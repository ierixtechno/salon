<x-platform-layout>
    <x-slot name="header">Tenants</x-slot>

    <div class="flex items-center justify-end mb-5">
        <a href="{{ route('platform.tenants.create') }}"
            class="inline-flex items-center gap-1.5 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg shadow-sm hover:bg-indigo-500 transition">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
            </svg>
            New tenant
        </a>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-100">
                    <tr>
                        <th class="text-left px-5 py-3 font-semibold uppercase text-xs tracking-wider text-slate-600">Name</th>
                        <th class="text-left px-5 py-3 font-semibold uppercase text-xs tracking-wider text-slate-600">Status</th>
                        <th class="text-left px-5 py-3 font-semibold uppercase text-xs tracking-wider text-slate-600">Users</th>
                        <th class="text-left px-5 py-3 font-semibold uppercase text-xs tracking-wider text-slate-600">Created</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($tenants as $tenant)
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-3">
                                <a href="{{ route('platform.tenants.show', $tenant) }}" class="flex items-center gap-3 group">
                                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-indigo-700 text-xs font-semibold">
                                        {{ strtoupper(substr($tenant->name, 0, 2)) }}
                                    </span>
                                    <span class="font-medium text-gray-900 group-hover:text-indigo-600">{{ $tenant->name }}</span>
                                </a>
                            </td>
                            <td class="px-5 py-3"><x-platform.status-badge :status="$tenant->status" /></td>
                            <td class="px-5 py-3 text-gray-600">{{ $tenant->users_count }}</td>
                            <td class="px-5 py-3 text-gray-500">{{ $tenant->created_at->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-5 py-10 text-center text-gray-400">No tenants yet.</td>
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
