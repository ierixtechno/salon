<?php

namespace App\Domain\Core\Support;

use App\Domain\Core\Models\CashMovement;
use App\Domain\Core\Models\CustomerMembership;
use App\Domain\Core\Models\CustomerPackage;
use App\Domain\Core\Models\Expense;
use App\Domain\Core\Models\GiftCard;
use App\Domain\Core\Models\Payment;
use App\Domain\Core\Models\Refund;
use App\Domain\Core\Models\SupplierPayment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * One chronological register of every real movement of money — what came in
 * and what went out — assembled at read time from the records that already
 * hold each movement, so it can never drift from them (CLAUDE.md §14:
 * reports enforce tenant + branch authorization; every source model is
 * already tenant-scoped).
 *
 * IN : invoice payments (cash/card/UPI/bank), gift card sales, package and
 *      membership sales made before they were invoiced, manual cash-in.
 * OUT: refunds, approved expenses, supplier payments, manual cash-out.
 *
 * Wallet, gift-card and loyalty redemptions are deliberately NOT listed —
 * they spend value that was already recorded as money in when it was
 * bought. Wallet top-ups are not listed either: a top-up records no payment
 * method, so it cannot be placed in the register honestly.
 *
 * Branch-less records (gift card sales, supplier payments) are only shown
 * to a user with access to all branches, and only when no single branch is
 * selected — never to a user restricted to particular branches.
 */
class BuildPaymentLedger
{
    /** Money-in methods: redemptions of stored value are not real money. */
    private const REAL_METHODS = ['cash', 'card', 'upi', 'bank_transfer'];

    /**
     * @param  array<int>  $branchIds  branches the user may see, already narrowed by any filter
     * @return Collection<int, array{at: Carbon, source: string, reference: ?string, party: ?string, method: string, in: float, out: float, branch: ?string}>
     */
    public function execute(Carbon $from, Carbon $to, array $branchIds, Collection $branchNames, bool $includeBranchless, string $timezone): Collection
    {
        // Timestamps are stored in UTC; the caller's range is in the tenant's timezone.
        $fromUtc = $from->copy()->utc();
        $toUtc = $to->copy()->utc();

        $entries = collect();

        $push = function (Carbon $at, string $source, ?string $reference, ?string $party, string $method, float $in, float $out, ?int $branchId) use ($entries, $branchNames) {
            $entries->push([
                'at' => $at,
                'source' => $source,
                'reference' => $reference,
                'party' => $party,
                'method' => $method,
                'in' => round($in, 2),
                'out' => round($out, 2),
                'branch' => $branchId ? ($branchNames[$branchId] ?? null) : null,
            ]);
        };

        // --- IN: invoice payments (a tip is money received too)
        Payment::with('invoice')
            ->whereIn('method', self::REAL_METHODS)
            ->whereBetween('created_at', [$fromUtc, $toUtc])
            ->whereHas('invoice', fn ($q) => $q->whereIn('branch_id', $branchIds))
            ->get()
            ->each(fn (Payment $p) => $push($p->created_at, 'Invoice payment', $p->invoice->invoice_number, $p->invoice->customer_name, $p->method, (float) $p->amount + (float) $p->tip_amount, 0, $p->invoice->branch_id));

        // --- IN: package/membership sales that pre-date invoicing (no invoice to carry the payment)
        CustomerPackage::with(['package', 'customer'])->whereNull('invoice_id')
            ->whereIn('branch_id', $branchIds)->whereBetween('purchased_at', [$fromUtc, $toUtc])->where('price_paid', '>', 0)
            ->get()
            ->each(fn (CustomerPackage $s) => $push($s->purchased_at, 'Package sale (no invoice)', null, $s->customer?->name, $s->purchase_method, (float) $s->price_paid, 0, $s->branch_id));

        CustomerMembership::with(['membershipPlan', 'customer'])->whereNull('invoice_id')
            ->whereIn('branch_id', $branchIds)->whereBetween('starts_at', [$fromUtc, $toUtc])->where('price_paid', '>', 0)
            ->get()
            ->each(fn (CustomerMembership $s) => $push($s->starts_at, 'Membership sale (no invoice)', null, $s->customer?->name, $s->purchase_method, (float) $s->price_paid, 0, $s->branch_id));

        // --- IN/OUT: manual cash-drawer adjustments
        CashMovement::whereIn('type', ['cash_in', 'cash_out'])
            ->whereIn('branch_id', $branchIds)->whereBetween('created_at', [$fromUtc, $toUtc])
            ->get()
            ->each(fn (CashMovement $m) => $push(
                $m->created_at,
                $m->type === 'cash_in' ? 'Cash in' : 'Cash out',
                null,
                $m->reason,
                'cash',
                $m->type === 'cash_in' ? (float) $m->amount : 0,
                $m->type === 'cash_out' ? (float) $m->amount : 0,
                $m->branch_id,
            ));

        // --- OUT: refunds
        Refund::with('invoice')
            ->whereBetween('created_at', [$fromUtc, $toUtc])
            ->whereHas('invoice', fn ($q) => $q->whereIn('branch_id', $branchIds))
            ->get()
            ->each(fn (Refund $r) => $push($r->created_at, 'Refund', $r->invoice->invoice_number, $r->invoice->customer_name, $r->method, 0, (float) $r->amount, $r->invoice->branch_id));

        // --- OUT: approved expenses (dated by expense_date, at the start of that day in the tenant's timezone)
        Expense::with('expenseCategory')
            ->where('status', 'approved')
            ->whereIn('branch_id', $branchIds)
            ->whereBetween('expense_date', [$from->copy()->timezone($timezone)->toDateString(), $to->copy()->timezone($timezone)->toDateString()])
            ->get()
            ->each(fn (Expense $e) => $push(
                Carbon::parse($e->expense_date->toDateString(), $timezone)->startOfDay()->utc(),
                'Expense',
                $e->expenseCategory?->name,
                $e->vendor_name,
                $e->payment_method,
                0,
                (float) $e->amount,
                $e->branch_id,
            ));

        if ($includeBranchless) {
            GiftCard::whereBetween('issued_at', [$fromUtc, $toUtc])->where('initial_value', '>', 0)
                ->get()
                ->each(fn (GiftCard $g) => $push($g->issued_at, 'Gift card sale', $g->code, null, $g->purchase_method, (float) $g->initial_value, 0, null));

            SupplierPayment::with('supplier')->whereBetween('paid_at', [$fromUtc, $toUtc])
                ->get()
                ->each(fn (SupplierPayment $s) => $push($s->paid_at, 'Supplier payment', $s->reference, $s->supplier?->name, $s->method, 0, (float) $s->amount, null));
        }

        return $entries->sortByDesc(fn (array $e) => $e['at']->getTimestamp())->values();
    }
}
