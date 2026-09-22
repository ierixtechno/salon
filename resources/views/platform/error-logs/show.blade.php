<x-platform-layout>
    <x-slot name="header">{{ $log->shortClass() }}</x-slot>

    @if (session('status'))
        <div class="mb-4 flex items-center gap-2 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
            {{ session('status') }}
        </div>
    @endif

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 max-w-4xl space-y-5">
        <div class="flex items-start justify-between gap-4">
            <div class="min-w-0">
                <p class="font-mono text-xs text-gray-500 break-all">{{ $log->exception_class }}</p>
                <p class="mt-1 text-gray-900 break-words">{{ $log->message }}</p>
            </div>
            <span class="shrink-0 inline-flex items-center gap-2 px-2.5 py-1 rounded-full text-xs font-medium capitalize {{ $log->isOpen() ? 'bg-red-500/10 text-red-600' : 'bg-green-500/10 text-green-600' }}">
                <span class="h-1.5 w-1.5 rounded-full bg-current"></span>
                {{ $log->status }}
            </span>
        </div>

        <dl class="grid grid-cols-2 sm:grid-cols-3 gap-4 text-sm">
            <div>
                <dt class="text-gray-500">Times it happened</dt>
                <dd class="font-medium text-gray-900">{{ number_format($log->occurrences) }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">First seen</dt>
                <dd class="font-medium text-gray-900">{{ $log->first_seen_at->format('d M Y, H:i') }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Last seen</dt>
                <dd class="font-medium text-gray-900">{{ $log->last_seen_at->format('d M Y, H:i') }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Where (last time)</dt>
                <dd class="font-mono text-xs text-gray-900">
                    @if ($log->path)
                        {{ $log->http_method }} {{ $log->path }}
                    @else
                        {{ $log->context === 'cli' ? 'scheduled / console' : 'unrouted request' }}
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-gray-500">Tenant (last time)</dt>
                <dd class="font-medium text-gray-900">
                    @if ($tenant)
                        <a href="{{ route('platform.tenants.show', $tenant) }}" class="text-indigo-600 hover:text-indigo-800">{{ $tenant->name }}</a>
                    @else
                        {{ $log->tenant_id ? '#'.$log->tenant_id.' (deleted)' : '—' }}
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-gray-500">Reference ID (last time)</dt>
                <dd class="font-mono text-xs text-gray-900 break-all">{{ $log->request_id ?? '—' }}</dd>
            </div>
        </dl>

        <div>
            <p class="text-sm text-gray-500 mb-1">Raised at</p>
            <p class="font-mono text-xs text-gray-800 break-all">{{ $log->file }}:{{ $log->line }}</p>
        </div>

        @if ($log->trace)
            <div>
                <p class="text-sm text-gray-500 mb-1">Call trace (most recent first; arguments are never stored)</p>
                <pre class="bg-gray-50 border border-gray-200 rounded-lg p-3 text-xs font-mono text-gray-700 overflow-x-auto">{{ $log->trace }}</pre>
            </div>
        @endif

        <div class="flex items-center justify-between pt-4 border-t border-gray-100">
            <a href="{{ route('platform.error-logs.index') }}" class="text-sm text-gray-600 hover:text-gray-900">&larr; Back to error log</a>

            @if ($log->isOpen())
                <form method="POST" action="{{ route('platform.error-logs.resolve', $log) }}">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="inline-flex items-center rounded-lg bg-green-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-green-500 transition">
                        Mark as resolved
                    </button>
                </form>
            @else
                <div class="flex items-center gap-4">
                    <span class="text-xs text-gray-500">
                        Resolved {{ $log->resolved_at?->diffForHumans() }}@if ($log->resolvedBy) by {{ $log->resolvedBy->name }}@endif
                    </span>
                    <form method="POST" action="{{ route('platform.error-logs.reopen', $log) }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="inline-flex items-center rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                            Reopen
                        </button>
                    </form>
                </div>
            @endif
        </div>
    </div>
</x-platform-layout>
