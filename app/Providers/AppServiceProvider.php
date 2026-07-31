<?php

namespace App\Providers;

use App\Auth\TenantAwareUserProvider;
use App\Domain\Core\Contracts\SmsProvider;
use App\Domain\Core\Contracts\WhatsAppProvider;
use App\Domain\Core\Models\Appointment;
use App\Domain\Core\Models\Branch;
use App\Domain\Core\Models\Customer;
use App\Domain\Core\Models\EmployeeProfile;
use App\Domain\Core\Models\GiftCard;
use App\Domain\Core\Models\Invoice;
use App\Domain\Core\Models\MembershipPlan;
use App\Domain\Core\Models\Package;
use App\Domain\Core\Models\Product;
use App\Domain\Core\Models\ProductCategory;
use App\Domain\Core\Models\PurchaseOrder;
use App\Domain\Core\Models\Resource as BranchResource;
use App\Domain\Core\Models\Service;
use App\Domain\Core\Models\ServiceCategory;
use App\Domain\Core\Models\Supplier;
use App\Domain\Core\Models\WaitlistEntry;
use App\Domain\Core\Notifications\Providers\NullSmsProvider;
use App\Domain\Core\Notifications\Providers\NullWhatsAppProvider;
use App\Domain\Platform\Contracts\PaymentGatewayProvider;
use App\Domain\Platform\Payments\NullPaymentGatewayProvider;
use App\Domain\Platform\Payments\RazorpayPaymentGatewayProvider;
use App\Policies\AppointmentPolicy;
use App\Policies\BranchPolicy;
use App\Policies\CustomerPolicy;
use App\Policies\EmployeeProfilePolicy;
use App\Policies\GiftCardPolicy;
use App\Policies\InvoicePolicy;
use App\Policies\MembershipPlanPolicy;
use App\Policies\PackagePolicy;
use App\Policies\ProductCategoryPolicy;
use App\Policies\ProductPolicy;
use App\Policies\PurchaseOrderPolicy;
use App\Policies\ResourcePolicy;
use App\Policies\ServiceCategoryPolicy;
use App\Policies\ServicePolicy;
use App\Policies\SupplierPolicy;
use App\Policies\WaitlistEntryPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Razorpay\Api\Api;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Provider drivers default to 'null' (see config/notifications.php)
        // — CLAUDE.md §42: swapping to a real SMS/WhatsApp provider is a
        // config change plus a new class implementing the interface, never
        // a change to any caller.
        $this->app->bind(SmsProvider::class, function (Application $app) {
            return match (config('notifications.sms.driver')) {
                default => new NullSmsProvider,
            };
        });

        $this->app->bind(WhatsAppProvider::class, function (Application $app) {
            return match (config('notifications.whatsapp.driver')) {
                default => new NullWhatsAppProvider,
            };
        });

        $this->app->bind(PaymentGatewayProvider::class, function (Application $app) {
            return match (config('payments.driver')) {
                'razorpay' => new RazorpayPaymentGatewayProvider(new Api(
                    config('services.razorpay.key'),
                    config('services.razorpay.secret'),
                )),
                default => new NullPaymentGatewayProvider,
            };
        });
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
        Gate::policy(ServiceCategory::class, ServiceCategoryPolicy::class);
        Gate::policy(Service::class, ServicePolicy::class);
        Gate::policy(Appointment::class, AppointmentPolicy::class);
        Gate::policy(WaitlistEntry::class, WaitlistEntryPolicy::class);
        Gate::policy(Invoice::class, InvoicePolicy::class);
        Gate::policy(ProductCategory::class, ProductCategoryPolicy::class);
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(Supplier::class, SupplierPolicy::class);
        Gate::policy(PurchaseOrder::class, PurchaseOrderPolicy::class);
        Gate::policy(Package::class, PackagePolicy::class);
        Gate::policy(MembershipPlan::class, MembershipPlanPolicy::class);
        Gate::policy(GiftCard::class, GiftCardPolicy::class);

        // Public booking (CLAUDE.md §75): limited per IP (a single scraper/
        // bot) AND per tenant slug (many IPs hammering one tenant's page)
        // independently — either limit alone is bypassable.
        RateLimiter::for('public-booking', function (Request $request) {
            return [
                Limit::perMinute(20)->by('public-booking-ip:'.$request->ip()),
                Limit::perMinute(60)->by('public-booking-tenant:'.$request->route('tenant_slug')),
            ];
        });
    }
}
