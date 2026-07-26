<?php

namespace App\Domain\Core\Actions;

use App\Domain\Core\Models\AuditLog;
use App\Domain\Core\Models\EmployeeProfile;
use App\Domain\Platform\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class CreateEmployee
{
    public function execute(Tenant $tenant, array $data): User
    {
        return DB::transaction(function () use ($tenant, $data) {
            // tenant_id is deliberately not fillable on User (CLAUDE.md §28)
            // — set directly, same pattern as OnboardTenant.
            $user = new User([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'is_active' => true,
                'all_branches' => (bool) ($data['all_branches'] ?? false),
            ]);
            $user->tenant_id = $tenant->id;
            $user->save();

            EmployeeProfile::create([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'job_title' => $data['job_title'] ?? null,
                'employment_type' => $data['employment_type'],
                'hire_date' => $data['hire_date'] ?? null,
                'phone' => $data['phone'] ?? null,
            ]);

            if (! $user->all_branches) {
                $user->branches()->sync($data['branches'] ?? []);
            }

            app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
            $role = Role::where('tenant_id', $tenant->id)->where('name', $data['role'])->firstOrFail();
            $user->assignRole($role);

            // Role assignment is audited per CLAUDE.md §47. tenant_id/actor
            // are passed explicitly rather than via AuditLog::record()'s
            // ambient-context helper — this action may run without a `web`
            // session (e.g. Super Admin tooling, tests).
            AuditLog::create([
                'tenant_id' => $tenant->id,
                'user_id' => Auth::guard('web')->id(),
                'action' => 'employee.role_assigned',
                'entity_type' => 'User',
                'entity_id' => $user->id,
                'meta' => ['role' => $role->name],
            ]);

            return $user;
        });
    }
}
