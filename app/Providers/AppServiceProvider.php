<?php

namespace App\Providers;

use App\Auth\TenantAwareUserProvider;
use App\Domain\Core\Models\Branch;
use App\Domain\Core\Models\Customer;
use App\Domain\Core\Models\EmployeeProfile;
use App\Domain\Core\Models\Resource as BranchResource;
use App\Policies\BranchPolicy;
use App\Policies\CustomerPolicy;
use App\Policies\EmployeeProfilePolicy;
use App\Policies\ResourcePolicy;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // See app/Auth/TenantAwareUserProvider.php — auth identity
        // resolution deliberately bypasses BelongsToTenant's TenantScope,
        // since it runs before any tenant context can exist.
        Auth::provider('tenant_eloquent', fn ($app, array $config) => new TenantAwareUserProvider($app['hash'], $config['model']));

        // Explicit policy registration: models under App\Domain\* don't
        // reliably hit Laravel's convention-based policy auto-discovery
        // (same issue as factory resolution — see Tenant/PlatformAdmin
        // newFactory() overrides).
        Gate::policy(Branch::class, BranchPolicy::class);
        Gate::policy(BranchResource::class, ResourcePolicy::class);
        Gate::policy(EmployeeProfile::class, EmployeeProfilePolicy::class);
        Gate::policy(Customer::class, CustomerPolicy::class);
    }
}
