<x-app-layout>
    <x-slot name="header">My Attendance</x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                    {{ session('status') }}
                </div>
            @endif

            @can('attendance.clock-self')
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="font-medium text-gray-900 mb-4">Clock in / out</h3>

                    @if ($accessibleBranches->isEmpty())
                        <p class="text-sm text-gray-500">You don't have access to any branch yet.</p>
                    @else
                        <div class="flex flex-col sm:flex-row gap-3">
                            <form method="POST" action="{{ route('attendance.clock-in') }}" class="flex items-center gap-2">
                                @csrf
                                <select name="branch_id" class="border-gray-300 rounded-md shadow-sm text-sm">
                                    @foreach ($accessibleBranches as $b)
                                        <option value="{{ $b->id }}">{{ $b->name }}</option>
                                    @endforeach
                                </select>
                                <x-primary-button>Clock in</x-primary-button>
                            </form>

                            <form method="POST" action="{{ route('attendance.clock-out') }}" class="flex items-center gap-2">
                                @csrf
                                <select name="branch_id" class="border-gray-300 rounded-md shadow-sm text-sm">
                                    @foreach ($accessibleBranches as $b)
                                        <option value="{{ $b->id }}">{{ $b->name }}</option>
                                    @endforeach
                                </select>
                                <x-secondary-button>Clock out</x-secondary-button>
                            </form>
                        </div>
                    @endif
                </div>
            @endcan

            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-gray-500">
                            <tr>
                                <th class="text-left px-4 py-2 font-medium">Date</th>
                                <th class="text-left px-4 py-2 font-medium">Status</th>
                                <th class="text-left px-4 py-2 font-medium">Check-in</th>
                                <th class="text-left px-4 py-2 font-medium">Check-out</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($records as $record)
                                <tr>
                                    <td class="px-4 py-2 text-gray-700">{{ $record->date->format('d M Y') }}</td>
                                    <td class="px-4 py-2 text-gray-500">{{ str($record->status)->replace('_', ' ')->headline() }}</td>
                                    <td class="px-4 py-2 text-gray-500">{{ $record->check_in_at?->format('h:i A') ?? '—' }}</td>
                                    <td class="px-4 py-2 text-gray-500">{{ $record->check_out_at?->format('h:i A') ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-6 text-center text-gray-500">No attendance recorded yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
