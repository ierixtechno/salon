<?php

namespace App\Http\Controllers\Public;

use App\Domain\Core\Actions\BookPublicAppointment;
use App\Domain\Core\Actions\CancelAppointment;
use App\Domain\Core\Actions\FindOrCreatePublicCustomer;
use App\Domain\Core\Actions\GetAvailableSlots;
use App\Domain\Core\Actions\RecordCustomerConsent;
use App\Domain\Core\Models\Appointment;
use App\Domain\Core\Models\Branch;
use App\Domain\Core\Models\Service;
use App\Domain\Core\Models\ServiceVariant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StorePublicBookingRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * The app's first fully public, unauthenticated surface (CLAUDE.md §75).
 * Tenant context comes from ResolveTenantFromSlug (bound as the `tenant`
 * request attribute + guestTenant container binding), never from client
 * input. No employee picker (auto-assigned by BookPublicAppointment), no
 * resource assignment, no persistent customer login — self-service here
 * means "view/cancel the one booking you just made via its unguessable
 * public_token link", not a full customer account portal.
 */
class PublicBookingController extends Controller
{
    public function show(Request $request): View
    {
        $tenant = $request->attributes->get('tenant');
        $branches = Branch::where('is_active', true)->orderBy('name')->get();

        $branchTree = $branches->map(function (Branch $branch) {
            $enabledModuleCodes = $branch->modules()->wherePivot('enabled', true)->pluck('code');

            $services = Service::where('is_active', true)
                ->whereHas('branches', fn ($q) => $q->where('branches.id', $branch->id)->where('is_available', true))
                ->whereHas('module', fn ($q) => $q->whereIn('code', $enabledModuleCodes))
                ->with(['category', 'variants' => fn ($q) => $q->where('is_active', true)])
                ->orderBy('name')
                ->get();

            return [
                'id' => $branch->id,
                'name' => $branch->name,
                'services' => $services->map(fn (Service $s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'category' => $s->category->name,
                    'duration_minutes' => $s->duration_minutes,
                    'price' => $s->priceForBranch($branch),
                    'variants' => $s->variants->map(fn ($v) => [
                        'id' => $v->id,
                        'name' => $v->name,
                        'price' => $v->effectivePrice(),
                        'duration_minutes' => $v->effectiveDurationMinutes(),
                    ]),
                ]),
            ];
        });

        return view('public.booking.show', [
            'tenant' => $tenant,
            'branches' => $branches,
            'branchTree' => $branchTree,
            'maxDate' => now()->addDays(30)->toDateString(),
            'minDate' => now()->toDateString(),
        ]);
    }

    public function slots(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        $validated = $request->validate([
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')->where('tenant_id', $tenant->id)->where('is_active', true)],
            'service_id' => ['required', 'integer', Rule::exists('services', 'id')->where('tenant_id', $tenant->id)->where('is_active', true)],
            'service_variant_id' => ['nullable', 'integer', Rule::exists('service_variants', 'id')->where('tenant_id', $tenant->id)],
            'date' => ['required', 'date', 'after_or_equal:today', 'before_or_equal:'.now()->addDays(30)->toDateString()],
        ]);

        $branch = Branch::findOrFail($validated['branch_id']);
        $service = Service::findOrFail($validated['service_id']);
        $variant = ! empty($validated['service_variant_id']) ? ServiceVariant::find($validated['service_variant_id']) : null;

        abort_unless(
            $branch->hasModuleEnabled($service->module->code) && $service->isAvailableAtBranch($branch),
            404,
        );

        $date = Carbon::parse($validated['date'], $branch->effectiveTimezone());
        $slots = app(GetAvailableSlots::class)->execute($branch, $service, $variant, $date);

        return response()->json([
            'slots' => collect($slots)->map(fn (Carbon $slot) => [
                'iso' => $slot->toIso8601String(),
                'label' => $slot->copy()->setTimezone($branch->effectiveTimezone())->format('h:i A'),
            ])->values(),
        ]);
    }

    public function store(StorePublicBookingRequest $request): RedirectResponse
    {
        $tenant = $request->attributes->get('tenant');

        // Honeypot tripped — respond exactly like success, create nothing,
        // never reveal that anything was detected (CLAUDE.md §75).
        if ($request->filled('website')) {
            return redirect()->route('public.booking.show', $tenant->slug)
                ->with('status', "Thanks! We'll be in touch shortly to confirm your booking.");
        }

        $branch = Branch::findOrFail($request->validated('branch_id'));
        $service = Service::findOrFail($request->validated('service_id'));
        $variant = $request->validated('service_variant_id') ? ServiceVariant::findOrFail($request->validated('service_variant_id')) : null;

        abort_unless(
            $branch->hasModuleEnabled($service->module->code) && $service->isAvailableAtBranch($branch),
            422,
            'This service is no longer available at the selected branch.',
        );

        $customer = app(FindOrCreatePublicCustomer::class)->execute(
            name: $request->validated('name'),
            phone: $request->validated('phone'),
            email: $request->validated('email'),
        );

        app(RecordCustomerConsent::class)->execute($customer, 'service_delivery', true, null);
        if ($request->boolean('marketing_consent')) {
            app(RecordCustomerConsent::class)->execute($customer, 'marketing', true, null);
        }

        $appointment = app(BookPublicAppointment::class)->execute(
            branch: $branch,
            customer: $customer,
            service: $service,
            variant: $variant,
            startsAt: Carbon::parse($request->validated('starts_at')),
        );

        return redirect()->route('public.booking.confirmation', [
            'tenant_slug' => $tenant->slug,
            'token' => $appointment->public_token,
        ]);
    }

    /**
     * `$tenantSlug` is unused directly (tenant context comes from the
     * `tenant` request attribute) but MUST stay in the signature: Laravel
     * binds non-model scalar route parameters into controller parameters
     * positionally, not by name — dropping it would silently bind the
     * `{token}` route segment's value into `$tenantSlug` and vice versa.
     */
    public function confirmation(Request $request, string $tenantSlug, string $token): View
    {
        $tenant = $request->attributes->get('tenant');

        // Eloquent, so TenantScope (via the guestTenant binding) already
        // confines this to the tenant resolved from the URL — a token
        // minted under a different tenant simply won't be found here.
        $appointment = Appointment::where('public_token', $token)
            ->with(['branch', 'service', 'serviceVariant'])
            ->firstOrFail();

        return view('public.booking.confirmation', [
            'tenant' => $tenant,
            'appointment' => $appointment,
        ]);
    }

    public function cancel(Request $request, string $tenantSlug, string $token, CancelAppointment $action): RedirectResponse
    {
        $tenant = $request->attributes->get('tenant');
        $appointment = Appointment::where('public_token', $token)->firstOrFail();

        abort_unless($appointment->canTransitionTo('cancelled'), 409, 'This booking can no longer be cancelled.');

        $action->execute($appointment, 'Cancelled by customer online.');

        return redirect()->route('public.booking.confirmation', ['tenant_slug' => $tenant->slug, 'token' => $token])
            ->with('status', 'Your booking has been cancelled.');
    }
}
