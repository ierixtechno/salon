<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $customer->name }} &mdash; Loyalty</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white shadow-sm rounded-lg p-6">
                <p class="text-sm text-gray-500">Current points balance</p>
                <p class="text-3xl font-semibold text-gray-900">{{ $balance }}</p>
            </div>

            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-gray-500">
                            <tr>
                                <th class="text-left px-4 py-2 font-medium">Date</th>
                                <th class="text-left px-4 py-2 font-medium">Type</th>
                                <th class="text-left px-4 py-2 font-medium">Points</th>
                                <th class="text-left px-4 py-2 font-medium">Reason</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($entries as $entry)
                                <tr>
                                    <td class="px-4 py-2 text-gray-500">{{ $entry->created_at->format('d M Y, h:i A') }}</td>
                                    <td class="px-4 py-2">{{ ucfirst($entry->type) }}</td>
                                    <td class="px-4 py-2 {{ $entry->points >= 0 ? 'text-green-700' : 'text-red-700' }}">{{ $entry->points >= 0 ? '+' : '' }}{{ $entry->points }}</td>
                                    <td class="px-4 py-2 text-gray-500">{{ $entry->reason ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-6 text-center text-gray-500">No loyalty activity yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
