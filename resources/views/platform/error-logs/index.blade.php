<x-platform-layout>
    <x-slot name="header">Error log</x-slot>

    @if (session('status'))
        <div class="mb-4 flex items-center gap-2 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
            {{ session('status') }}
        </div>
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-5">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
            <p class="text-xs uppercase tracking-wider text-gray-500">Open errors</p>
            <p class="mt-1 text-2xl font-semibold {{ $openCount > 0 ? 'text-red-600' : 'text-green-600' }}">{{ number_format($openCount) }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
            <p class="text-xs uppercase tracking-wider text-gray-500">New in the last 24 hours</p>
            <p class="mt-1 text-2xl font-semibold {{ $newLast24h > 0 ? 'text-amber-600' : 'text-gray-900' }}">{{ number_format($newLast24h) }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
            <p class="text-xs uppercase tracking-wider text-gray-500">Resolved</p>
            <p class="mt-1 text-2xl font-semibold text-gray-900">{{ number_format($resolvedCount) }}</p>
        </div>
    </div>

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
        <div class="inline-flex rounded-lg border border-gray-200 bg-white p-0.5 text-sm">
            @foreach (['open' => 'Open', 'resolved' => 'Resolved', 'all' => 'All'] as $key => $label)
                <a href="{{ route('platform.error-logs.index', array_filter(['status' => $key, 'q' => $search])) }}"
                    class="px-3 py-1.5 rounded-md {{ $status === $key ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:bg-gray-50' }}">{{ $label }}</a>
            @endforeach
        </div>

        <form method="GET" action="{{ route('platform.error-logs.index') }}" class="flex gap-2">
            <input type="hidden" name="status" value="{{ $status }}">
            <input type="text" name="q" value="{{ $search }}" placeholder="Search message, class, path or reference ID"
                class="w-full sm:w-80 rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
            <button type="submit" class="rounded-lg bg-gray-800 px-3 py-2 text-sm font-medium text-white hover:bg-gray-700">Search</button>
        </form>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-100">
                    <tr>
                        <th class="text-left px-5 py-3 font-semibold uppercase text-xs tracking-wider text-slate-600">Last seen</th>
                        <th class="text-left px-5 py-3 font-semibold uppercase text-xs tracking-wider text-slate-600">Error</th>
                        <th class="text-left px-5 py-3 font-semibold uppercase text-xs tracking-wider text-slate-600">Where</th>
                        <th class="text-left px-5 py-3 font-semibold uppercase text-xs tracking-wider text-slate-600">Times</th>
                        <th class="text-left px-5 py-3 font-semibold uppercase text-xs tracking-wider text-slate-600">Tenant</th>
                        <th class="text-left px-5 py-3 font-semibold uppercase text-xs tracking-wider text-slate-600">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($logs as $log)
                        <tr class="hover:bg-gray-50 align-top">
                            <td class="px-5 py-3 text-gray-500 whitespace-nowrap">{{ $log->last_seen_at->diffForHumans() }}</td>
                            <td class="px-5 py-3 max-w-md">
                                <a href="{{ route('platform.error-logs.show', $log) }}" class="font-medium text-gray-900 hover:text-indigo-600">{{ $log->shortClass() }}</a>
                                <p class="text-gray-500 text-xs mt-0.5 break-words">{{ \Illuminate\Support\Str::limit($log->message, 160) }}</p>
                            </td>
                            <td class="px-5 py-3 text-gray-600 text-xs font-mono">
                                @if ($log->path)
                                    {{ $log->http_method }} {{ $log->path }}
                                @else
                                    {{ $log->context === 'cli' ? 'scheduled / console' : 'unrouted request' }}
                                @endif
                            </td>
                            <td class="px-5 py-3 text-gray-700">{{ number_format($log->occurrences) }}</td>
                            <td class="px-5 py-3 text-gray-600">{{ $log->tenant_id ? ($tenantNames[$log->tenant_id] ?? '#'.$log->tenant_id) : '—' }}</td>
                            <td class="px-5 py-3">
                                <span class="inline-flex items-center gap-2 px-2.5 py-1 rounded-full text-xs font-medium capitalize {{ $log->isOpen() ? 'bg-red-500/10 text-red-600' : 'bg-green-500/10 text-green-600' }}">
                                    <span class="h-1.5 w-1.5 rounded-full bg-current"></span>
                                    {{ $log->status }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-10 text-center text-gray-400">
                                {{ $status === 'open' && $search === '' ? 'No open errors. 🎉' : 'Nothing matches.' }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-5 py-4 border-t border-gray-100">{{ $logs->links() }}</div>
    </div>

    <p class="mt-4 text-xs text-gray-400">
        Errors repeat as one row with a counter. Anything marked resolved reopens by itself if it happens again.
        A summary is emailed to Super Admin each morning (only on days there is something to report).
    </p>
</x-platform-layout>
