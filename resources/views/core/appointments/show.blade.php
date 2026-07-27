<x-app-layout>
    <x-slot name="header">Appointment — {{ $appointment->customer->name }}</x-slot>

    <div class="py-8">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                    {{ session('status') }}
                </div>
            @endif

            <a href="{{ route('appointments.index', ['branch_id' => $appointment->branch_id]) }}" class="text-sm text-indigo-600 hover:text-indigo-800">&larr; Back to appointments</a>

            <div class="bg-white shadow-sm rounded-lg p-6 space-y-3">
                <div class="flex items-center justify-between">
                    <h3 class="font-medium text-gray-900">{{ $appointment->service->name }}{{ $appointment->serviceVariant ? ' — '.$appointment->serviceVariant->name : '' }}</h3>
                    <span class="text-xs px-2 py-1 rounded-full border bg-gray-50 text-gray-700 border-gray-200">
                        {{ str($appointment->status)->replace('_', ' ')->headline() }}
                    </span>
                </div>
                <dl class="text-sm text-gray-600 grid grid-cols-2 gap-y-1">
                    <dt class="text-gray-400">Customer</dt><dd>{{ $appointment->customer->name }}</dd>
                    <dt class="text-gray-400">Branch</dt><dd>{{ $appointment->branch->name }}</dd>
                    <dt class="text-gray-400">Staff</dt><dd>{{ $appointment->employee->name }}</dd>
                    @if ($appointment->resource)
                        <dt class="text-gray-400">Resource</dt><dd>{{ $appointment->resource->name }}</dd>
                    @endif
                    <dt class="text-gray-400">Time</dt>
                    <dd>{{ $appointment->starts_at->timezone($appointment->branch->effectiveTimezone())->format('d M Y, h:i A') }} – {{ $appointment->ends_at->timezone($appointment->branch->effectiveTimezone())->format('h:i A') }}</dd>
                    <dt class="text-gray-400">Price</dt><dd>{{ $appointment->price }}</dd>
                    @if ($appointment->notes)
                        <dt class="text-gray-400">Notes</dt><dd>{{ $appointment->notes }}</dd>
                    @endif
                    @if ($appointment->status === 'cancelled' && $appointment->cancellation_reason)
                        <dt class="text-gray-400">Cancellation reason</dt><dd>{{ $appointment->cancellation_reason }}</dd>
                    @endif
                </dl>
            </div>

            @can('update', $appointment)
                <div class="bg-white shadow-sm rounded-lg p-6 flex flex-wrap gap-3">
                    @if ($appointment->canTransitionTo('checked_in'))
                        <form method="POST" action="{{ route('appointments.check-in', $appointment) }}">
                            @csrf
                            <x-primary-button>Check in</x-primary-button>
                        </form>
                    @endif
                    @if ($appointment->canTransitionTo('in_service'))
                        <form method="POST" action="{{ route('appointments.start', $appointment) }}">
                            @csrf
                            <x-primary-button>Start service</x-primary-button>
                        </form>
                    @endif
                    @if ($appointment->canTransitionTo('completed'))
                        <form method="POST" action="{{ route('appointments.complete', $appointment) }}">
                            @csrf
                            <x-primary-button>Complete</x-primary-button>
                        </form>
                    @endif
                    @if ($appointment->canTransitionTo('no_show'))
                        <form method="POST" action="{{ route('appointments.no-show', $appointment) }}">
                            @csrf
                            <x-secondary-button>Mark no-show</x-secondary-button>
                        </form>
                    @endif
                </div>
            @endcan

            @if ($appointment->status === 'completed' && $appointment->service->consumables->isNotEmpty() && auth()->user()->can('inventory.adjust'))
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="font-medium text-gray-900 mb-1">Product usage</h3>
                    @if ($consumptionRecorded)
                        <p class="text-sm text-gray-500">Product usage has already been recorded for this appointment.</p>
                    @else
                        <p class="text-xs text-gray-500 mb-4">Deducts this service's usual products (see the service's "Product consumption" settings) from {{ $appointment->branch->name }}'s stock.</p>
                        <form method="POST" action="{{ route('appointments.consumption', $appointment) }}">
                            @csrf
                            <x-primary-button>Record product usage</x-primary-button>
                        </form>
                    @endif
                </div>
            @endif

            @if ($appointment->status === 'completed' && auth()->user()->can('packages.redeem'))
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="font-medium text-gray-900 mb-1">Package redemption</h3>
                    @if ($packageRedemption)
                        <p class="text-sm text-gray-500">
                            Redeemed against "{{ $packageRedemption->customerPackageItem->customerPackage->package->name }}" on
                            {{ $packageRedemption->redeemed_at->format('d M Y, h:i A') }}.
                        </p>
                    @elseif ($redeemablePackageItems->isEmpty())
                        <p class="text-sm text-gray-500">This customer has no active package covering this service.</p>
                    @else
                        <p class="text-xs text-gray-500 mb-4">Deducts one unit from the customer's prepaid package instead of charging for this service.</p>
                        <form method="POST" action="{{ route('appointments.redeem-package', $appointment) }}" class="flex flex-col sm:flex-row gap-3 sm:items-end">
                            @csrf
                            <div class="flex-1">
                                <select name="customer_package_item_id" required class="block w-full border-gray-300 rounded-md shadow-sm text-sm">
                                    @foreach ($redeemablePackageItems as $item)
                                        <option value="{{ $item->id }}">
                                            {{ $item->customerPackage->package->name }} &mdash; {{ $item->quantityRemaining() }} remaining
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <x-primary-button>Redeem package</x-primary-button>
                        </form>
                    @endif
                </div>
            @endif

            @can('update', $appointment)
                @if (in_array($appointment->status, ['pending', 'confirmed']))
                    <div class="bg-white shadow-sm rounded-lg p-6">
                        <h3 class="font-medium text-gray-900 mb-4">Reschedule</h3>
                        <form method="PUT" action="{{ route('appointments.reschedule', $appointment) }}" class="flex flex-col sm:flex-row gap-3">
                            @csrf
                            @method('PUT')
                            <x-text-input class="block w-full" type="datetime-local" name="starts_at" required />
                            <x-primary-button>Reschedule</x-primary-button>
                        </form>
                        <x-input-error :messages="$errors->get('starts_at')" class="mt-2" />
                    </div>
                @endif
            @endcan

            @can('cancel', $appointment)
                @if ($appointment->canTransitionTo('cancelled'))
                    <div class="bg-white shadow-sm rounded-lg p-6 border border-red-200">
                        <h3 class="font-medium text-red-900 mb-4">Cancel appointment</h3>
                        <form method="POST" action="{{ route('appointments.cancel', $appointment) }}" onsubmit="return confirm('Cancel this appointment?')" class="space-y-3">
                            @csrf
                            <textarea name="reason" rows="2" placeholder="Reason (optional)"
                                class="block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm"></textarea>
                            <x-danger-button>Cancel appointment</x-danger-button>
                        </form>
                    </div>
                @endif
            @endcan
        </div>
    </div>
</x-app-layout>
