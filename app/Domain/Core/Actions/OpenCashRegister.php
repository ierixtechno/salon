<?php

namespace App\Domain\Core\Actions;

use App\Domain\Core\Models\Branch;
use App\Domain\Core\Models\CashRegisterSession;
use Illuminate\Support\Facades\DB;

/**
 * Only one `open` session per branch at a time. MySQL can't express a
 * partial unique index for `status = 'open'`, so this is enforced with a
 * transaction + row lock on any existing open session for the branch,
 * the same "lock a proxy row, then check-then-act" concurrency pattern
 * used for appointment booking/stock decrement (CLAUDE.md §24).
 */
class OpenCashRegister
{
    public function execute(Branch $branch, float $openingCash, ?int $openedBy, ?string $notes = null): CashRegisterSession
    {
        abort_if($openingCash < 0, 422, 'Opening cash cannot be negative.');

        return DB::transaction(function () use ($branch, $openingCash, $openedBy, $notes) {
            $existing = CashRegisterSession::where('branch_id', $branch->id)
                ->where('status', 'open')
                ->lockForUpdate()
                ->first();

            abort_if($existing, 409, 'This branch already has an open cash register session.');

            $session = new CashRegisterSession([
                'branch_id' => $branch->id,
                'opened_by' => $openedBy,
                'opened_at' => now(),
                'opening_cash' => $openingCash,
                'notes' => $notes,
            ]);
            $session->status = 'open';
            $session->save();

            return $session;
        });
    }
}
