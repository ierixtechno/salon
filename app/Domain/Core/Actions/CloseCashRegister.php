<?php

namespace App\Domain\Core\Actions;

use App\Domain\Core\Models\CashRegisterSession;
use Illuminate\Support\Facades\DB;

/**
 * Snapshots `expected_closing` (opening_cash + sum of the session's
 * ledger, CLAUDE.md §14) and `difference` (actual - expected) at close
 * time — once closed, no further cash movements can be recorded against
 * this session (RecordCashMovement aborts on a non-open session), so the
 * snapshot never drifts from the ledger it was computed from.
 */
class CloseCashRegister
{
    public function execute(CashRegisterSession $session, float $actualClosing, ?int $closedBy, ?string $notes = null): CashRegisterSession
    {
        abort_unless($session->status === 'open', 409, 'This cash register session is already closed.');
        abort_if($actualClosing < 0, 422, 'Actual closing amount cannot be negative.');

        return DB::transaction(function () use ($session, $actualClosing, $closedBy, $notes) {
            $session = CashRegisterSession::whereKey($session->id)->lockForUpdate()->firstOrFail();
            abort_unless($session->status === 'open', 409, 'This cash register session is already closed.');

            $expectedClosing = $session->runningTotal();

            $session->status = 'closed';
            $session->closed_by = $closedBy;
            $session->closed_at = now();
            $session->actual_closing = $actualClosing;
            $session->expected_closing = $expectedClosing;
            $session->difference = round($actualClosing - $expectedClosing, 2);
            if ($notes !== null) {
                $session->notes = $notes;
            }
            $session->save();

            return $session;
        });
    }
}
