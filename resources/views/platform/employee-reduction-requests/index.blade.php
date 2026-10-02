<x-platform-layout>
    <x-slot name="header">Employee Reduction Requests</x-slot>

    @if (session('status'))
        <div class="mb-4 flex items-center gap-2 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
            {{ session('status') }}
        </div>
    @endif

    @php
        $statusStyles = [
            'pending' => 'bg-amber-500/10 text-amber-600',
            'approved' => 'bg-green-500/10 text-green-600',
            'rejected' => 'bg-red-500/10 text-red-600',
            'cancelled' => 'bg-gray-500/10 text-gray-500',
        ];
    @endphp

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-100">
                    <tr>
                        <th class="text-left px-5 py-3 font-semibold uppercase text-xs tracking-wider text-slate-600">Tenant</th>
                        <th class="text-left px-5 py-3 font-semibold uppercase text-xs tracking-wider text-slate-600">Requested by</th>
                        <th class="text-left px-5 py-3 font-semibold uppercase text-xs tracking-wider text-slate-600">Change</th>
                        <th class="text-left px-5 py-3 font-semibold uppercase text-xs tracking-wider text-slate-600">Reason</th>
                        <th class="text-left px-5 py-3 font-semibold uppercase text-xs tracking-wider text-slate-600">Status</th>
                        <th class="text-left px-5 py-3 font-semibold uppercase text-xs tracking-wider text-slate-600">Requested</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($requests as $req)
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-3">
                                <a href="{{ route('platform.tenants.show', $req->tenant) }}" class="font-medium text-gray-900 hover:text-indigo-600">
                                    {{ $req->tenant->name }}
                                </a>
                            </td>
                            <td class="px-5 py-3 text-gray-600">{{ $req->requestedBy->name }}</td>
                            <td class="px-5 py-3 text-gray-600">{{ $req->current_extra_user_count }} &rarr; {{ $req->requested_extra_user_count }} purchased slots</td>
                            <td class="px-5 py-3 text-gray-500 max-w-xs truncate" title="{{ $req->reason }}">{{ $req->reason ?: '—' }}</td>
                            <td class="px-5 py-3">
                                <span class="inline-flex items-center gap-2 px-2.5 py-1 rounded-full text-xs font-medium capitalize {{ $statusStyles[$req->status] ?? $statusStyles['cancelled'] }}">
                                    <span class="h-1.5 w-1.5 rounded-full bg-current"></span>
                                    {{ $req->status }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-gray-500">{{ $req->created_at->diffForHumans() }}</td>
                            <td class="px-5 py-3 text-right whitespace-nowrap">
                                @if ($req->status === 'pending')
                                    <form method="POST" action="{{ route('platform.employee-reduction-requests.approve', $req) }}" class="inline" onsubmit="return confirm('Approve — this tenant\'s subscription will cover {{ $req->requested_extra_user_count }} purchased employee slots from its next renewal.')">
                                        @csrf
                                        <button type="submit" class="text-green-700 hover:text-green-900 font-medium mr-3">Approve</button>
                                    </form>
                                    <button type="button" onclick="document.getElementById('reject-{{ $req->id }}').classList.toggle('hidden')" class="text-red-600 hover:text-red-800 font-medium">Reject</button>
                                    <form id="reject-{{ $req->id }}" method="POST" action="{{ route('platform.employee-reduction-requests.reject', $req) }}" class="hidden mt-2 flex items-center gap-2">
                                        @csrf
                                        <input type="text" name="reason" placeholder="Reason (optional)" class="border-gray-300 rounded-md shadow-sm text-xs">
                                        <button type="submit" class="text-red-600 hover:text-red-800 text-xs font-medium">Confirm reject</button>
                                    </form>
                                @else
                                    <span class="text-xs text-gray-400">{{ $req->decidedBy?->name ?? '—' }} · {{ $req->decided_at?->diffForHumans() }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-10 text-center text-gray-400">No employee reduction requests yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-5 py-4 border-t border-gray-100">{{ $requests->links() }}</div>
    </div>
</x-platform-layout>
