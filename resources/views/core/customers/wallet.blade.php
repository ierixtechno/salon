<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $customer->name }} &mdash; Wallet</h2>
    </x-slot>

    <div class="py-8">
        <div class="px-4 sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                    {{ session('status') }}
                </div>
            @endif

            <div class="bg-white shadow-sm rounded-lg p-6">
                <p class="text-sm text-gray-500">Current balance</p>
                <p class="text-3xl font-semibold text-gray-900">{{ $balance }}</p>
            </div>

            @can('wallet.credit')
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="font-medium text-gray-900 mb-4">Add credit</h3>
                    <form method="POST" action="{{ route('customers.wallet.credit', $customer) }}" class="flex flex-col sm:flex-row gap-3 sm:items-end">
                        @csrf
                        <div class="flex-1">
                            <x-input-label for="amount" value="Amount" />
                            <x-text-input id="amount" class="block mt-1 w-full" type="number" step="0.01" min="0.01" name="amount" required />
                            <x-input-error :messages="$errors->get('amount')" class="mt-2" />
                        </div>
                        <div class="flex-1">
                            <x-input-label for="reason" value="Reason (optional)" />
                            <x-text-input id="reason" class="block mt-1 w-full" type="text" name="reason" />
                        </div>
                        <x-primary-button>Add credit</x-primary-button>
                    </form>
                </div>
            @endcan

            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-indigo-100 text-indigo-800">
                            <tr>
                                <th class="text-left px-4 py-2 font-semibold text-base">Date</th>
                                <th class="text-left px-4 py-2 font-semibold text-base">Type</th>
                                <th class="text-left px-4 py-2 font-semibold text-base">Amount</th>
                                <th class="text-left px-4 py-2 font-semibold text-base">Reason</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($transactions as $txn)
                                <tr>
                                    <td class="px-4 py-2 text-gray-500">{{ $txn->created_at->format('d M Y, h:i A') }}</td>
                                    <td class="px-4 py-2">{{ ucfirst($txn->type) }}</td>
                                    <td class="px-4 py-2 {{ $txn->amount >= 0 ? 'text-green-700' : 'text-red-700' }}">{{ $txn->amount >= 0 ? '+' : '' }}{{ $txn->amount }}</td>
                                    <td class="px-4 py-2 text-gray-500">{{ $txn->reason ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-6 text-center text-gray-500">No wallet activity yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
