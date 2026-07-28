<x-app-layout>
    <x-slot name="header">Commission Rules</x-slot>

    <div class="py-8">
        <div class="px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                    {{ session('status') }}
                </div>
            @endif

            <p class="text-sm text-gray-500 mb-4">One flat commission rule per employee, applied when an invoice is fully paid.</p>

            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-gray-500">
                            <tr>
                                <th class="text-left px-4 py-2 font-medium">Employee</th>
                                <th class="text-left px-4 py-2 font-medium">Type</th>
                                <th class="text-left px-4 py-2 font-medium">Rate</th>
                                <th class="text-left px-4 py-2 font-medium">Active</th>
                                <th class="text-left px-4 py-2 font-medium"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($employees as $employee)
                                @php $rule = $employee->user->commissionRule; $formId = 'commission-form-'.$employee->user->id; @endphp
                                <tr>
                                    <td class="px-4 py-2 font-medium text-gray-800">
                                        {{ $employee->user->name }}
                                        <form id="{{ $formId }}" method="POST" action="{{ route('commission.rules.update', $employee->user) }}">
                                            @csrf
                                            @method('PUT')
                                        </form>
                                    </td>
                                    <td class="px-4 py-2">
                                        <select form="{{ $formId }}" name="type" class="border-gray-300 rounded-md shadow-sm text-xs">
                                            @foreach (\App\Domain\Core\Models\CommissionRule::TYPES as $type)
                                                <option value="{{ $type }}" @selected(($rule?->type ?? 'percentage') === $type)>{{ ucfirst($type) }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="px-4 py-2">
                                        <input form="{{ $formId }}" type="number" step="0.01" min="0" name="rate" value="{{ $rule?->rate ?? 0 }}" class="w-24 border-gray-300 rounded-md shadow-sm text-xs">
                                    </td>
                                    <td class="px-4 py-2">
                                        <input form="{{ $formId }}" type="checkbox" name="is_active" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" @checked($rule?->is_active ?? false)>
                                    </td>
                                    <td class="px-4 py-2">
                                        <button form="{{ $formId }}" type="submit" class="text-xs px-3 py-1.5 rounded-md bg-gray-800 text-white hover:bg-gray-700">Save</button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-6 text-center text-gray-500">No employees yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
