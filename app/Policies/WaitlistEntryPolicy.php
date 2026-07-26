<?php

namespace App\Policies;

use App\Domain\Core\Models\WaitlistEntry;
use App\Models\User;

class WaitlistEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('waitlist.view');
    }

    public function view(User $user, WaitlistEntry $entry): bool
    {
        return $user->can('waitlist.view') && $user->tenant_id === $entry->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->can('waitlist.create');
    }

    public function update(User $user, WaitlistEntry $entry): bool
    {
        return $user->can('waitlist.update') && $user->tenant_id === $entry->tenant_id;
    }

    public function delete(User $user, WaitlistEntry $entry): bool
    {
        return $user->can('waitlist.update') && $user->tenant_id === $entry->tenant_id;
    }
}
