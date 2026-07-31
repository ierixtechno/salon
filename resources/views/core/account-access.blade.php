<x-app-layout>
    <x-slot name="header">Account Access</x-slot>

    <div class="py-8">
        <div class="px-4 sm:px-6 lg:px-8 max-w-lg mx-auto">
            <div class="bg-white shadow-sm rounded-lg p-8 text-center">
                @if ($state->isPending())
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-blue-100 text-blue-600 mb-4">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2m6-2a10 10 0 11-20 0 10 10 0 0120 0z" />
                        </svg>
                    </div>
                    <h2 class="text-lg font-semibold text-gray-900">Welcome to {{ config('platform.brand_name') }}!</h2>
                    <p class="mt-2 text-sm text-gray-600">
                        Your account has been created. Our team will send you an invoice shortly — once it's paid, your dashboard unlocks immediately.
                    </p>
                @else
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-red-100 text-red-600 mb-4">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <h2 class="text-lg font-semibold text-gray-900">Your subscription has expired</h2>
                    <p class="mt-2 text-sm text-gray-600">
                        Your subscription (and its 7-day grace period) ended {{ $state->daysSinceExpiry }} {{ Str::plural('day', $state->daysSinceExpiry) }} ago. Renew now to restore full access.
                    </p>
                @endif

                <div class="mt-6 border-t border-gray-100 pt-6">
                    @can('tenant.billing.manage')
                        @if ($pendingQuotation)
                            <p class="text-sm text-gray-600">
                                Quotation <span class="font-medium text-gray-900">{{ $pendingQuotation->quotation_number }}</span>
                                — ₹{{ number_format($pendingQuotation->amount, 2) }}
                            </p>
                            <a href="{{ route('billing.quotations.show', $pendingQuotation) }}"
                                class="mt-4 inline-flex items-center justify-center rounded-md bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                                Pay Now
                            </a>
                        @else
                            <p class="text-sm text-gray-500">
                                No invoice is pending yet. Please contact your account manager to receive one.
                            </p>
                        @endif
                    @else
                        <p class="text-sm text-gray-500">
                            Contact your account owner or manager to complete payment and restore access.
                        </p>
                    @endcan
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
