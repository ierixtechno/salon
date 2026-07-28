<x-app-layout>
    <x-slot name="header">Tattoo Consultations — {{ $customer->name }}</x-slot>

    <div class="py-8">
        <div class="px-4 sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                    {{ session('status') }}
                </div>
            @endif

            <div class="flex items-center justify-between">
                <div class="flex gap-4 text-sm">
                    <a href="{{ route('customers.edit', $customer) }}" class="text-indigo-600 hover:text-indigo-800">&larr; Back to customer</a>
                    <a href="{{ route('tattoo.profile.edit', $customer) }}" class="text-indigo-600 hover:text-indigo-800">Tattoo profile</a>
                </div>
                @can('tattoo-consultations.create')
                    <a href="{{ route('tattoo.consultations.create', $customer) }}">
                        <x-primary-button>New consultation</x-primary-button>
                    </a>
                @endcan
            </div>

            <div class="bg-white shadow-sm rounded-lg divide-y divide-gray-100">
                @forelse ($consultations as $consultation)
                    <div class="p-6">
                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <p class="font-medium text-gray-900">{{ $consultation->consultation_date->toFormattedDateString() }}</p>
                            <p class="text-xs text-gray-500">{{ $consultation->branch->name }}{{ $consultation->consultant ? ' — '.$consultation->consultant->name : '' }}</p>
                        </div>
                        @if ($consultation->design_description)
                            <p class="text-sm text-gray-700 mt-2"><span class="font-medium">Design:</span> {{ $consultation->design_description }}</p>
                        @endif
                        @if ($consultation->placement)
                            <p class="text-sm text-gray-700 mt-1"><span class="font-medium">Placement:</span> {{ $consultation->placement }}</p>
                        @endif
                        @if ($consultation->size_estimate)
                            <p class="text-sm text-gray-700 mt-1"><span class="font-medium">Size:</span> {{ $consultation->size_estimate }}</p>
                        @endif
                        @if ($consultation->aftercare_instructions)
                            <p class="text-sm text-gray-700 mt-1"><span class="font-medium">Aftercare:</span> {{ $consultation->aftercare_instructions }}</p>
                        @endif
                        @if ($consultation->notes)
                            <p class="text-sm text-gray-500 mt-1">{{ $consultation->notes }}</p>
                        @endif
                    </div>
                @empty
                    <p class="p-6 text-sm text-gray-500">No consultations recorded yet.</p>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
