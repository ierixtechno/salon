<x-app-layout>
    <x-slot name="header">Appointments Report</x-slot>

    <div class="py-8">
        <div class="px-4 sm:px-6 lg:px-8">
            @include('core.reports._tabs')
            @include('core.reports._filters')

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
                <div class="bg-white shadow-sm rounded-lg p-4">
                    <p class="text-xs text-gray-500">Total</p>
                    <p class="text-xl font-semibold text-gray-900">{{ $total }}</p>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-4">
                    <p class="text-xs text-gray-500">Completed</p>
                    <p class="text-xl font-semibold text-gray-900">{{ $byStatus['completed'] ?? 0 }}</p>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-4">
                    <p class="text-xs text-gray-500">Cancelled</p>
                    <p class="text-xl font-semibold text-gray-900">{{ $byStatus['cancelled'] ?? 0 }}</p>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-4">
                    <p class="text-xs text-gray-500">No-show rate</p>
                    <p class="text-xl font-semibold text-gray-900">{{ $noShowRate }}%</p>
                </div>
            </div>

            <div class="bg-white shadow-sm rounded-lg overflow-hidden mb-6">
                <div class="px-6 py-4 border-b border-gray-100"><h3 class="font-medium text-gray-900">By status</h3></div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-indigo-100 text-indigo-800">
                            <tr>
                                <th class="text-left px-4 py-2 font-semibold text-base">Status</th>
                                <th class="text-left px-4 py-2 font-semibold text-base">Count</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($byStatus as $status => $count)
                                <tr>
                                    <td class="px-4 py-2 text-gray-700">{{ str($status)->replace('_', ' ')->headline() }}</td>
                                    <td class="px-4 py-2 text-gray-800">{{ $count }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="2" class="px-4 py-6 text-center text-gray-500">No appointments in this range.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 mb-6">
                <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100"><h3 class="font-medium text-gray-900">Top employees (completed)</h3></div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <tbody class="divide-y divide-gray-100">
                                @forelse ($byEmployee as $row)
                                    <tr>
                                        <td class="px-4 py-2 text-gray-700">{{ $row->name }}</td>
                                        <td class="px-4 py-2 text-gray-800 text-right">{{ $row->completed_count }}</td>
                                    </tr>
                                @empty
                                    <tr><td class="px-4 py-6 text-center text-gray-500">No data yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100"><h3 class="font-medium text-gray-900">Top services booked</h3></div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <tbody class="divide-y divide-gray-100">
                                @forelse ($byService as $row)
                                    <tr>
                                        <td class="px-4 py-2 text-gray-700">{{ $row->name }}</td>
                                        <td class="px-4 py-2 text-gray-800 text-right">{{ $row->booking_count }}</td>
                                    </tr>
                                @empty
                                    <tr><td class="px-4 py-6 text-center text-gray-500">No data yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            @if ($byBranch->isNotEmpty())
                <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100"><h3 class="font-medium text-gray-900">By branch</h3></div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-indigo-100 text-indigo-800">
                                <tr>
                                    <th class="text-left px-4 py-2 font-semibold text-base">Branch</th>
                                    <th class="text-left px-4 py-2 font-semibold text-base">Count</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($byBranch as $row)
                                    <tr>
                                        <td class="px-4 py-2 text-gray-700">{{ $row->branch_name }}</td>
                                        <td class="px-4 py-2 text-gray-800">{{ $row->count }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
