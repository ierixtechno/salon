<x-app-layout>
    <x-slot name="header">Module Performance Report</x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            @include('core.reports._tabs')
            @include('core.reports._filters')

            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h3 class="font-medium text-gray-900">Revenue and appointments by vertical</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-gray-500">
                            <tr>
                                <th class="text-left px-4 py-2 font-medium">Module</th>
                                <th class="text-left px-4 py-2 font-medium">Revenue</th>
                                <th class="text-left px-4 py-2 font-medium">Appointments</th>
                                <th class="text-left px-4 py-2 font-medium">Completed</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($rows as $row)
                                <tr>
                                    <td class="px-4 py-2 font-medium text-gray-800">{{ $row->module->name }}</td>
                                    <td class="px-4 py-2 text-gray-800">{{ current_tenant()->currency }} {{ number_format($row->revenue, 2) }}</td>
                                    <td class="px-4 py-2 text-gray-500">{{ $row->appointment_count }}</td>
                                    <td class="px-4 py-2 text-gray-500">{{ $row->completed_count }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-4 py-6 text-center text-gray-500">No modules enabled.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
