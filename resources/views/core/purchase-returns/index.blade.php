<x-app-layout>
    <x-slot name="header">Purchase Returns</x-slot>

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

                @can('create', \App\Domain\Core\Models\PurchaseOrder::class)
                    <a href="{{ route('purchase-returns.create', ['branch_id' => $branch?->id]) }}" class="sm:ml-auto">
                        <x-primary-button>New return</x-primary-button>
                    </a>
                @endcan
            </div>

            <div class="bg-white shadow-sm rounded-lg divide-y divide-gray-100">
                @forelse ($returns as $return)
                    <div class="p-4">
                        <div class="flex items-center justify-between">
                            <span class="font-medium text-gray-900">{{ $return->supplier->name }}</span>
                            <span class="text-xs text-gray-500">{{ $return->created_at->format('d M Y') }}</span>
                        </div>
                        @if ($return->reason)
                            <p class="text-sm text-gray-500 mt-1">{{ $return->reason }}</p>
                        @endif
                        <div class="text-xs text-gray-500 mt-2">
                            @foreach ($return->movements as $movement)
                                {{ $movement->product->name ?? '' }} ({{ abs($movement->quantity) }})@if(!$loop->last), @endif
                            @endforeach
                        </div>
                    </div>
                @empty
                    <p class="p-6 text-sm text-gray-500">No returns recorded yet.</p>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
