<x-app-layout>
    <x-slot name="header">Purchase Orders</x-slot>

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

                <div class="flex gap-3 sm:ml-auto">
                    <a href="{{ route('purchase-returns.index', ['branch_id' => $branch?->id]) }}" class="text-sm text-indigo-600 hover:text-indigo-800 self-center">Returns</a>
                    @can('create', \App\Domain\Core\Models\PurchaseOrder::class)
                        <a href="{{ route('purchase-orders.create', ['branch_id' => $branch?->id]) }}">
                            <x-primary-button>New order</x-primary-button>
                        </a>
                    @endcan
                </div>
            </div>

            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-gray-500">
                            <tr>
                                <th class="text-left px-4 py-2 font-medium">PO #</th>
                                <th class="text-left px-4 py-2 font-medium">Supplier</th>
                                <th class="text-left px-4 py-2 font-medium">Status</th>
                                <th class="text-left px-4 py-2 font-medium">Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($orders as $order)
                                <tr>
                                    <td class="px-4 py-2">
                                        <a href="{{ route('purchase-orders.show', $order) }}" class="text-indigo-600 hover:text-indigo-800 font-medium">{{ $order->poNumber() }}</a>
                                    </td>
                                    <td class="px-4 py-2 text-gray-700">{{ $order->supplier->name }}</td>
                                    <td class="px-4 py-2 text-gray-500">{{ str($order->status)->replace('_', ' ')->headline() }}</td>
                                    <td class="px-4 py-2 text-gray-500">{{ $order->created_at->format('d M Y') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-6 text-center text-gray-500">No purchase orders yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
