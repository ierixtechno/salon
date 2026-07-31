@php
    $state = $subscriptionAccessState ?? null;
    $blockedFlash = session('subscription_blocked');
@endphp

@can('tenant.billing.manage')
    @if ($blockedFlash)
        <div class="bg-red-50 border-b border-red-200 px-4 sm:px-6 lg:px-8 py-3">
            <div class="flex items-center justify-between gap-4 flex-wrap">
                <p class="text-sm text-red-800">{{ $blockedFlash }}</p>
                <a href="{{ route('billing.quotations.index') }}" class="text-sm font-semibold text-red-800 hover:text-red-900 underline shrink-0">
                    Go to Billing
                </a>
            </div>
        </div>
    @elseif ($state?->isGrace())
        <div class="bg-red-50 border-b border-red-200 px-4 sm:px-6 lg:px-8 py-3">
            <div class="flex items-center justify-between gap-4 flex-wrap">
                <p class="text-sm text-red-800">
                    Your subscription expired {{ $state->daysSinceExpiry }} {{ Str::plural('day', $state->daysSinceExpiry) }} ago —
                    {{ $state->graceDaysLeft }} {{ Str::plural('day', $state->graceDaysLeft) }} of read-only access left. Renew now to restore full access.
                </p>
                <a href="{{ route('billing.quotations.index') }}" class="text-sm font-semibold text-red-800 hover:text-red-900 underline shrink-0">
                    Pay Now
                </a>
            </div>
        </div>
    @elseif ($state?->showsReminderBanner())
        <div class="bg-amber-50 border-b border-amber-200 px-4 sm:px-6 lg:px-8 py-3">
            <div class="flex items-center justify-between gap-4 flex-wrap">
                <p class="text-sm text-amber-800">
                    Your {{ $state->planName }} subscription renews in {{ $state->reminderDaysLeft }} {{ Str::plural('day', $state->reminderDaysLeft) }}.
                </p>
                <a href="{{ route('billing.quotations.index') }}" class="text-sm font-semibold text-amber-800 hover:text-amber-900 underline shrink-0">
                    View Billing
                </a>
            </div>
        </div>
    @endif
@endcan
