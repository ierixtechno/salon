<x-public-layout :tenant="$tenant">
    @if (session('status'))
        <div class="mb-6 rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
            {{ session('status') }}
        </div>
    @endif

    <div class="bg-white shadow-sm rounded-lg p-6 space-y-4">
        <div class="flex items-center justify-between">
            <h2 class="text-base font-semibold text-gray-900">Your booking</h2>
            <span @class([
                'text-xs px-2 py-1 rounded-full',
                'bg-amber-100 text-amber-800' => $appointment->status === 'pending',
                'bg-green-100 text-green-800' => $appointment->status === 'confirmed',
                'bg-gray-100 text-gray-600' => $appointment->status === 'cancelled',
            ])>
                {{ str($appointment->status)->replace('_', ' ')->headline() }}
            </span>
        </div>

        <dl class="text-sm text-gray-600 space-y-1">
            <div class="flex justify-between"><dt class="text-gray-400">Branch</dt><dd>{{ $appointment->branch->name }}</dd></div>
            <div class="flex justify-between"><dt class="text-gray-400">Service</dt><dd>{{ $appointment->service->name }}{{ $appointment->serviceVariant ? ' ('.$appointment->serviceVariant->name.')' : '' }}</dd></div>
            <div class="flex justify-between"><dt class="text-gray-400">Date &amp; time</dt><dd>{{ $appointment->starts_at->timezone($appointment->branch->effectiveTimezone())->format('d M Y, h:i A') }}</dd></div>
        </dl>

        @if ($appointment->status === 'pending')
            <p class="text-xs text-gray-500">We'll confirm this shortly. Keep this page's link if you'd like to cancel later.</p>
        @endif

        @if (in_array($appointment->status, ['pending', 'confirmed']) && $appointment->starts_at->isFuture())
            <form method="POST" action="{{ route('public.booking.cancel', ['tenant_slug' => $tenant->slug, 'token' => $appointment->public_token]) }}" onsubmit="return confirm('Cancel this booking?')">
                @csrf
                <x-danger-button type="submit">Cancel booking</x-danger-button>
            </form>
        @endif
    </div>

    <a href="{{ route('public.booking.show', $tenant->slug) }}" class="inline-block mt-4 text-sm text-indigo-600 hover:text-indigo-800">&larr; Book another appointment</a>
</x-public-layout>
