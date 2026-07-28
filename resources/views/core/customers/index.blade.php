<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Customers</h2>
            @can('create', App\Domain\Core\Models\Customer::class)
                <a href="{{ route('customers.create') }}"
                    class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm rounded-md hover:bg-indigo-500">
                    + New customer
                </a>
            @endcan
        </div>
    </x-slot>

    <div class="py-8">
        <div class="px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                    {{ session('status') }}
                </div>
            @endif

            <form method="GET" action="{{ route('customers.index') }}" class="mb-4">
                <input type="text" name="search" value="{{ $search }}" placeholder="Search by name, phone, or email"
                    class="block w-full sm:max-w-sm border-gray-300 rounded-md shadow-sm text-sm">
            </form>

            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-indigo-100 text-indigo-800">
                            <tr>
                                <th class="text-left px-4 py-2 font-semibold text-base">Name</th>
                                <th class="text-left px-4 py-2 font-semibold text-base">Phone</th>
                                <th class="text-left px-4 py-2 font-semibold text-base">Email</th>
                                <th class="text-left px-4 py-2 font-semibold text-base">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($customers as $customer)
                                <tr>
                                    <td class="px-4 py-2">
                                        <a href="{{ route('customers.edit', $customer) }}" class="text-indigo-600 hover:text-indigo-800">
                                            {{ $customer->name }}
                                        </a>
                                    </td>
                                    <td class="px-4 py-2 text-gray-500">{{ $customer->phone ?? '—' }}</td>
                                    <td class="px-4 py-2 text-gray-500">{{ $customer->email ?? '—' }}</td>
                                    <td class="px-4 py-2">
                                        @if ($customer->isErased())
                                            <span class="text-xs px-2 py-1 rounded-full bg-red-100 text-red-700">Erased</span>
                                        @elseif (! $customer->is_active)
                                            <span class="text-xs px-2 py-1 rounded-full bg-gray-100 text-gray-600">Inactive</span>
                                        @else
                                            <span class="text-xs px-2 py-1 rounded-full bg-green-100 text-green-800">Active</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-6 text-center text-gray-500">No customers yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-4">{{ $customers->links() }}</div>
        </div>
    </div>
</x-app-layout>
