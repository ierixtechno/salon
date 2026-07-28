<x-app-layout>
    <x-slot name="header">Leave Requests</x-slot>

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
                        <thead class="bg-indigo-100 text-indigo-800">
                            <tr>
                                <th class="text-left px-4 py-2 font-semibold text-base">Employee</th>
                                <th class="text-left px-4 py-2 font-semibold text-base">Type</th>
                                <th class="text-left px-4 py-2 font-semibold text-base">Dates</th>
                                <th class="text-left px-4 py-2 font-semibold text-base">Days</th>
                                <th class="text-left px-4 py-2 font-semibold text-base">Status</th>
                                <th class="text-left px-4 py-2 font-semibold text-base">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($leaveRequests as $leaveRequest)
                                <tr>
                                    <td class="px-4 py-2">
                                        <div class="font-medium text-gray-800">{{ $leaveRequest->user->name }}</div>
                                        @if ($leaveRequest->reason)
                                            <div class="text-xs text-gray-500">{{ $leaveRequest->reason }}</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 text-gray-500">{{ $leaveRequest->leaveType->name }}</td>
                                    <td class="px-4 py-2 text-gray-500">{{ $leaveRequest->start_date->format('d M Y') }} – {{ $leaveRequest->end_date->format('d M Y') }}</td>
                                    <td class="px-4 py-2 text-gray-500">{{ $leaveRequest->days }}</td>
                                    <td class="px-4 py-2">
                                        <span @class([
                                            'text-xs px-2 py-1 rounded-full',
                                            'bg-amber-100 text-amber-800' => $leaveRequest->status === 'pending',
                                            'bg-green-100 text-green-800' => $leaveRequest->status === 'approved',
                                            'bg-red-100 text-red-700' => $leaveRequest->status === 'rejected',
                                            'bg-gray-100 text-gray-600' => $leaveRequest->status === 'cancelled',
                                        ])>
                                            {{ str($leaveRequest->status)->headline() }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2">
                                        @can('leave.approve')
                                            @if ($leaveRequest->status === 'pending')
                                                <div class="flex items-center gap-2">
                                                    <form method="POST" action="{{ route('leave.approve', $leaveRequest) }}">
                                                        @csrf
                                                        <button type="submit" class="text-xs px-3 py-1.5 rounded-md bg-green-600 text-white hover:bg-green-500">Approve</button>
                                                    </form>
                                                    <form method="POST" action="{{ route('leave.reject', $leaveRequest) }}" onsubmit="return confirm('Reject this leave request?')">
                                                        @csrf
                                                        <button type="submit" class="text-xs px-3 py-1.5 rounded-md bg-red-600 text-white hover:bg-red-500">Reject</button>
                                                    </form>
                                                </div>
                                            @elseif ($leaveRequest->status === 'approved')
                                                <form method="POST" action="{{ route('leave.cancel', $leaveRequest) }}" onsubmit="return confirm('Cancel this approved leave?')">
                                                    @csrf
                                                    <button type="submit" class="text-xs px-3 py-1.5 rounded-md bg-gray-700 text-white hover:bg-gray-600">Cancel</button>
                                                </form>
                                            @endif
                                        @endcan
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-6 text-center text-gray-500">No leave requests yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
