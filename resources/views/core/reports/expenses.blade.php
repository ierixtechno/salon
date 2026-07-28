<x-app-layout>
    <x-slot name="header">Expenses Report</x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            @include('core.reports._tabs')
            @include('core.reports._filters')

            <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 mb-6">
                <div class="bg-white shadow-sm rounded-lg p-4">
                    <p class="text-xs text-gray-500">Total spent</p>
                    <p class="text-xl font-semibold text-gray-900">{{ current_tenant()->currency }} {{ number_format($totals->amount, 2) }}</p>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-4">
                    <p class="text-xs text-gray-500">Tax paid</p>
                    <p class="text-xl font-semibold text-gray-900">{{ current_tenant()->currency }} {{ number_format($totals->tax, 2) }}</p>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-4">
                    <p class="text-xs text-gray-500">Expenses</p>
                    <p class="text-xl font-semibold text-gray-900">{{ $totals->expense_count }}</p>
                </div>
            </div>

            <p class="text-xs text-gray-500 mb-2">Only approved expenses are counted.</p>

            <div class="bg-white shadow-sm rounded-lg overflow-hidden mb-6">
                <div class="px-6 py-4 border-b border-gray-100"><h3 class="font-medium text-gray-900">By category</h3></div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-gray-500">
                            <tr>
                                <th class="text-left px-4 py-2 font-medium">Category</th>
                                <th class="text-left px-4 py-2 font-medium">Count</th>
                                <th class="text-left px-4 py-2 font-medium">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($byCategory as $row)
                                <tr>
                                    <td class="px-4 py-2 text-gray-700">{{ $row->name }}</td>
                                    <td class="px-4 py-2 text-gray-500">{{ $row->expense_count }}</td>
                                    <td class="px-4 py-2 text-gray-800">{{ number_format($row->amount, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="px-4 py-6 text-center text-gray-500">No expenses in this range.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($byBranch->isNotEmpty())
                <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100"><h3 class="font-medium text-gray-900">By branch</h3></div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 text-gray-500">
                                <tr>
                                    <th class="text-left px-4 py-2 font-medium">Branch</th>
                                    <th class="text-left px-4 py-2 font-medium">Count</th>
                                    <th class="text-left px-4 py-2 font-medium">Amount</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($byBranch as $row)
                                    <tr>
                                        <td class="px-4 py-2 text-gray-700">{{ $row->branch_name }}</td>
                                        <td class="px-4 py-2 text-gray-500">{{ $row->expense_count }}</td>
                                        <td class="px-4 py-2 text-gray-800">{{ number_format($row->amount, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
