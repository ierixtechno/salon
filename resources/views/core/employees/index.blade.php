<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Employees</h2>
            @can('create', App\Domain\Core\Models\EmployeeProfile::class)
                <a href="{{ route('employees.create') }}"
                    class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm rounded-md hover:bg-indigo-500">
                    + New employee
                </a>
            @endcan
        </div>
    </x-slot>

    <div class="py-8">
        <div class="px-4 sm:px-6 lg:px-8">
            @if ($userLimit !== null)
                @php $full = $userCount >= $userLimit; @endphp
                <div class="mb-4 rounded-md border px-4 py-3 text-sm {{ $full ? 'bg-amber-50 border-amber-200 text-amber-800' : 'bg-indigo-50 border-indigo-100 text-indigo-900' }}">
                    Your plan allows {{ $userLimit }} {{ \Illuminate\Support\Str::plural('user', $userLimit) }} ({{ $userCount }} in use).
                    @if ($full)
                        To add more, add branches or upgrade your plan.
                        @can('tenant.billing.manage')
                            <a href="{{ route('billing.plans.index') }}" class="font-semibold underline">View plans</a>
                        @endcan
                    @endif
                </div>
            @endif
            <x-input-error :messages="$errors->get('user_limit')" class="mb-4" />

            @can('tenant.billing.manage')
                @if ($extraUserCount > 0)
                    <div class="mb-4 rounded-md border border-gray-200 bg-white px-4 py-3 text-sm">
                        @if ($pendingEmployeeReduction)
                            <p class="text-gray-700">Request pending: reduce purchased employee slots from {{ $extraUserCount }} to {{ $pendingEmployeeReduction->requested_extra_user_count }}. Super Admin will review it.</p>
                        @else
                            <form method="POST" action="{{ route('employee-reduction-requests.store') }}" class="flex flex-wrap items-end gap-3">
                                @csrf
                                <div>
                                    <label for="requested_extra_user_count" class="block text-xs text-gray-500 mb-1">Reduce purchased employee slots (now {{ $extraUserCount }}) to</label>
                                    <input id="requested_extra_user_count" name="requested_extra_user_count" type="number" min="0" max="{{ $extraUserCount - 1 }}" required class="w-28 border-gray-300 rounded-md shadow-sm text-sm">
                                </div>
                                <div class="flex-1 min-w-[12rem]">
                                    <label for="reduction_reason" class="block text-xs text-gray-500 mb-1">Reason (optional)</label>
                                    <input id="reduction_reason" name="reason" type="text" maxlength="500" class="w-full border-gray-300 rounded-md shadow-sm text-sm">
                                </div>
                                <button type="submit" class="inline-flex items-center rounded-md bg-gray-800 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700">Request reduction</button>
                            </form>
                            <p class="text-xs text-gray-500 mt-2">Deactivate the staff you no longer need first. The lower price applies from your next renewal; no refund for the current period.</p>
                            <x-input-error :messages="$errors->get('requested_extra_user_count')" class="mt-2" />
                        @endif
                    </div>
                @endif
            @endcan

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
                                <th class="text-left px-4 py-2 font-semibold text-base">Name</th>
                                <th class="text-left px-4 py-2 font-semibold text-base">Job title</th>
                                <th class="text-left px-4 py-2 font-semibold text-base">Role</th>
                                <th class="text-left px-4 py-2 font-semibold text-base">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($employees as $employee)
                                <tr>
                                    <td class="px-4 py-2">
                                        <a href="{{ route('employees.edit', $employee) }}" class="text-indigo-600 hover:text-indigo-800">
                                            {{ $employee->user->name }}
                                        </a>
                                        <div class="text-xs text-gray-500">{{ $employee->user->email }}</div>
                                    </td>
                                    <td class="px-4 py-2 text-gray-500">{{ $employee->job_title ?? '—' }}</td>
                                    <td class="px-4 py-2 text-gray-500">{{ $employee->user->roles->first()?->name ?? '—' }}</td>
                                    <td class="px-4 py-2">
                                        <span class="text-xs px-2 py-1 rounded-full {{ $employee->user->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                                            {{ $employee->user->is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-6 text-center text-gray-500">No employees yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
