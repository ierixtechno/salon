<x-app-layout>
    <x-slot name="header">{{ $order->poNumber() }} — {{ $order->supplier->name }}</x-slot>

    <div class="py-8">
        <div class="px-4 sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                    {{ session('status') }}
                </div>
            @endif

            <a href="{{ route('purchase-orders.index', ['branch_id' => $order->branch_id]) }}" class="text-sm text-indigo-600 hover:text-indigo-800">&larr; Back to purchase orders</a>

            <div class="bg-white shadow-sm rounded-lg p-6 space-y-3">
                <div class="flex items-center justify-between">
                    <h3 class="font-medium text-gray-900">{{ $order->poNumber() }}</h3>
                    <span class="text-xs px-2 py-1 rounded-full border bg-gray-50 text-gray-700 border-gray-200">
                        {{ str($order->status)->replace('_', ' ')->headline() }}
                    </span>
                </div>
                <dl class="text-sm text-gray-600 grid grid-cols-2 gap-y-1">
                    <dt class="text-gray-400">Supplier</dt><dd>{{ $order->supplier->name }}</dd>
                    <dt class="text-gray-400">Branch</dt><dd>{{ $order->branch->name }}</dd>
                    @if ($order->expected_date)
                        <dt class="text-gray-400">Expected</dt><dd>{{ $order->expected_date->format('d M Y') }}</dd>
                    @endif
                    @if ($order->notes)
                        <dt class="text-gray-400">Notes</dt><dd>{{ $order->notes }}</dd>
                    @endif
                </dl>
            </div>

            <div class="bg-white shadow-sm rounded-lg p-6">
                <h3 class="font-medium text-gray-900 mb-4">Items</h3>
                <div class="divide-y divide-gray-100 text-sm">
                    @foreach ($order->lines as $line)
                        <div class="py-2 flex justify-between">
                            <span>{{ $line->product->name }}</span>
                            <span class="text-gray-500">{{ $line->quantity_received }} / {{ $line->quantity_ordered }} received &middot; {{ $line->unit_cost }} each</span>
                        </div>
                    @endforeach
                </div>
            </div>

            @can('update', $order)
                <div class="bg-white shadow-sm rounded-lg p-6 flex flex-wrap gap-3">
                    @if ($order->canTransitionTo('ordered'))
                        <form method="POST" action="{{ route('purchase-orders.order', $order) }}">
                            @csrf
                            <x-primary-button>Place order</x-primary-button>
                        </form>
                    @endif
                    @if ($order->canTransitionTo('cancelled'))
                        <form method="POST" action="{{ route('purchase-orders.cancel', $order) }}" onsubmit="return confirm('Cancel this purchase order?')">
                            @csrf
                            <x-danger-button>Cancel order</x-danger-button>
                        </form>
                    @endif
                </div>
            @endcan

            @can('update', $order)
                @if (in_array($order->status, ['ordered', 'partially_received']))
                    <div class="bg-white shadow-sm rounded-lg p-6">
                        <h3 class="font-medium text-gray-900 mb-4">Receive goods</h3>
                        <form method="POST" action="{{ route('purchase-orders.receive', $order) }}" class="space-y-4">
                            @csrf
                            <div class="space-y-3">
                                @foreach ($order->lines as $line)
                                    @continue($line->quantity_received >= $line->quantity_ordered)
                                    <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-4 py-2 border-b border-gray-100 last:border-0">
                                        <input type="hidden" name="lines[{{ $loop->index }}][purchase_order_line_id]" value="{{ $line->id }}">
                                        <div class="w-full sm:w-48 text-sm font-medium text-gray-700">{{ $line->product->name }}</div>
                                        <div class="text-xs text-gray-500 w-full sm:w-32">Outstanding: {{ $line->quantityOutstanding() }}</div>
                                        <input type="number" step="0.001" min="0" max="{{ $line->quantityOutstanding() }}"
                                            name="lines[{{ $loop->index }}][quantity]" placeholder="Quantity received"
                                            class="w-full sm:w-40 border-gray-300 rounded-md shadow-sm text-sm">
                                    </div>
                                @endforeach
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <x-input-label for="supplier_invoice_number" value="Supplier invoice # (optional)" />
                                    <x-text-input id="supplier_invoice_number" class="block mt-1 w-full" type="text" name="supplier_invoice_number" />
                                </div>
                                <div>
                                    <x-input-label for="supplier_invoice_amount" value="Invoice amount (optional)" />
                                    <x-text-input id="supplier_invoice_amount" class="block mt-1 w-full" type="number" step="0.01" min="0" name="supplier_invoice_amount" />
                                </div>
                            </div>

                            <x-input-error :messages="$errors->get('lines')" class="mt-2" />

                            <div class="flex justify-end">
                                <x-primary-button>Record receipt</x-primary-button>
                            </div>
                        </form>
                    </div>
                @endif
            @endcan

            @if ($order->goodsReceipts->isNotEmpty())
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="font-medium text-gray-900 mb-4">Receipt history</h3>
                    <div class="divide-y divide-gray-100 text-sm">
                        @foreach ($order->goodsReceipts as $receipt)
                            <div class="py-2 flex justify-between">
                                <span>{{ $receipt->received_at->format('d M Y, h:i A') }}{{ $receipt->supplier_invoice_number ? ' — Inv# '.$receipt->supplier_invoice_number : '' }}</span>
                                <span>{{ $receipt->receivedBy?->name ?? '—' }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
