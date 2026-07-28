<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Expenses</h2>
            <div class="flex items-center gap-2">
                @if ($branches->isNotEmpty())
                    <form method="GET" class="flex items-center gap-2">
                        <select name="branch_id" onchange="this.form.submit()" class="border-gray-300 rounded-md shadow-sm text-sm">
                            <option value="">All branches</option>
                            @foreach ($branches as $b)
                                <option value="{{ $b->id }}" @selected($branch?->id === $b->id)>{{ $b->name }}</option>
                            @endforeach
                        </select>
                        <select name="status" onchange="this.form.submit()" class="border-gray-300 rounded-md shadow-sm text-sm">
                            <option value="">All statuses</option>
                            @foreach (\App\Domain\Core\Models\Expense::STATUSES as $s)
                                <option value="{{ $s }}" @selected($status === $s)>{{ ucfirst($s) }}</option>
                            @endforeach
                        </select>
                    </form>
                @endif
                @can('expense-categories.manage')
                    <a href="{{ route('expense-categories.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Manage categories</a>
                @endcan
                @can('expenses.create')
                    <a href="{{ route('expenses.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm rounded-md hover:bg-indigo-500">
                        + New expense
                    </a>
                @endcan
            </div>
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
                                <th class="text-left px-4 py-2 font-semibold text-base">Date</th>
                                <th class="text-left px-4 py-2 font-semibold text-base">Category</th>
                                <th class="text-left px-4 py-2 font-semibold text-base">Vendor</th>
                                <th class="text-left px-4 py-2 font-semibold text-base">Amount</th>
                                <th class="text-left px-4 py-2 font-semibold text-base">Method</th>
                                <th class="text-left px-4 py-2 font-semibold text-base">Status</th>
                                <th class="text-left px-4 py-2 font-semibold text-base">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($expenses as $expense)
                                <tr>
                                    <td class="px-4 py-2 text-gray-700">{{ $expense->expense_date->format('d M Y') }}</td>
                                    <td class="px-4 py-2 text-gray-500">{{ $expense->expenseCategory->name }}</td>
                                    <td class="px-4 py-2 text-gray-500">{{ $expense->vendor_name ?? '—' }}</td>
                                    <td class="px-4 py-2 text-gray-800">
                                        ₹{{ number_format($expense->amount + $expense->tax_amount, 2) }}
                                        @if ($expense->attachment_path)
                                            <a href="{{ route('expenses.attachment', $expense) }}" class="ml-1 text-xs text-indigo-600 hover:text-indigo-800">📎</a>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 text-gray-500">{{ str($expense->payment_method)->replace('_', ' ')->headline() }}</td>
                                    <td class="px-4 py-2">
                                        <span @class([
                                            'text-xs px-2 py-1 rounded-full',
                                            'bg-amber-100 text-amber-800' => $expense->status === 'pending',
                                            'bg-green-100 text-green-800' => $expense->status === 'approved',
                                            'bg-red-100 text-red-700' => $expense->status === 'rejected',
                                        ])>
                                            {{ ucfirst($expense->status) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2">
                                        @can('expenses.approve')
                                            @if ($expense->status === 'pending')
                                                <div class="flex items-center gap-2">
                                                    <form method="POST" action="{{ route('expenses.approve', $expense) }}">
                                                        @csrf
                                                        <button type="submit" class="text-xs px-3 py-1.5 rounded-md bg-green-600 text-white hover:bg-green-500">Approve</button>
                                                    </form>
                                                    <form method="POST" action="{{ route('expenses.reject', $expense) }}" onsubmit="return confirm('Reject this expense?')">
                                                        @csrf
                                                        <button type="submit" class="text-xs px-3 py-1.5 rounded-md bg-red-600 text-white hover:bg-red-500">Reject</button>
                                                    </form>
                                                </div>
                                            @endif
                                        @endcan
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-6 text-center text-gray-500">No expenses recorded yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{ $expenses->links() }}
        </div>
    </div>
</x-app-layout>
