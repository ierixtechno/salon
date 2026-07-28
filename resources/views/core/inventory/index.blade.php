<x-app-layout>
    <x-slot name="header">Stock Levels</x-slot>

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
                </form>

                <div class="flex flex-wrap gap-3 sm:ml-auto text-sm">
                    <a href="{{ route('inventory.movements', ['branch_id' => $branch?->id]) }}" class="text-indigo-600 hover:text-indigo-800 self-center">Movement ledger</a>
                    @can('inventory.adjust')
                        <a href="{{ route('inventory.transfer.form', ['branch_id' => $branch?->id]) }}" class="text-indigo-600 hover:text-indigo-800 self-center">Transfer stock</a>
                        <a href="{{ route('inventory.adjust.form', ['branch_id' => $branch?->id]) }}">
                            <x-primary-button>Adjust stock</x-primary-button>
                        </a>
                    @endcan
                </div>
            </div>

            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-gray-500">
                            <tr>
                                <th class="text-left px-4 py-2 font-medium">Product</th>
                                <th class="text-left px-4 py-2 font-medium">Category</th>
                                <th class="text-left px-4 py-2 font-medium">Quantity</th>
                                <th class="text-left px-4 py-2 font-medium">Reorder level</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($stocks as $stock)
                                <tr class="{{ $stock->isLowStock() ? 'bg-red-50' : '' }}">
                                    <td class="px-4 py-2 font-medium text-gray-900">{{ $stock->product->name }}</td>
                                    <td class="px-4 py-2 text-gray-500">{{ $stock->product->category->name }}</td>
                                    <td class="px-4 py-2 {{ $stock->isLowStock() ? 'text-red-700 font-medium' : 'text-gray-700' }}">
                                        {{ $stock->quantity }} {{ $stock->product->unit }}
                                        @if ($stock->isLowStock())
                                            <span class="text-xs ml-1">(low)</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 text-gray-500">{{ $stock->reorder_level_override ?? $stock->product->reorder_level }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-6 text-center text-gray-500">No stock recorded at this branch yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
