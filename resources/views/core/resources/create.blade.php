<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">New Resource — {{ $branch->name }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="px-4 sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm rounded-lg p-6">
                <form method="POST" action="{{ route('branches.resources.store', $branch) }}" class="space-y-4">
                    @csrf

                    <div>
                        <x-input-label for="type" value="Type" />
                        <select id="type" name="type" required class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">
                            @foreach ($types as $type)
                                <option value="{{ $type }}" @selected(old('type') === $type)>{{ str($type)->replace('_', ' ')->headline() }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('type')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="name" value="Name" />
                        <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" placeholder="e.g. Chair 1, VIP Room" required autofocus />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="capacity" value="Capacity" />
                        <x-text-input id="capacity" class="block mt-1 w-24" type="number" name="capacity" min="1" max="20" :value="old('capacity', 1)" required />
                        <p class="text-xs text-gray-500 mt-1">Simultaneous customers this resource can serve (e.g. 2 for a couple spa room).</p>
                        <x-input-error :messages="$errors->get('capacity')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="turnaround_minutes" value="Turnaround time (min)" />
                        <x-text-input id="turnaround_minutes" class="block mt-1 w-24" type="number" name="turnaround_minutes" min="0" max="240" :value="old('turnaround_minutes', 0)" />
                        <p class="text-xs text-gray-500 mt-1">Cleaning/reset time needed after a session before this resource can be booked again.</p>
                        <x-input-error :messages="$errors->get('turnaround_minutes')" class="mt-2" />
                    </div>

                    <div class="flex justify-end">
                        <x-primary-button>Add resource</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
