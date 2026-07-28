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
