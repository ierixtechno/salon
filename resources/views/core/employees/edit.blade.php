<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $employee->user->name }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="px-4 sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                    {{ session('status') }}
                </div>
            @endif

            @php
                $showWorkforce = Auth::user()->can('attendance.view') || Auth::user()->can('leave.view') || Auth::user()->can('commission.manage');
                $hasAssignedBranches = $assignedBranchIds->isNotEmpty();
                $canDeactivateEmployee = auth()->user()->can('delete', $employee);

                $tabs = ['profile' => 'Profile'];
                if ($showWorkforce) {
                    $tabs['workforce'] = 'Workforce';
                }
                if ($hasAssignedBranches) {
                    $tabs['schedule'] = 'Schedule';
                }
                if ($canDeactivateEmployee) {
                    $tabs['danger'] = 'Danger Zone';
                }
            @endphp

            <x-tabs :items="$tabs" default="profile">
                <x-tab-panel name="profile">
                    <div class="bg-white shadow-sm rounded-lg p-6">
                        <h3 class="font-medium text-gray-900 mb-4">Profile</h3>

                        <form method="POST" action="{{ route('employees.update', $employee) }}" class="space-y-4" x-data="{ allBranches: {{ $employee->user->all_branches ? 'true' : 'false' }} }">
                            @csrf
                            @method('PUT')

                            <div>
                                <x-input-label for="name" value="Full name" />
                                <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name', $employee->user->name)" required />
                                <x-input-error :messages="$errors->get('name')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="email" value="Email (login ID)" />
                                <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email', $employee->user->email)" required />
                                <x-input-error :messages="$errors->get('email')" class="mt-2" />
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <x-input-label for="job_title" value="Job title" />
                                    <x-text-input id="job_title" class="block mt-1 w-full" type="text" name="job_title" :value="old('job_title', $employee->job_title)" />
                                </div>
                                <div>
                                    <x-input-label for="employment_type" value="Employment type" />
                                    <select id="employment_type" name="employment_type" required class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">
                                        @foreach (\App\Domain\Core\Models\EmployeeProfile::EMPLOYMENT_TYPES as $type)
                                            <option value="{{ $type }}" @selected(old('employment_type', $employee->employment_type) === $type)>{{ str($type)->replace('_', ' ')->headline() }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <x-input-label for="hire_date" value="Hire date" />
                                    <x-text-input id="hire_date" class="block mt-1 w-full" type="date" name="hire_date" :value="old('hire_date', optional($employee->hire_date)->format('Y-m-d'))" />
                                </div>
                                <div>
                                    <x-input-label for="phone" value="Phone" />
                                    <x-phone-input id="phone" class="block w-full" :value="old('phone', $employee->phone)" />
                                    <x-input-error :messages="$errors->get('phone')" class="mt-2" />
                                </div>
                            </div>

                            <div>
                                <x-input-label for="role" value="Role" />
                                <select id="role" name="role" required class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">
                                    @foreach ($roles as $role)
                                        <option value="{{ $role }}" @selected(old('role', $currentRole) === $role)>{{ $role }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('role')" class="mt-2" />
                            </div>

                            <div>
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" name="all_branches" value="1" x-model="allBranches"
                                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                    <span class="text-sm text-gray-700">Access to all branches</span>
                                </label>

                                <div x-show="!allBranches" class="grid grid-cols-1 sm:grid-cols-2 gap-2 mt-3">
                                    @foreach ($branches as $branch)
                                        <label class="flex items-center gap-2 border rounded-md px-3 py-2 cursor-pointer hover:bg-gray-50">
                                            <input type="checkbox" name="branches[]" value="{{ $branch->id }}"
                                                class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                                @checked($assignedBranchIds->contains($branch->id))>
                                            <span class="text-sm text-gray-800">{{ $branch->name }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>

                            <label class="flex items-center gap-2">
                                <input type="checkbox" name="is_active" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" @checked(old('is_active', $employee->user->is_active))>
                                <span class="text-sm text-gray-700">Active</span>
                            </label>

                            <div class="flex justify-end">
                                <x-primary-button>Save</x-primary-button>
                            </div>
                        </form>
                    </div>
                </x-tab-panel>

                @if ($showWorkforce)
                    <x-tab-panel name="workforce">
                        <div class="bg-white shadow-sm rounded-lg p-6">
                            <h3 class="font-medium text-gray-900 mb-4">Workforce</h3>
                            <div class="flex flex-wrap gap-3">
                                @can('attendance.view')
                                    <a href="{{ route('attendance.index') }}" class="text-sm px-3 py-1.5 rounded-md border bg-gray-50 border-gray-300 text-gray-700 hover:bg-gray-100">Attendance register</a>
                                @endcan
                                @can('leave.view')
                                    <a href="{{ route('leave.index') }}" class="text-sm px-3 py-1.5 rounded-md border bg-gray-50 border-gray-300 text-gray-700 hover:bg-gray-100">Leave requests</a>
                                @endcan
                                @can('commission.manage')
                                    <a href="{{ route('commission.rules') }}" class="text-sm px-3 py-1.5 rounded-md border bg-gray-50 border-gray-300 text-gray-700 hover:bg-gray-100">Commission rules</a>
                                @endcan
                            </div>
                        </div>
                    </x-tab-panel>
                @endif

                @if ($hasAssignedBranches)
                    <x-tab-panel name="schedule">
                        @foreach ($branches as $branch)
                            @continue(! $assignedBranchIds->contains($branch->id))
                            @php $schedule = $schedulesByBranch->get($branch->id, collect())->keyBy('day_of_week'); @endphp
                            <div class="bg-white shadow-sm rounded-lg p-6">
                                <h3 class="font-medium text-gray-900 mb-1">Weekly Schedule — {{ $branch->name }}</h3>
                                <p class="text-xs text-gray-500 mb-4">Used later to check availability when booking appointments at this branch.</p>

                                <form method="POST" action="{{ route('employees.schedule', $employee) }}">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="branch_id" value="{{ $branch->id }}">

                                    <div class="space-y-2">
                                        @foreach (range(0, 6) as $day)
                                            @php $shift = $schedule->get($day); @endphp
                                            <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-4 py-2 border-b border-gray-100 last:border-0">
                                                <input type="hidden" name="shifts[{{ $day }}][day_of_week]" value="{{ $day }}">
                                                <div class="w-full sm:w-28 text-sm font-medium text-gray-700">
                                                    {{ \Carbon\Carbon::create()->startOfWeek(\Carbon\Carbon::SUNDAY)->addDays($day)->format('l') }}
                                                </div>

                                                <label class="flex items-center gap-2 text-sm text-gray-600">
                                                    <input type="checkbox" name="shifts[{{ $day }}][is_off]" value="1"
                                                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                                        @checked(! $shift) onchange="this.closest('div').querySelectorAll('input[type=time]').forEach(el => el.disabled = this.checked)">
                                                    Off
                                                </label>

                                                <div class="flex items-center gap-2">
                                                    <input type="time" name="shifts[{{ $day }}][starts_at]"
                                                        value="{{ $shift ? substr($shift->starts_at, 0, 5) : '' }}"
                                                        @disabled(! $shift)
                                                        class="border-gray-300 rounded-md shadow-sm text-sm disabled:bg-gray-100">
                                                    <span class="text-gray-400 text-sm">to</span>
                                                    <input type="time" name="shifts[{{ $day }}][ends_at]"
                                                        value="{{ $shift ? substr($shift->ends_at, 0, 5) : '' }}"
                                                        @disabled(! $shift)
                                                        class="border-gray-300 rounded-md shadow-sm text-sm disabled:bg-gray-100">
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>

                                    <div class="flex justify-end mt-4">
                                        <x-primary-button>Save schedule</x-primary-button>
                                    </div>
                                </form>
                            </div>
                        @endforeach
                    </x-tab-panel>
                @endif

                @if ($canDeactivateEmployee)
                    <x-tab-panel name="danger">
                        <div class="bg-white shadow-sm rounded-lg p-6">
                            <h3 class="font-medium text-gray-900 mb-1">Deactivate employee</h3>
                            <p class="text-xs text-gray-500 mb-4">Revokes login access. Historical records are kept.</p>
                            <form method="POST" action="{{ route('employees.destroy', $employee) }}" onsubmit="return confirm('Deactivate this employee?')">
                                @csrf
                                @method('DELETE')
                                <x-danger-button>Deactivate</x-danger-button>
                            </form>
                        </div>
                    </x-tab-panel>
                @endif
            </x-tabs>
        </div>
    </div>
</x-app-layout>
