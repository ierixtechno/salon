<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">New Employee</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm rounded-lg p-6">
                <form method="POST" action="{{ route('employees.store') }}" class="space-y-4" x-data="{ allBranches: false }">
                    @csrf

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="name" value="Full name" />
                            <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required autofocus />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="email" value="Email (their login)" />
                            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required />
                            <x-input-error :messages="$errors->get('email')" class="mt-2" />
                        </div>
                    </div>

                    <div>
                        <x-input-label for="password" value="Temporary password" />
                        <x-text-input id="password" class="block mt-1 w-full" type="password" name="password" required />
                        <p class="text-xs text-gray-500 mt-1">Share this with them directly — there's no invite email yet.</p>
                        <x-input-error :messages="$errors->get('password')" class="mt-2" />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="job_title" value="Job title" />
                            <x-text-input id="job_title" class="block mt-1 w-full" type="text" name="job_title" :value="old('job_title')" placeholder="e.g. Senior Stylist" />
                        </div>
                        <div>
                            <x-input-label for="employment_type" value="Employment type" />
                            <select id="employment_type" name="employment_type" required class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">
                                @foreach (\App\Domain\Core\Models\EmployeeProfile::EMPLOYMENT_TYPES as $type)
                                    <option value="{{ $type }}" @selected(old('employment_type') === $type)>{{ str($type)->replace('_', ' ')->headline() }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="hire_date" value="Hire date" />
                            <x-text-input id="hire_date" class="block mt-1 w-full" type="date" name="hire_date" :value="old('hire_date')" />
                        </div>
                        <div>
                            <x-input-label for="phone" value="Phone" />
                            <x-text-input id="phone" class="block mt-1 w-full" type="text" name="phone" :value="old('phone')" />
                        </div>
                    </div>

                    <div>
                        <x-input-label for="role" value="Role" />
                        <select id="role" name="role" required class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">
                            @foreach ($roles as $role)
                                <option value="{{ $role }}" @selected(old('role') === $role)>{{ $role }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('role')" class="mt-2" />
                    </div>

                    <div>
                        <label class="flex items-center gap-2">
                            <input type="checkbox" name="all_branches" value="1" x-model="allBranches"
                                class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" @checked(old('all_branches'))>
                            <span class="text-sm text-gray-700">Access to all branches</span>
                        </label>

                        <div x-show="!allBranches" class="grid grid-cols-1 sm:grid-cols-2 gap-2 mt-3">
                            @foreach ($branches as $branch)
                                <label class="flex items-center gap-2 border rounded-md px-3 py-2 cursor-pointer hover:bg-gray-50">
                                    <input type="checkbox" name="branches[]" value="{{ $branch->id }}"
                                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                        @checked(collect(old('branches', []))->contains($branch->id))>
                                    <span class="text-sm text-gray-800">{{ $branch->name }}</span>
                                </label>
                            @endforeach
                        </div>
                        <x-input-error :messages="$errors->get('branches')" class="mt-2" />
                    </div>

                    <div class="flex justify-end">
                        <x-primary-button>Add employee</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
