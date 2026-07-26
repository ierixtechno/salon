<?php

namespace App\Policies;

use App\Domain\Core\Models\Appointment;
use App\Models\User;

/**
 * Row-level "staff sees only their own appointments" (docs/04-RBAC.md's
 * description of the Staff default) is not implemented here — no other
 * resource in this codebase (Services, Consultations) scopes visibility
 * below tenant+branch either, so Staff gets the same branch-scoped access
 * as Manager, just without `cancel`. Revisit if a real customer need for
 * per-employee visibility scoping surfaces.
 */
class AppointmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('appointments.view');
    }

    public function view(User $user, Appointment $appointment): bool
    {
        return $user->can('appointments.view')
            && $user->tenant_id === $appointment->tenant_id
            && $user->canAccessBranch($appointment->branch);
    }

    public function create(User $user): bool
    {
        return $user->can('appointments.create');
    }

    public function update(User $user, Appointment $appointment): bool
    {
        return $user->can('appointments.update')
            && $user->tenant_id === $appointment->tenant_id
            && $user->canAccessBranch($appointment->branch);
    }

    public function cancel(User $user, Appointment $appointment): bool
    {
        return $user->can('appointments.cancel')
            && $user->tenant_id === $appointment->tenant_id
            && $user->canAccessBranch($appointment->branch);
    }
}
