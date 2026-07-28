<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Edit Resource</h2>
    </x-slot>

    <div class="py-8">
        <div class="px-4 sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm rounded-lg p-6">
                <form method="POST" action="{{ route('resources.update', $resource) }}" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <div>
                        <x-input-label for="type" value="Type" />
                        <select id="type" name="type" required class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">
                            @foreach ($types as $type)
                                <option value="{{ $type }}" @selected(old('type', $resource->type) === $type)>{{ str($type)->replace('_', ' ')->headline() }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('type')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="name" value="Name" />
                        <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name', $resource->name)" required />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="capacity" value="Capacity" />
                        <x-text-input id="capacity" class="block mt-1 w-24" type="number" name="capacity" min="1" max="20" :value="old('capacity', $resource->capacity)" required />
                        <x-input-error :messages="$errors->get('capacity')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="turnaround_minutes" value="Turnaround time (min)" />
                        <x-text-input id="turnaround_minutes" class="block mt-1 w-24" type="number" name="turnaround_minutes" min="0" max="240" :value="old('turnaround_minutes', $resource->turnaround_minutes)" />
                        <p class="text-xs text-gray-500 mt-1">Cleaning/reset time needed after a session before this resource can be booked again.</p>
                        <x-input-error :messages="$errors->get('turnaround_minutes')" class="mt-2" />
                    </div>

                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="is_active" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" @checked(old('is_active', $resource->is_active))>
                        <span class="text-sm text-gray-700">Active</span>
                    </label>

                    <div class="flex items-center justify-between pt-2">
                        <a href="{{ route('branches.resources.index', $resource->branch_id) }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Back</a>
                        <div class="flex gap-3">
                            <x-primary-button>Save</x-primary-button>
                        </div>
                    </div>
                </form>

                @can('delete', $resource)
                    <form method="POST" action="{{ route('resources.destroy', $resource) }}" onsubmit="return confirm('Remove this resource?')" class="mt-4 pt-4 border-t border-gray-100">
                        @csrf
                        @method('DELETE')
                        <x-danger-button>Remove resource</x-danger-button>
                    </form>
                @endcan
            </div>
        </div>
    </div>
</x-app-layout>
