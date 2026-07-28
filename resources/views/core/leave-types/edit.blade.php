<x-app-layout>
    <x-slot name="header">{{ $leaveType->name }}</x-slot>

    <div class="py-8">
        <div class="px-4 sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                    {{ session('status') }}
                </div>
            @endif

            <div class="bg-white shadow-sm rounded-lg p-6">
                <form method="POST" action="{{ route('leave-types.update', $leaveType) }}" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <div>
                        <x-input-label for="name" value="Name" />
                        <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name', $leaveType->name)" required />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="annual_days" value="Annual days (leave blank for unlimited)" />
                        <x-text-input id="annual_days" class="block mt-1 w-full" type="number" min="0" max="365" name="annual_days" :value="old('annual_days', $leaveType->annual_days)" />
                        <x-input-error :messages="$errors->get('annual_days')" class="mt-2" />
                    </div>

                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="is_paid" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" @checked(old('is_paid', $leaveType->is_paid))>
                        <span class="text-sm text-gray-700">Paid leave</span>
                    </label>

                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="is_active" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" @checked(old('is_active', $leaveType->is_active))>
                        <span class="text-sm text-gray-700">Active</span>
                    </label>

                    <div class="flex justify-end">
                        <x-primary-button>Save</x-primary-button>
                    </div>
                </form>
            </div>

            <div class="bg-white shadow-sm rounded-lg p-6">
                <h3 class="font-medium text-gray-900 mb-1">Deactivate leave type</h3>
                <p class="text-xs text-gray-500 mb-4">Existing/historical leave requests keep working — this just hides it from new-request pickers.</p>
                <form method="POST" action="{{ route('leave-types.destroy', $leaveType) }}" onsubmit="return confirm('Deactivate this leave type?')">
                    @csrf
                    @method('DELETE')
                    <x-danger-button>Deactivate</x-danger-button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
