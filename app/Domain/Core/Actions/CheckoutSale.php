<?php

namespace App\Domain\Core\Actions;

use App\Domain\Core\Actions\Concerns\RecalculatesInvoiceTotals;
use App\Domain\Core\Models\Invoice;
use App\Domain\Core\Models\InvoiceSequence;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Finalizes a draft invoice: assigns the GST-compliant sequential invoice
 * number (D-003, docs/decisions/README.md) and locks in the totals. This
 * is the one moment an invoice becomes a real legal document — CLAUDE.md
 * §24 requires invoice numbering to be concurrency-safe, so the per-
 * (branch, financial-year) counter row is locked with `lockForUpdate()`
 * before being read+incremented, the same "lock a proxy row" pattern used
 * throughout this codebase (BookAppointment) for the same reason: MySQL
 * has no native atomic-sequence primitive, so the row lock is what
 * actually prevents two simultaneous checkouts from getting the same
 * number.
 */
class CheckoutSale
{
    use RecalculatesInvoiceTotals;

    public function execute(Invoice $invoice): Invoice
    {
        abort_unless($invoice->canTransitionTo('finalized'), 409, "Cannot finalize an invoice that is currently {$invoice->status}.");
        abort_if($invoice->lines()->count() === 0, 422, 'Cannot finalize an invoice with no line items.');

        return DB::transaction(function () use ($invoice) {
            $this->recalculateTotals($invoice);

            $financialYear = $this->financialYearFor(now());

            // "Insert, ignore if it already exists (created concurrently
            // by another checkout), then lock+read" — the lockForUpdate()
            // below is the actual serialization point; this just makes
            // sure a row exists to lock, race-safely, without a
            // check-then-insert gap of its own.
            try {
                $newSequence = new InvoiceSequence([
                    'branch_id' => $invoice->branch_id,
                    'financial_year' => $financialYear,
                    'next_number' => 1,
                ]);
                $newSequence->save();
            } catch (UniqueConstraintViolationException) {
                // Already exists — fine, the lock+read below picks it up.
            }

            $sequence = InvoiceSequence::where('branch_id', $invoice->branch_id)
                ->where('financial_year', $financialYear)
                ->lockForUpdate()
                ->firstOrFail();

            $sequenceNumber = $sequence->next_number;
            $sequence->next_number = $sequenceNumber + 1;
            $sequence->save();

            $invoice->sequence_number = $sequenceNumber;
            $invoice->financial_year = $financialYear;
            $invoice->invoice_number = sprintf('%s/%s/%06d', $invoice->branch->code, $financialYear, $sequenceNumber);
            $invoice->status = 'finalized';
            $invoice->finalized_at = now();
            $invoice->save();

            return $invoice;
        });
    }

    /**
     * India's financial year runs 1 April – 31 March, e.g. "2025-26".
     */
    private function financialYearFor(\DateTimeInterface $date): string
    {
        $year = (int) $date->format('Y');
        $startYear = ((int) $date->format('n') >= 4) ? $year : $year - 1;

        return sprintf('%d-%02d', $startYear, ($startYear + 1) % 100);
    }
}
