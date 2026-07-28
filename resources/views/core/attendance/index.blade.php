<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Attendance Register</h2>

            @if ($branches->isNotEmpty())
                <form method="GET" class="flex items-center gap-2">
                    <select name="branch_id" onchange="this.form.submit()" class="border-gray-300 rounded-md shadow-sm text-sm">
                        @foreach ($branches as $b)
                            <option value="{{ $b->id }}" @selected($branch?->id === $b->id)>{{ $b->name }}</option>
                        @endforeach
                    </select>
                    <input type="date" name="date" value="{{ $date->toDateString() }}" onchange="this.form.submit()" class="border-gray-300 rounded-md shadow-sm text-sm">
                </form>
            @endif
        </div>
    </x-slot>

    <div class="py-8">
        <div class="px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                    {{ session('status') }}
                </div>
            @endif

            @if (! $branch)
                <div class="bg-white shadow-sm rounded-lg p-6 text-sm text-gray-500">
                    You don't have access to any branch yet.
                </div>
            @else
                <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 text-gray-500">
                                <tr>
                                    <th class="text-left px-4 py-2 font-medium">Employee</th>
                                    <th class="text-left px-4 py-2 font-medium">Status</th>
                                    <th class="text-left px-4 py-2 font-medium">Check-in / out</th>
                                    <th class="text-left px-4 py-2 font-medium">Mark</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse ($employees as $employee)
                                    @php $record = $records->get($employee->user_id); @endphp
                                    <tr>
                                        <td class="px-4 py-2">
                                            <div class="font-medium text-gray-800">{{ $employee->user->name }}</div>
                                            <div class="text-xs text-gray-500">{{ $employee->job_title }}</div>
                                        </td>
                                        <td class="px-4 py-2">
                                            @if ($record)
                                                <span @class([
                                                    'text-xs px-2 py-1 rounded-full',
                                                    'bg-green-100 text-green-800' => $record->status === 'present',
                                                    'bg-red-100 text-red-700' => $record->status === 'absent',
                                                    'bg-amber-100 text-amber-800' => $record->status === 'half_day',
                                                    'bg-indigo-100 text-indigo-700' => $record->status === 'on_leave',
                                                ])>
                                                    {{ str($record->status)->replace('_', ' ')->headline() }}
                                                </span>
                                            @else
                                                <span class="text-xs text-gray-400">Not marked</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2 text-gray-500 text-xs">
                                            {{ $record?->check_in_at?->format('h:i A') ?? '—' }}
                                            /
                                            {{ $record?->check_out_at?->format('h:i A') ?? '—' }}
                                        </td>
                                        <td class="px-4 py-2">
                                            <form method="POST" action="{{ route('attendance.mark') }}" class="flex items-center gap-2">
                                                @csrf
                                                <input type="hidden" name="branch_id" value="{{ $branch->id }}">
                                                <input type="hidden" name="user_id" value="{{ $employee->user_id }}">
                                                <input type="hidden" name="date" value="{{ $date->toDateString() }}">
                                                <select name="status" class="border-gray-300 rounded-md shadow-sm text-xs">
                                                    @foreach (\App\Domain\Core\Models\AttendanceRecord::STATUSES as $status)
                                                        <option value="{{ $status }}" @selected($record?->status === $status)>{{ str($status)->replace('_', ' ')->headline() }}</option>
                                                    @endforeach
                                                </select>
                                                <button type="submit" class="text-xs px-3 py-1.5 rounded-md bg-gray-800 text-white hover:bg-gray-700">Save</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-4 py-6 text-center text-gray-500">No employees assigned to this branch.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
