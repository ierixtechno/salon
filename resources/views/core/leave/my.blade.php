<x-app-layout>
    <x-slot name="header">My Leave</x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                    {{ session('status') }}
                </div>
            @endif

            <div class="bg-white shadow-sm rounded-lg p-6">
                <h3 class="font-medium text-gray-900 mb-4">Request leave</h3>

                @if ($leaveTypes->isEmpty())
                    <p class="text-sm text-gray-500">No leave types configured yet — ask your admin to set one up.</p>
                @else
                    <form method="POST" action="{{ route('leave.store') }}" class="space-y-4">
                        @csrf

                        <div>
                            <x-input-label for="leave_type_id" value="Leave type" />
                            <select id="leave_type_id" name="leave_type_id" required class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">
                                @foreach ($leaveTypes as $leaveType)
                                    <option value="{{ $leaveType->id }}" @selected(old('leave_type_id') == $leaveType->id)>{{ $leaveType->name }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('leave_type_id')" class="mt-2" />
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="start_date" value="Start date" />
                                <x-text-input id="start_date" class="block mt-1 w-full" type="date" name="start_date" :value="old('start_date')" required />
                                <x-input-error :messages="$errors->get('start_date')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="end_date" value="End date" />
                                <x-text-input id="end_date" class="block mt-1 w-full" type="date" name="end_date" :value="old('end_date')" required />
                                <x-input-error :messages="$errors->get('end_date')" class="mt-2" />
                            </div>
                        </div>

                        <div>
                            <x-input-label for="reason" value="Reason (optional)" />
                            <textarea id="reason" name="reason" rows="3" class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">{{ old('reason') }}</textarea>
                            <x-input-error :messages="$errors->get('reason')" class="mt-2" />
                        </div>

                        <div class="flex justify-end">
                            <x-primary-button>Submit request</x-primary-button>
                        </div>
                    </form>
                @endif
            </div>

            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-gray-500">
                            <tr>
                                <th class="text-left px-4 py-2 font-medium">Type</th>
                                <th class="text-left px-4 py-2 font-medium">Dates</th>
                                <th class="text-left px-4 py-2 font-medium">Days</th>
                                <th class="text-left px-4 py-2 font-medium">Status</th>
                                <th class="text-left px-4 py-2 font-medium"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($leaveRequests as $leaveRequest)
                                <tr>
                                    <td class="px-4 py-2 text-gray-700">{{ $leaveRequest->leaveType->name }}</td>
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
                                        @if (in_array($leaveRequest->status, ['pending', 'approved']))
                                            <form method="POST" action="{{ route('leave.cancel', $leaveRequest) }}" onsubmit="return confirm('Cancel this leave request?')">
                                                @csrf
                                                <button type="submit" class="text-xs text-gray-500 hover:text-gray-700 underline">Cancel</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-6 text-center text-gray-500">No leave requests yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
