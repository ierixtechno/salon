<?php

namespace App\Domain\Core\Actions;

use App\Domain\Core\Models\AuditLog;
use App\Domain\Core\Models\EmployeeProfile;
use App\Domain\Core\Support\EnforceUserLimit;
use App\Domain\Platform\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class UpdateEmployee
{
    public function execute(User $user, EmployeeProfile $profile, array $data): void
    {
        DB::transaction(function () use ($user, $profile, $data) {
            // Switching a deactivated employee back on takes a user slot again.
            if (! $user->is_active && (bool) ($data['is_active'] ?? false)) {
                $tenant = Tenant::whereKey($user->tenant_id)->lockForUpdate()->firstOrFail();
                app(EnforceUserLimit::class)->check($tenant);
            }

            $previousEmail = $user->email;

            $user->update([
                'name' => $data['name'],
                'email' => $data['email'],
                'is_active' => (bool) ($data['is_active'] ?? $user->is_active),
                'all_branches' => (bool) ($data['all_branches'] ?? false),
            ]);

            $profile->update([
                'job_title' => $data['job_title'] ?? null,
                'employment_type' => $data['employment_type'],
                'hire_date' => $data['hire_date'] ?? null,
                'phone' => $data['phone'] ?? null,
            ]);

            $user->branches()->sync($user->all_branches ? [] : ($data['branches'] ?? []));

            app(PermissionRegistrar::class)->setPermissionsTeamId($user->tenant_id);
            $previousRole = $user->roles()->first()?->name;
            $role = Role::where('tenant_id', $user->tenant_id)->where('name', $data['role'])->firstOrFail();
            $user->syncRoles([$role]);

            if ($previousEmail !== $user->email) {
                AuditLog::create([
                    'tenant_id' => $user->tenant_id,
                    'user_id' => Auth::guard('web')->id(),
                    'action' => 'employee.email_changed',
                    'entity_type' => 'User',
                    'entity_id' => $user->id,
                    'meta' => ['from' => $previousEmail, 'to' => $user->email],
                ]);
            }

            if ($previousRole !== $role->name) {
                // Role changes are audited per CLAUDE.md §47.
                AuditLog::create([
                    'tenant_id' => $user->tenant_id,
                    'user_id' => Auth::guard('web')->id(),
                    'action' => 'employee.role_changed',
                    'entity_type' => 'User',
                    'entity_id' => $user->id,
                    'meta' => ['from' => $previousRole, 'to' => $role->name],
                ]);
            }
        });
    }
}
