<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Cash Register</h2>
            @if ($branches->isNotEmpty())
                <form method="GET" class="flex items-center gap-2">
                    <select name="branch_id" onchange="this.form.submit()" class="border-gray-300 rounded-md shadow-sm text-sm">
                        @foreach ($branches as $b)
                            <option value="{{ $b->id }}" @selected($branch?->id === $b->id)>{{ $b->name }}</option>
                        @endforeach
                    </select>
                </form>
            @endif
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                    {{ session('status') }}
                </div>
            @endif

            @if (! $branch)
                <div class="bg-white shadow-sm rounded-lg p-6 text-sm text-gray-500">
                    You don't have access to any branch yet.
                </div>
            @elseif (! $session)
                @can('cash-register.manage')
                    <div class="bg-white shadow-sm rounded-lg p-6">
                        <h3 class="font-medium text-gray-900 mb-4">Open cash register — {{ $branch->name }}</h3>
                        <form method="POST" action="{{ route('cash-register.open') }}" class="space-y-4">
                            @csrf
                            <input type="hidden" name="branch_id" value="{{ $branch->id }}">
                            <div>
                                <x-input-label for="opening_cash" value="Opening cash" />
                                <x-text-input id="opening_cash" class="block mt-1 w-full" type="number" step="0.01" min="0" name="opening_cash" value="0" required />
                                <x-input-error :messages="$errors->get('opening_cash')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="notes" value="Notes (optional)" />
                                <textarea id="notes" name="notes" rows="2" class="block mt-1 w-full border-gray-300 rounded-md shadow-sm"></textarea>
                            </div>
                            <div class="flex justify-end">
                                <x-primary-button>Open register</x-primary-button>
                            </div>
                        </form>
                    </div>
                @else
                    <div class="bg-white shadow-sm rounded-lg p-6 text-sm text-gray-500">
                        No open cash register session for {{ $branch->name }}.
                    </div>
                @endcan
            @else
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-medium text-gray-900">{{ $branch->name }} — open since {{ $session->opened_at->format('d M, h:i A') }}</h3>
                        <span class="text-xs px-2 py-1 rounded-full bg-green-100 text-green-800">Open</span>
                    </div>
                    <dl class="text-sm text-gray-600 grid grid-cols-2 gap-y-1">
                        <dt class="text-gray-400">Opening cash</dt><dd>₹{{ number_format($session->opening_cash, 2) }}</dd>
                        <dt class="text-gray-400">Running total</dt><dd class="font-semibold text-gray-900">₹{{ number_format($session->runningTotal(), 2) }}</dd>
                        <dt class="text-gray-400">Opened by</dt><dd>{{ $session->openedBy?->name ?? '—' }}</dd>
                    </dl>
                </div>

                @can('cash-register.manage')
                    <div class="bg-white shadow-sm rounded-lg p-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <form method="POST" action="{{ route('cash-register.cash-in', $session) }}" class="space-y-2">
                            @csrf
                            <x-input-label value="Cash in" />
                            <x-text-input class="block w-full text-sm" type="number" step="0.01" min="0.01" name="amount" placeholder="Amount" required />
                            <x-text-input class="block w-full text-sm" type="text" name="reason" placeholder="Reason" required />
                            <x-secondary-button type="submit" class="w-full justify-center">Add cash</x-secondary-button>
                        </form>

                        <form method="POST" action="{{ route('cash-register.cash-out', $session) }}" class="space-y-2">
                            @csrf
                            <x-input-label value="Cash out" />
                            <x-text-input class="block w-full text-sm" type="number" step="0.01" min="0.01" name="amount" placeholder="Amount" required />
                            <x-text-input class="block w-full text-sm" type="text" name="reason" placeholder="Reason" required />
                            <x-secondary-button type="submit" class="w-full justify-center">Remove cash</x-secondary-button>
                        </form>
                    </div>

                    <div class="bg-white shadow-sm rounded-lg p-6 border border-red-200">
                        <h3 class="font-medium text-red-900 mb-4">Close register</h3>
                        <form method="POST" action="{{ route('cash-register.close', $session) }}" class="space-y-4" onsubmit="return confirm('Close this cash register session?')">
                            @csrf
                            <div>
                                <x-input-label for="actual_closing" value="Actual cash counted" />
                                <x-text-input id="actual_closing" class="block mt-1 w-full" type="number" step="0.01" min="0" name="actual_closing" required />
                                <x-input-error :messages="$errors->get('actual_closing')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="close_notes" value="Notes (optional)" />
                                <textarea id="close_notes" name="notes" rows="2" class="block mt-1 w-full border-gray-300 rounded-md shadow-sm"></textarea>
                            </div>
                            <div class="flex justify-end">
                                <x-danger-button>Close register</x-danger-button>
                            </div>
                        </form>
                    </div>
                @endcan

                <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100">
                        <h3 class="font-medium text-gray-900">Movements</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 text-gray-500">
                                <tr>
                                    <th class="text-left px-4 py-2 font-medium">Time</th>
                                    <th class="text-left px-4 py-2 font-medium">Type</th>
                                    <th class="text-left px-4 py-2 font-medium">Reason</th>
                                    <th class="text-left px-4 py-2 font-medium">Amount</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse ($session->cashMovements->sortByDesc('created_at') as $movement)
                                    <tr>
                                        <td class="px-4 py-2 text-gray-500">{{ $movement->created_at->format('h:i A') }}</td>
                                        <td class="px-4 py-2 text-gray-700">{{ str($movement->type)->replace('_', ' ')->headline() }}</td>
                                        <td class="px-4 py-2 text-gray-500">{{ $movement->reason ?? '—' }}</td>
                                        <td class="px-4 py-2 {{ $movement->amount < 0 ? 'text-red-600' : 'text-gray-800' }}">
                                            ₹{{ number_format($movement->amount, 2) }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-4 py-6 text-center text-gray-500">No movements yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            <a href="{{ route('cash-register.history') }}" class="inline-block text-sm text-gray-500 hover:text-gray-700">View past sessions &rarr;</a>
        </div>
    </div>
</x-app-layout>
