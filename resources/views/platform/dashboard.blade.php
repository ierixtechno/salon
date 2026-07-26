<x-platform-layout>
    <h1 class="text-xl font-semibold text-gray-900 mb-6">Dashboard</h1>

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-8">
        @foreach ([
            'Trial' => $counts['trial'],
            'Active' => $counts['active'],
            'Suspended' => $counts['suspended'],
            'Cancelled' => $counts['cancelled'],
        ] as $label => $count)
            <div class="bg-white rounded-lg shadow-sm p-4">
                <div class="text-2xl font-semibold text-gray-900">{{ $count }}</div>
                <div class="text-sm text-gray-500">{{ $label }}</div>
            </div>
        @endforeach
    </div>

    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
            <h2 class="font-medium text-gray-900">Recent tenants</h2>
            <a href="{{ route('platform.tenants.index') }}" class="text-sm text-indigo-600 hover:text-indigo-800">View all</a>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-gray-500">
                    <tr>
                        <th class="text-left px-4 py-2 font-medium">Name</th>
                        <th class="text-left px-4 py-2 font-medium">Status</th>
                        <th class="text-left px-4 py-2 font-medium">Created</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($recentTenants as $tenant)
                        <tr>
                            <td class="px-4 py-2">
                                <a href="{{ route('platform.tenants.show', $tenant) }}" class="text-indigo-600 hover:text-indigo-800">
                                    {{ $tenant->name }}
                                </a>
                            </td>
                            <td class="px-4 py-2 capitalize">{{ $tenant->status }}</td>
                            <td class="px-4 py-2 text-gray-500">{{ $tenant->created_at->diffForHumans() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-platform-layout>
