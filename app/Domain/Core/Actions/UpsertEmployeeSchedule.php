<?php

namespace App\Domain\Core\Actions;

use App\Domain\Core\Models\EmployeeSchedule;
use App\Models\User;

/**
 * Weekly recurring availability template per (employee, branch) pair —
 * read by the Phase 5 Appointment Engine, not itself an attendance/leave
 * record (Phase 9). See docs/modules/EMPLOYEE.md.
 */
class UpsertEmployeeSchedule
{
    public function execute(User $user, int $branchId, array $shifts): void
    {
        foreach ($shifts as $shift) {
            $isOff = (bool) ($shift['is_off'] ?? false);

            if ($isOff) {
                EmployeeSchedule::where('user_id', $user->id)
                    ->where('branch_id', $branchId)
                    ->where('day_of_week', $shift['day_of_week'])
                    ->delete();

                continue;
            }

            EmployeeSchedule::updateOrCreate(
                ['user_id' => $user->id, 'branch_id' => $branchId, 'day_of_week' => $shift['day_of_week']],
                ['tenant_id' => $user->tenant_id, 'starts_at' => $shift['starts_at'], 'ends_at' => $shift['ends_at']],
            );
        }
    }
}
