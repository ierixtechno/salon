<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Data Export</h2>
            <form method="POST" action="{{ route('data-exports.store') }}">
                @csrf
                <x-primary-button>Request export</x-primary-button>
            </form>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                    {{ session('status') }}
                </div>
            @endif

            <p class="text-sm text-gray-500 mb-4">
                Exports a ZIP of your customers, appointments, and invoices as CSV files. Each export is available to download for 7 days.
            </p>

            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-gray-500">
                            <tr>
                                <th class="text-left px-4 py-2 font-medium">Requested</th>
                                <th class="text-left px-4 py-2 font-medium">Requested by</th>
                                <th class="text-left px-4 py-2 font-medium">Status</th>
                                <th class="text-left px-4 py-2 font-medium">Expires</th>
                                <th class="text-left px-4 py-2 font-medium"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($exports as $export)
                                <tr>
                                    <td class="px-4 py-2 text-gray-700">{{ $export->created_at->format('d M Y, h:i A') }}</td>
                                    <td class="px-4 py-2 text-gray-500">{{ $export->requestedBy?->name ?? '—' }}</td>
                                    <td class="px-4 py-2">
                                        <span @class([
                                            'text-xs px-2 py-1 rounded-full',
                                            'bg-amber-100 text-amber-800' => in_array($export->status, ['pending', 'processing']),
                                            'bg-green-100 text-green-800' => $export->status === 'completed',
                                            'bg-red-100 text-red-700' => $export->status === 'failed',
                                        ])>
                                            {{ ucfirst($export->status) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 text-gray-500">{{ $export->expires_at?->format('d M Y') ?? '—' }}</td>
                                    <td class="px-4 py-2">
                                        @if ($export->isDownloadable())
                                            <a href="{{ route('data-exports.download', $export) }}" class="text-indigo-600 hover:text-indigo-800 text-xs">Download</a>
                                        @elseif ($export->status === 'completed')
                                            <span class="text-xs text-gray-400">Expired</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-6 text-center text-gray-500">No exports requested yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{ $exports->links() }}
        </div>
    </div>
</x-app-layout>
