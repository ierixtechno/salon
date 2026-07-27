<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Gift Cards</h2>
            @can('gift-cards.create')
                <a href="{{ route('gift-cards.create') }}"
                    class="inline-flex items-center px-4 py-2 bg-gray-800 text-white text-sm rounded-md hover:bg-gray-700">
                    + Issue gift card
                </a>
            @endcan
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
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
                                <th class="text-left px-4 py-2 font-medium">Code</th>
                                <th class="text-left px-4 py-2 font-medium">Customer</th>
                                <th class="text-left px-4 py-2 font-medium">Initial value</th>
                                <th class="text-left px-4 py-2 font-medium">Balance</th>
                                <th class="text-left px-4 py-2 font-medium">Expires</th>
                                <th class="text-left px-4 py-2 font-medium">Status</th>
                                <th class="px-4 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($giftCards as $card)
                                <tr>
                                    <td class="px-4 py-2 font-mono">{{ $card->code }}</td>
                                    <td class="px-4 py-2 text-gray-500">{{ $card->customer?->name ?? '—' }}</td>
                                    <td class="px-4 py-2">{{ $card->initial_value }}</td>
                                    <td class="px-4 py-2">{{ $card->balance() }}</td>
                                    <td class="px-4 py-2 text-gray-500">{{ $card->expires_at?->toFormattedDateString() ?? '—' }}</td>
                                    <td class="px-4 py-2">
                                        <span class="text-xs px-2 py-1 rounded-full {{ $card->status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                                            {{ ucfirst($card->status) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 text-right">
                                        @can('update', $card)
                                            @if ($card->status === 'active')
                                                <form method="POST" action="{{ route('gift-cards.cancel', $card) }}" onsubmit="return confirm('Cancel this gift card and write off its remaining balance?');">
                                                    @csrf
                                                    <button type="submit" class="text-red-600 hover:text-red-800 text-sm">Cancel</button>
                                                </form>
                                            @endif
                                        @endcan
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-6 text-center text-gray-500">No gift cards issued yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="p-4">{{ $giftCards->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
