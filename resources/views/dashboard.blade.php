<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Dashboard</h2>

            @if ($branches->count() > 1)
                <form method="GET" class="text-sm">
                    <select name="branch_id" onchange="this.form.submit()" class="border-gray-300 rounded-md shadow-sm text-sm">
                        @foreach ($branches as $b)
                            <option value="{{ $b->id }}" @selected($branch?->id === $b->id)>{{ $b->name }}</option>
                        @endforeach
                    </select>
                </form>
            @endif
        </div>
    </x-slot>

    <div class="py-8">
        <div class="px-4 sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                    {{ session('status') }}
                </div>
            @endif

            @if (! $branch)
                <div class="bg-white shadow-sm rounded-lg p-8 text-center">
                    <h3 class="font-medium text-gray-900 mb-1">Let's get your first branch set up</h3>
                    <p class="text-sm text-gray-500 mb-4">Everything else — services, appointments, invoicing — starts once you have at least one active branch.</p>
                    @can('viewAny', App\Domain\Core\Models\Branch::class)
                        <a href="{{ route('branches.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm rounded-md hover:bg-indigo-500">
                            + Add a branch
                        </a>
                    @endcan
                </div>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    @can('appointments.view')
                        <div class="bg-gradient-to-br from-blue-500 to-blue-600 shadow-sm rounded-lg p-5 flex items-start gap-4 text-white">
                            <div class="shrink-0 rounded-lg bg-white/20 p-2.5">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3.75 18.75h16.5A1.5 1.5 0 0021.75 17.25V6.75a1.5 1.5 0 00-1.5-1.5H3.75a1.5 1.5 0 00-1.5 1.5v10.5a1.5 1.5 0 001.5 1.5z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 9.75h18" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-2xl font-semibold">{{ $appointmentCounts['upcoming'] }}</p>
                                <p class="text-sm text-blue-100">Upcoming today</p>
                                <p class="text-xs text-blue-200 mt-0.5">{{ $appointmentCounts['completed'] }} completed so far</p>
                            </div>
                        </div>
                    @endcan

                    @can('invoices.view')
                        <div class="bg-gradient-to-br from-emerald-500 to-emerald-600 shadow-sm rounded-lg p-5 flex items-start gap-4 text-white">
                            <div class="shrink-0 rounded-lg bg-white/20 p-2.5">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182C10.55 7.72 11.275 7.5 12 7.5c.768 0 1.536.219 2.121.659L15 8.818" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-2xl font-semibold">{{ number_format((float) $todaysSales, 2) }}</p>
                                <p class="text-sm text-emerald-100">Sales today</p>
                                <a href="{{ route('invoices.index', ['branch_id' => $branch->id]) }}" class="text-xs text-white underline decoration-emerald-200 hover:decoration-white mt-0.5 inline-block">View invoices &rarr;</a>
                            </div>
                        </div>
                    @endcan

                    @can('customers.view')
                        <div class="bg-gradient-to-br from-purple-500 to-purple-600 shadow-sm rounded-lg p-5 flex items-start gap-4 text-white">
                            <div class="shrink-0 rounded-lg bg-white/20 p-2.5">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-2xl font-semibold">{{ $newCustomersThisMonth }}</p>
                                <p class="text-sm text-purple-100">New customers</p>
                                <p class="text-xs text-purple-200 mt-0.5">this month</p>
                            </div>
                        </div>
                    @endcan

                    @can('inventory.view')
                        <div class="bg-gradient-to-br {{ $lowStockItems->isNotEmpty() ? 'from-red-500 to-red-600' : 'from-slate-500 to-slate-600' }} shadow-sm rounded-lg p-5 flex items-start gap-4 text-white">
                            <div class="shrink-0 rounded-lg bg-white/20 p-2.5">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-2xl font-semibold">{{ $lowStockItems->count() }}</p>
                                <p class="text-sm {{ $lowStockItems->isNotEmpty() ? 'text-red-100' : 'text-slate-200' }}">Low stock alerts</p>
                                <a href="{{ route('inventory.index', ['branch_id' => $branch->id]) }}" class="text-xs text-white underline decoration-white/50 hover:decoration-white mt-0.5 inline-block">View stock &rarr;</a>
                            </div>
                        </div>
                    @endcan
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    @can('appointments.view')
                        <div class="lg:col-span-2 bg-white shadow-sm rounded-lg">
                            <div class="p-5 border-b border-gray-100 flex items-center justify-between">
                                <h3 class="font-medium text-gray-900">Today's schedule</h3>
                                <a href="{{ route('appointments.index', ['branch_id' => $branch->id]) }}" class="text-sm text-indigo-600 hover:text-indigo-800">View all &rarr;</a>
                            </div>
                            <div class="divide-y divide-gray-100 max-h-[28rem] overflow-y-auto">
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
                                @forelse ($todaysAppointments as $appointment)
                                    <a href="{{ route('appointments.show', $appointment) }}" class="p-4 flex items-center gap-4 hover:bg-gray-50">
                                        <div class="w-20 text-sm font-medium text-gray-700 shrink-0">
                                            {{ $appointment->starts_at->timezone($branch->effectiveTimezone())->format('h:i A') }}
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm font-medium text-gray-900 truncate">{{ $appointment->customer->name }}</p>
                                            <p class="text-xs text-gray-500 truncate">{{ $appointment->service->name }} &mdash; {{ $appointment->employee->name }}</p>
                                        </div>
                                        <span class="text-xs px-2 py-1 rounded-full border shrink-0 {{ $badgeColors[$appointment->status] ?? 'bg-gray-50 text-gray-700 border-gray-200' }}">
                                            {{ str($appointment->status)->replace('_', ' ')->headline() }}
                                        </span>
                                    </a>
                                @empty
                                    <p class="p-6 text-sm text-gray-500">No appointments scheduled for today.</p>
                                @endforelse
                            </div>
                        </div>
                    @endcan

                    <div class="space-y-6">
                        <div class="bg-white shadow-sm rounded-lg p-5">
                            <h3 class="font-medium text-gray-900 mb-3">Quick actions</h3>
                            <div class="grid grid-cols-1 gap-2 text-sm">
                                @can('appointments.create')
                                    <a href="{{ route('appointments.create', ['branch_id' => $branch->id]) }}" class="flex items-center justify-between rounded-md border border-gray-200 px-3 py-2 hover:bg-gray-50">
                                        Book appointment <span aria-hidden="true">&rarr;</span>
                                    </a>
                                @endcan
                                @can('invoices.create')
                                    <a href="{{ route('invoices.create', ['branch_id' => $branch->id]) }}" class="flex items-center justify-between rounded-md border border-gray-200 px-3 py-2 hover:bg-gray-50">
                                        New sale <span aria-hidden="true">&rarr;</span>
                                    </a>
                                @endcan
                                @can('customers.create')
                                    <a href="{{ route('customers.create') }}" class="flex items-center justify-between rounded-md border border-gray-200 px-3 py-2 hover:bg-gray-50">
                                        Add customer <span aria-hidden="true">&rarr;</span>
                                    </a>
                                @endcan
                            </div>
                        </div>

                        @can('inventory.view')
                            @if ($lowStockItems->isNotEmpty())
                                <div class="bg-white shadow-sm rounded-lg p-5">
                                    <h3 class="font-medium text-gray-900 mb-3">Low stock</h3>
                                    <ul class="space-y-2 text-sm">
                                        @foreach ($lowStockItems->take(5) as $stock)
                                            <li class="flex items-center justify-between">
                                                <span class="text-gray-700 truncate">{{ $stock->product->name }}</span>
                                                <span class="text-red-600 font-medium shrink-0 ml-2">{{ $stock->quantity }} left</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                    <a href="{{ route('inventory.index', ['branch_id' => $branch->id]) }}" class="text-xs text-indigo-600 hover:text-indigo-800 mt-3 inline-block">View all &rarr;</a>
                                </div>
                            @endif
                        @endcan
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
