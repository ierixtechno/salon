<?php

namespace App\Domain\Core\Actions;

use App\Domain\Core\Models\CommissionEntry;
use App\Domain\Core\Models\Invoice;

/**
 * Called when an invoice reaches `refunded` (fully refunded — CLAUDE.md
 * §22 "refunds/voids adjust commission"). Reverses every accrual entry for
 * the invoice in full. Deliberately does not attempt a proportional
 * partial-reversal for a partial refund that leaves the invoice `paid` —
 * exact partial-commission clawback needs a business rule nobody has
 * specified yet, so it's left for a future decision rather than guessed
 * (see docs/modules/EMPLOYEE.md).
 */
class ReverseCommission
{
    public function execute(Invoice $invoice): void
    {
        $accrualEntries = CommissionEntry::where('invoice_id', $invoice->id)->where('type', 'accrual')->get();

        $alreadyReversedIds = CommissionEntry::where('invoice_id', $invoice->id)
            ->where('type', 'reversal')
            ->pluck('reversed_entry_id')
            ->all();

        foreach ($accrualEntries as $entry) {
            if (in_array($entry->id, $alreadyReversedIds, true)) {
                continue;
            }

            CommissionEntry::create([
                'user_id' => $entry->user_id,
                'invoice_id' => $invoice->id,
                'invoice_line_id' => $entry->invoice_line_id,
                'type' => 'reversal',
                'amount' => -(float) $entry->amount,
                'rate_applied' => $entry->rate_applied,
                'reversed_entry_id' => $entry->id,
            ]);
        }
    }
}
