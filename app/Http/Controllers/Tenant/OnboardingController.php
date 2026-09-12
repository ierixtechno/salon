<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Platform\Actions\OnboardTenant;
use App\Domain\Platform\Models\Module;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\OnboardTenantRequest;
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
     */
    public function store(OnboardTenantRequest $request, OnboardTenant $onboardTenant): RedirectResponse
    {
        $onboardTenant->execute($request->validated());

        return redirect()->route('login')->with(
            'status',
            "Your account has been created. We'll send you an invoice shortly — once it's paid, you'll be able to log in.",
        );
    }
}
