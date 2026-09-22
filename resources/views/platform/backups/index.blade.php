<x-platform-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <span>Backups</span>
            <form method="POST" action="{{ route('platform.backups.run') }}">
                @csrf
                <button type="submit" class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition">
                    Run backup now
                </button>
            </form>
        </div>
    </x-slot>

    @if (session('status'))
        <div class="mb-4 flex items-center gap-2 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
            {{ session('status') }}
        </div>
    @endif

    @if ($isStale)
        <div class="mb-4 rounded-lg bg-red-600 px-4 py-3 text-sm text-white">
            <p class="font-semibold">
                @if ($lastSuccess)
                    No successful backup in over {{ $staleAfterHours }} hours — the last one was {{ $lastSuccess->finished_at->diffForHumans() }}.
                @else
                    No successful backup has been made yet.
                @endif
            </p>
            <p class="mt-1 text-red-100">
                Backups should run every night at 02:00. If they aren't, the server's cron job (<code class="font-mono">php artisan schedule:run</code>, every minute) is most likely not running — see the deployment guide. You can run one right now with the button above.
            </p>
        </div>
    @elseif ($lastSuccess)
        <div class="mb-4 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
            Last successful backup: <span class="font-semibold">{{ $lastSuccess->finished_at->format('d M Y, H:i') }}</span>
            ({{ $lastSuccess->finished_at->diffForHumans() }}) &middot; {{ human_file_size($lastSuccess->size_bytes) }}
        </div>
    @endif

    <div class="mb-4 rounded-lg bg-white border border-gray-100 shadow-sm px-4 py-3 text-sm text-gray-600 space-y-1">
        <p>
            A full backup (database + uploaded files) is made automatically every night at 02:00 and kept for {{ $retentionDays }} days
            (the newest {{ config('backup.always_keep_latest') }} are always kept regardless).
        </p>
        <p>
            <span class="font-medium text-gray-800">They are stored on this same server.</span>
            That protects against a bad update or an accidental deletion, but not against losing the server itself —
            download one regularly and keep it somewhere else (your computer, Google Drive, etc.).
            Every archive contains all tenants' data, so treat downloads accordingly.
        </p>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-100">
                    <tr>
                        <th class="text-left px-5 py-3 font-semibold uppercase text-xs tracking-wider text-slate-600">Started</th>
                        <th class="text-left px-5 py-3 font-semibold uppercase text-xs tracking-wider text-slate-600">Type</th>
                        <th class="text-left px-5 py-3 font-semibold uppercase text-xs tracking-wider text-slate-600">Result</th>
                        <th class="text-left px-5 py-3 font-semibold uppercase text-xs tracking-wider text-slate-600">Size</th>
                        <th class="text-left px-5 py-3 font-semibold uppercase text-xs tracking-wider text-slate-600">Contents</th>
                        <th class="text-left px-5 py-3 font-semibold uppercase text-xs tracking-wider text-slate-600">Took</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($runs as $run)
                        <tr class="hover:bg-gray-50 align-top">
                            <td class="px-5 py-3 text-gray-700 whitespace-nowrap">{{ $run->started_at->format('d M Y, H:i') }}</td>
                            <td class="px-5 py-3 text-gray-600 capitalize">
                                {{ $run->trigger }}
                                @if ($run->platformAdmin)
                                    <span class="block text-xs text-gray-400">{{ $run->platformAdmin->name }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-3">
                                @php
                                    $styles = [
                                        'success' => 'bg-green-500/10 text-green-600',
                                        'failed' => 'bg-red-500/10 text-red-600',
                                        'running' => 'bg-amber-500/10 text-amber-600',
                                    ];
                                @endphp
                                <span class="inline-flex items-center gap-2 px-2.5 py-1 rounded-full text-xs font-medium capitalize {{ $styles[$run->status] ?? $styles['running'] }}">
                                    <span class="h-1.5 w-1.5 rounded-full bg-current"></span>
                                    {{ $run->status }}
                                </span>
                                @if ($run->status === 'failed' && $run->error_message)
                                    <p class="mt-1 text-xs text-red-600 max-w-xs">{{ $run->error_message }}</p>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-gray-600 whitespace-nowrap">{{ $run->size_bytes ? human_file_size($run->size_bytes) : '—' }}</td>
                            <td class="px-5 py-3 text-gray-500 whitespace-nowrap">
                                @if ($run->succeeded())
                                    {{ $run->table_count }} tables, {{ number_format($run->row_count) }} rows
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-5 py-3 text-gray-500 whitespace-nowrap">{{ $run->durationSeconds() !== null ? $run->durationSeconds().'s' : '—' }}</td>
                            <td class="px-5 py-3 text-right whitespace-nowrap">
                                @if ($run->succeeded())
                                    @if ($run->fileExists())
                                        <a href="{{ route('platform.backups.download', $run) }}" class="text-indigo-600 hover:text-indigo-800 font-medium">Download</a>
                                    @else
                                        <span class="text-xs text-gray-400">removed (older than {{ $retentionDays }} days)</span>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-10 text-center text-gray-400">No backups yet — the first one runs tonight at 02:00, or use "Run backup now".</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-5 py-4 border-t border-gray-100">{{ $runs->links() }}</div>
    </div>
</x-platform-layout>
