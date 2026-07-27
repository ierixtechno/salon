<?php

namespace App\Http\Controllers\Core;

use App\Domain\Core\Models\ExpenseCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Core\StoreExpenseCategoryRequest;
use App\Http\Requests\Core\UpdateExpenseCategoryRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class ExpenseCategoryController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:expense-categories.manage');
    }

    public function index(): View
    {
        return view('core.expense-categories.index', [
            'categories' => ExpenseCategory::orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('core.expense-categories.create');
    }

    public function store(StoreExpenseCategoryRequest $request): RedirectResponse
    {
        ExpenseCategory::create($request->validated());

        return redirect()->route('expense-categories.index')->with('status', 'Expense category created.');
    }

    public function edit(ExpenseCategory $expenseCategory): View
    {
        return view('core.expense-categories.edit', ['category' => $expenseCategory]);
    }

    public function update(UpdateExpenseCategoryRequest $request, ExpenseCategory $expenseCategory): RedirectResponse
    {
        $expenseCategory->update($request->validated());

        return redirect()->route('expense-categories.index')->with('status', 'Expense category updated.');
    }

    public function destroy(ExpenseCategory $expenseCategory): RedirectResponse
    {
        $expenseCategory->update(['is_active' => false]);

        return redirect()->route('expense-categories.index')->with('status', 'Expense category deactivated.');
    }
}
