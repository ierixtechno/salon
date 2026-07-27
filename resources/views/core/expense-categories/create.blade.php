<x-app-layout>
    <x-slot name="header">New Expense Category</x-slot>

    <div class="py-8">
        <div class="max-w-xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm rounded-lg p-6">
                <form method="POST" action="{{ route('expense-categories.store') }}" class="space-y-4">
                    @csrf

                    <div>
                        <x-input-label for="name" value="Category name" />
                        <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" placeholder="e.g. Rent, Utilities" required autofocus />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div class="flex justify-end">
                        <x-primary-button>Create category</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
