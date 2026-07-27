<?php

namespace App\Domain\Core\Actions;

use App\Domain\Core\Models\CommissionEntry;
use App\Domain\Core\Models\CommissionRule;
use App\Domain\Core\Models\Invoice;

/**
 * Called once, when an invoice first reaches `paid` (CLAUDE.md §22 —
 * commission only from authoritative finalized transaction data, never a
 * client-submitted value). Skips lines with no employee attributed
 * (`performed_by` null) or no active commission rule for that employee —
 * both are safe, silent no-ops, not errors. Guarded against double-accrual
 * if this is ever called twice for the same invoice.
 */
class AccrueCommission
{
    public function execute(Invoice $invoice): void
    {
        if (CommissionEntry::where('invoice_id', $invoice->id)->where('type', 'accrual')->exists()) {
            return;
        }

        $rulesByUserId = CommissionRule::where('is_active', true)->get()->keyBy('user_id');

        foreach ($invoice->lines as $line) {
            if (! $line->performed_by || ! $rulesByUserId->has($line->performed_by)) {
                continue;
            }

            $rule = $rulesByUserId->get($line->performed_by);
            $amount = $rule->amountFor((float) $line->taxable_value);

            if ($amount <= 0) {
                continue;
            }

            CommissionEntry::create([
                'user_id' => $line->performed_by,
                'invoice_id' => $invoice->id,
                'invoice_line_id' => $line->id,
                'type' => 'accrual',
                'amount' => $amount,
                'rate_applied' => $rule->rate,
            ]);
        }
    }
}
