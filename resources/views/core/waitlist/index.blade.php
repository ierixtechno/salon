<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Waitlist</h2>
            @can('create', App\Domain\Core\Models\WaitlistEntry::class)
                <a href="{{ route('waitlist.create') }}"
                    class="inline-flex items-center px-4 py-2 bg-gray-800 text-white text-sm rounded-md hover:bg-gray-700">
                    + Add to waitlist
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

            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-gray-500">
                            <tr>
                                <th class="text-left px-4 py-2 font-medium">Customer</th>
                                <th class="text-left px-4 py-2 font-medium">Branch</th>
                                <th class="text-left px-4 py-2 font-medium">Service</th>
                                <th class="text-left px-4 py-2 font-medium">Preferred date</th>
                                <th class="text-left px-4 py-2 font-medium">Status</th>
                                <th class="px-4 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($entries as $entry)
                                <tr>
                                    <td class="px-4 py-2 font-medium text-gray-900">{{ $entry->customer->name }}</td>
                                    <td class="px-4 py-2 text-gray-500">{{ $entry->branch->name }}</td>
                                    <td class="px-4 py-2 text-gray-500">{{ $entry->service->name }}</td>
                                    <td class="px-4 py-2 text-gray-500">{{ optional($entry->preferred_date)->toFormattedDateString() ?? '—' }}</td>
                                    <td class="px-4 py-2">
                                        <span class="text-xs px-2 py-1 rounded-full bg-amber-100 text-amber-800">{{ str($entry->status)->headline() }}</span>
                                    </td>
                                    <td class="px-4 py-2 text-right space-x-3 whitespace-nowrap">
                                        @can('update', $entry)
                                            <form method="POST" action="{{ route('waitlist.book', $entry) }}" class="inline">
                                                @csrf
                                                <button type="submit" class="text-indigo-600 hover:text-indigo-800">Book</button>
                                            </form>
                                            <a href="{{ route('waitlist.edit', $entry) }}" class="text-gray-500 hover:text-gray-700">Edit</a>
                                        @endcan
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-6 text-center text-gray-500">Nobody on the waitlist.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
