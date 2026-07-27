<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Suppliers</h2>
            @can('create', App\Domain\Core\Models\Supplier::class)
                <a href="{{ route('suppliers.create') }}"
                    class="inline-flex items-center px-4 py-2 bg-gray-800 text-white text-sm rounded-md hover:bg-gray-700">
                    + New supplier
                </a>
            @endcan
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                    {{ session('status') }}
                </div>
            @endif

            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-gray-500">
                            <tr>
                                <th class="text-left px-4 py-2 font-medium">Name</th>
                                <th class="text-left px-4 py-2 font-medium">Contact</th>
                                <th class="text-left px-4 py-2 font-medium">Phone</th>
                                <th class="text-left px-4 py-2 font-medium">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($suppliers as $supplier)
                                <tr>
                                    <td class="px-4 py-2">
                                        <a href="{{ route('suppliers.edit', $supplier) }}" class="text-indigo-600 hover:text-indigo-800 font-medium">
                                            {{ $supplier->name }}
                                        </a>
                                    </td>
                                    <td class="px-4 py-2 text-gray-500">{{ $supplier->contact_person ?? '—' }}</td>
                                    <td class="px-4 py-2 text-gray-500">{{ $supplier->phone ?? '—' }}</td>
                                    <td class="px-4 py-2">
                                        <span class="text-xs px-2 py-1 rounded-full {{ $supplier->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                                            {{ $supplier->is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-6 text-center text-gray-500">No suppliers yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
