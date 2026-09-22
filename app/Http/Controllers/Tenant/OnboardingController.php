<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Core\Actions\SendNotification;
use App\Domain\Platform\Actions\NotifyPlatformAdmins;
use App\Domain\Platform\Actions\OnboardTenant;
use App\Domain\Platform\Models\Module;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\OnboardTenantRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class OnboardingController extends Controller
{
    public function create(): View
    {
        return view('tenant.onboarding', [
            'modules' => Module::orderBy('name')->get(),
        ]);
    }

    /**
     * Deliberately does NOT log the new owner in — a tenant that has never
     * paid must not be able to use the app at all (LoginRequest::
     * authenticate() enforces this on every login attempt, not just this
     * one), so there is no working dashboard to send them to yet. They
     * land back on the login page with an explanatory message instead;
     * once Super Admin creates a quotation and it's paid, that same
     * login works normally.
     *
     * Because they can't log in, nothing inside the app can reach them —
     * so they get a confirmation email, and Super Admin gets an alert that
     * a signup is waiting for a quotation (otherwise it would sit unnoticed
     * until someone happened to open the Tenants list).
     */
    public function store(OnboardTenantRequest $request, OnboardTenant $onboardTenant): RedirectResponse
    {
        $data = $request->validated();
        $owner = $onboardTenant->execute($data);

        $this->announceSignup($owner, $data);

        return redirect()->route('login')->with(
            'status',
            "Your account has been created. We'll send you an invoice shortly — once it's paid, you'll be able to log in.",
        );
    }

    /**
     * The account already exists by this point — a mail/queue hiccup must
     * never turn a successful signup into an error page (CLAUDE.md §37),
     * so this is isolated and only reported.
     */
    private function announceSignup(User $owner, array $data): void
    {
        try {
            $brand = config('platform.brand_name');

            app(SendNotification::class)->execute(
                tenantId: $owner->tenant_id,
                channel: 'email',
                recipientType: 'user',
                recipientId: $owner->id,
                toAddress: $owner->email,
                subject: "We've received your registration",
                body: "Hello {$owner->name},\n\n"
                    ."Thank you for registering {$data['business_name']} on {$brand}.\n\n"
                    ."Your account has been created. We'll email your quotation shortly — "
                    ."once payment is confirmed, you'll be able to log in and start using your account.\n\n"
                    ."Log in here when you're ready:\n".route('login')."\n\n"
                    ."- {$brand}",
            );

            app(NotifyPlatformAdmins::class)->execute(
                subject: "New signup: {$data['business_name']}",
                body: "A new business has registered and is waiting for a quotation.\n\n"
                    ."Business: {$data['business_name']}\n"
                    ."Owner: {$owner->name} <{$owner->email}>\n"
                    .'Modules requested: '.implode(', ', $data['modules'] ?? [])."\n"
                    .'Billing state: '.($data['billing_state'] ?? 'not given')."\n"
                    .'GSTIN: '.($data['gstin'] ?? 'not given')."\n\n"
                    ."Review and create a quotation:\n".route('platform.tenants.show', $owner->tenant_id),
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
