<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Platform\Actions\OnboardTenant;
use App\Domain\Platform\Models\Module;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\OnboardTenantRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class OnboardingController extends Controller
{
    public function create(): View
    {
        return view('tenant.onboarding', [
            'modules' => Module::orderBy('name')->get(),
        ]);
    }

    public function store(OnboardTenantRequest $request, OnboardTenant $onboardTenant): RedirectResponse
    {
        $owner = $onboardTenant->execute($request->validated());

        Auth::guard('web')->login($owner);

        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }
}
