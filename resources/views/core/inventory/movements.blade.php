<x-app-layout>
    <x-slot name="header">Stock Movements</x-slot>

    <div class="py-8">
        <div class="px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white shadow-sm rounded-lg p-4 flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-4">
                <form method="GET" class="flex flex-wrap items-center gap-3">
                    <select name="branch_id" onchange="this.form.submit()" class="border-gray-300 rounded-md shadow-sm text-sm">
                        @foreach ($branches as $b)
                            <option value="{{ $b->id }}" @selected($branch?->id === $b->id)>{{ $b->name }}</option>
                        @endforeach
                    </select>
                </form>
                <a href="{{ route('inventory.index', ['branch_id' => $branch?->id]) }}" class="text-sm text-indigo-600 hover:text-indigo-800 sm:ml-auto">&larr; Stock levels</a>
            </div>

            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-indigo-100 text-indigo-800">
                            <tr>
                                <th class="text-left px-4 py-2 font-semibold text-base">Date</th>
                                <th class="text-left px-4 py-2 font-semibold text-base">Product</th>
                                <th class="text-left px-4 py-2 font-semibold text-base">Type</th>
                                <th class="text-left px-4 py-2 font-semibold text-base">Quantity</th>
                                <th class="text-left px-4 py-2 font-semibold text-base">By</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($movements as $movement)
                                <tr>
                                    <td class="px-4 py-2 text-gray-500">{{ $movement->occurred_at->format('d M Y, h:i A') }}</td>
                                    <td class="px-4 py-2 font-medium text-gray-900">{{ $movement->product->name }}</td>
                                    <td class="px-4 py-2 text-gray-500">{{ str($movement->type)->replace('_', ' ')->headline() }}</td>
                                    <td class="px-4 py-2 {{ $movement->quantity >= 0 ? 'text-green-700' : 'text-red-700' }}">
                                        {{ $movement->quantity >= 0 ? '+' : '' }}{{ $movement->quantity }}
                                    </td>
                                    <td class="px-4 py-2 text-gray-500">{{ $movement->performedBy?->name ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-6 text-center text-gray-500">No movements recorded at this branch yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if (! is_array($movements) && method_exists($movements, 'links'))
                {{ $movements->links() }}
            @endif
        </div>
    </div>
</x-app-layout>
