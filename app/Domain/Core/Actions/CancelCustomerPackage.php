<?php

namespace App\Domain\Core\Actions;

use App\Domain\Core\Models\CustomerPackage;

class CancelCustomerPackage
{
    public function execute(CustomerPackage $customerPackage, ?string $reason): CustomerPackage
    {
        abort_unless($customerPackage->status === 'active', 409, "Cannot cancel a package that is currently {$customerPackage->status}.");

        $customerPackage->status = 'cancelled';
        $customerPackage->cancelled_at = now();
        $customerPackage->cancel_reason = $reason;
        $customerPackage->save();

        return $customerPackage;
    }
}
