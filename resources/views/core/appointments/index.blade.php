<x-app-layout>
    <x-slot name="header">Appointments</x-slot>

    <div class="py-8">
        <div class="px-4 sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                    {{ session('status') }}
                </div>
            @endif

            <div class="bg-white shadow-sm rounded-lg p-4 flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-4">
                <form method="GET" class="flex flex-wrap items-center gap-3">
                    <select name="branch_id" onchange="this.form.submit()" class="border-gray-300 rounded-md shadow-sm text-sm">
                        @foreach ($branches as $b)
                            <option value="{{ $b->id }}" @selected($branch?->id === $b->id)>{{ $b->name }}</option>
                        @endforeach
                    </select>
                    <input type="date" name="date" value="{{ $date->toDateString() }}" onchange="this.form.submit()" class="border-gray-300 rounded-md shadow-sm text-sm">
                </form>

                @can('create', \App\Domain\Core\Models\Appointment::class)
                    <a href="{{ route('appointments.create', ['branch_id' => $branch?->id]) }}" class="sm:ml-auto">
                        <x-primary-button>New appointment</x-primary-button>
                    </a>
                @endcan
            </div>

            <div class="bg-white shadow-sm rounded-lg divide-y divide-gray-100">
                @forelse ($appointments as $appointment)
                    @php
                        $badgeColors = [
                            'confirmed' => 'bg-blue-50 text-blue-700 border-blue-200',
                            'checked_in' => 'bg-amber-50 text-amber-700 border-amber-200',
                            'in_service' => 'bg-purple-50 text-purple-700 border-purple-200',
                            'completed' => 'bg-green-50 text-green-700 border-green-200',
                            'no_show' => 'bg-red-50 text-red-700 border-red-200',
                            'pending' => 'bg-gray-50 text-gray-700 border-gray-200',
                        ];
                    @endphp
                    <div class="p-4 flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-4">
                        <div class="w-full sm:w-28 text-sm font-medium text-gray-700">
                            {{ $appointment->starts_at->timezone($branch->effectiveTimezone())->format('h:i A') }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <a href="{{ route('appointments.show', $appointment) }}" class="font-medium text-gray-900 hover:text-indigo-600">{{ $appointment->customer->name }}</a>
                            <p class="text-xs text-gray-500 truncate">{{ $appointment->service->name }} — {{ $appointment->employee->name }}{{ $appointment->resource ? ' — '.$appointment->resource->name : '' }}</p>
                        </div>
                        <span class="text-xs px-2 py-1 rounded-full border shrink-0 {{ $badgeColors[$appointment->status] ?? 'bg-gray-50 text-gray-700 border-gray-200' }}">
                            {{ str($appointment->status)->replace('_', ' ')->headline() }}
                        </span>
                    </div>
                @empty
                    <p class="p-6 text-sm text-gray-500">No appointments for this day.</p>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
