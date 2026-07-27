<?php

namespace App\Domain\Core\Actions;

use App\Domain\Core\Models\CustomerMembership;

class CancelCustomerMembership
{
    public function execute(CustomerMembership $membership, ?string $reason): CustomerMembership
    {
        abort_unless($membership->status === 'active', 409, "Cannot cancel a membership that is currently {$membership->status}.");

        $membership->status = 'cancelled';
        $membership->cancelled_at = now();
        $membership->cancel_reason = $reason;
        $membership->save();

        return $membership;
    }
}
