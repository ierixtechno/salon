<x-app-layout>
    <x-slot name="header">New Expense</x-slot>

    <div class="py-8">
        <div class="px-4 sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm rounded-lg p-6">
                <form method="POST" action="{{ route('expenses.store') }}" class="space-y-4" enctype="multipart/form-data">
                    @csrf

                    <div>
                        <x-input-label for="branch_id" value="Branch" />
                        <select id="branch_id" name="branch_id" required class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">
                            @foreach ($branches as $b)
                                <option value="{{ $b->id }}" @selected(old('branch_id') == $b->id)>{{ $b->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('branch_id')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="expense_category_id" value="Category" />
                        <select id="expense_category_id" name="expense_category_id" required class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected(old('expense_category_id') == $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('expense_category_id')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="vendor_name" value="Vendor (optional)" />
                        <x-text-input id="vendor_name" class="block mt-1 w-full" type="text" name="vendor_name" :value="old('vendor_name')" />
                        <x-input-error :messages="$errors->get('vendor_name')" class="mt-2" />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="amount" value="Amount" />
                            <x-text-input id="amount" class="block mt-1 w-full" type="number" step="0.01" min="0.01" name="amount" :value="old('amount')" required />
                            <x-input-error :messages="$errors->get('amount')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="tax_amount" value="Tax (optional)" />
                            <x-text-input id="tax_amount" class="block mt-1 w-full" type="number" step="0.01" min="0" name="tax_amount" value="0" />
                            <x-input-error :messages="$errors->get('tax_amount')" class="mt-2" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="payment_method" value="Payment method" />
                            <select id="payment_method" name="payment_method" required class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">
                                @foreach (\App\Domain\Core\Models\Expense::PAYMENT_METHODS as $method)
                                    <option value="{{ $method }}" @selected(old('payment_method') === $method)>{{ str($method)->replace('_', ' ')->headline() }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('payment_method')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="expense_date" value="Expense date" />
                            <x-text-input id="expense_date" class="block mt-1 w-full" type="date" name="expense_date" :value="old('expense_date', now()->toDateString())" required />
                            <x-input-error :messages="$errors->get('expense_date')" class="mt-2" />
                        </div>
                    </div>

                    <div>
                        <x-input-label for="description" value="Description (optional)" />
                        <textarea id="description" name="description" rows="3" class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">{{ old('description') }}</textarea>
                        <x-input-error :messages="$errors->get('description')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="attachment" value="Receipt / attachment (optional)" />
                        <input id="attachment" type="file" name="attachment" accept=".jpg,.jpeg,.png,.pdf" class="block mt-1 w-full text-sm">
                        <p class="text-xs text-gray-500 mt-1">JPG, PNG or PDF, up to 5MB.</p>
                        <x-input-error :messages="$errors->get('attachment')" class="mt-2" />
                    </div>

                    <div class="flex justify-end">
                        <x-primary-button>Save expense</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
