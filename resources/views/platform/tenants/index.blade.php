<x-platform-layout>
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-xl font-semibold text-gray-900">Tenants</h1>
        <a href="{{ route('platform.tenants.create') }}"
            class="inline-flex items-center px-4 py-2 bg-gray-800 text-white text-sm rounded-md hover:bg-gray-700">
            + New tenant
        </a>
    </div>

    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-gray-500">
                    <tr>
                        <th class="text-left px-4 py-2 font-medium">Name</th>
                        <th class="text-left px-4 py-2 font-medium">Status</th>
                        <th class="text-left px-4 py-2 font-medium">Users</th>
                        <th class="text-left px-4 py-2 font-medium">Created</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($tenants as $tenant)
                        <tr>
                            <td class="px-4 py-2">
                                <a href="{{ route('platform.tenants.show', $tenant) }}" class="text-indigo-600 hover:text-indigo-800">
                                    {{ $tenant->name }}
                                </a>
                            </td>
                            <td class="px-4 py-2 capitalize">{{ $tenant->status }}</td>
                            <td class="px-4 py-2">{{ $tenant->users_count }}</td>
                            <td class="px-4 py-2 text-gray-500">{{ $tenant->created_at->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-6 text-center text-gray-500">No tenants yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">
        {{ $tenants->links() }}
    </div>
</x-platform-layout>
