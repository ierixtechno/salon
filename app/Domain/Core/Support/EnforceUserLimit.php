<?php

namespace App\Domain\Core\Support;

use App\Domain\Platform\Models\Tenant;
use Illuminate\Validation\ValidationException;

/**
 * The employee (user) cap that comes with the tenant's plan and branch count
 * (SubscriptionPlan::userLimitFor). Call inside a transaction, after locking
 * the tenant row, so two simultaneous requests cannot both take the last slot
 * (CLAUDE.md §24). Users a tenant already has above the limit are never
 * removed — the cap only stops NEW active users being added.
 */
class EnforceUserLimit
{
    public function check(Tenant $tenant): void
    {
        $limit = $tenant->userLimit();

        if ($limit === null) {
            return;
        }

        if ($tenant->activeUserCount() >= $limit) {
            throw ValidationException::withMessages([
                'user_limit' => "Your plan allows {$limit} ".str('user')->plural($limit).' and all are in use. Add branches or upgrade your plan under Billing > Plans to add more.',
            ]);
        }
    }
}
