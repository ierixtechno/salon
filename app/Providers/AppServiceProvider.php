<?php

namespace App\Providers;

use App\Auth\TenantAwareUserProvider;
use Illuminate\Support\Facades\Auth;
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
    }
}
