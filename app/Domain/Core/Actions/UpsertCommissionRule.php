<?php

namespace App\Domain\Core\Actions;

use App\Domain\Core\Models\CommissionRule;
use App\Models\User;

class UpsertCommissionRule
{
    public function execute(User $employee, string $type, float $rate, bool $isActive): CommissionRule
    {
        return CommissionRule::updateOrCreate(
            ['user_id' => $employee->id],
            ['type' => $type, 'rate' => $rate, 'is_active' => $isActive],
        );
    }
}
