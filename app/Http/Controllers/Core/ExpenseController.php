<?php

namespace App\Http\Controllers\Core;

use App\Domain\Core\Actions\ApproveExpense;
use App\Domain\Core\Actions\CreateExpense;
use App\Domain\Core\Actions\RejectExpense;
use App\Domain\Core\Models\Branch;
use App\Domain\Core\Models\Expense;
use App\Domain\Core\Models\ExpenseCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Core\DecideExpenseRequest;
use App\Http\Requests\Core\StoreExpenseRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExpenseController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:expenses.view')->only(['index']);
        $this->middleware('can:expenses.create')->only(['create', 'store']);
        $this->middleware('can:expenses.approve')->only(['approve', 'reject']);
    }

    public function index(Request $request): View
    {
        $branches = $this->accessibleBranches();
        $branch = $this->resolveBranch($request, $branches);

        $query = Expense::with(['expenseCategory', 'createdBy'])->latest('expense_date');
        if ($branch) {
            $query->where('branch_id', $branch->id);
        } else {
            $query->whereIn('branch_id', $branches->pluck('id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return view('core.expenses.index', [
            'branches' => $branches,
            'branch' => $branch,
            'status' => $request->string('status')->toString(),
            'expenses' => $query->paginate(30)->withQueryString(),
        ]);
    }

    public function create(): View
    {
        return view('core.expenses.create', [
            'branches' => $this->accessibleBranches(),
            'categories' => ExpenseCategory::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(StoreExpenseRequest $request, CreateExpense $action): RedirectResponse
    {
        $branch = Branch::findOrFail($request->validated('branch_id'));
        $category = ExpenseCategory::findOrFail($request->validated('expense_category_id'));

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $filename = (string) Str::uuid().'.'.$file->getClientOriginalExtension();
            $attachmentPath = $file->storeAs('expenses/'.$branch->tenant_id, $filename, 'local');
        }

        $action->execute(
            branch: $branch,
            category: $category,
            amount: (float) $request->validated('amount'),
            taxAmount: (float) ($request->validated('tax_amount') ?? 0),
            paymentMethod: $request->validated('payment_method'),
            expenseDate: Carbon::parse($request->validated('expense_date')),
            vendorName: $request->validated('vendor_name'),
            description: $request->validated('description'),
            attachmentPath: $attachmentPath,
            createdBy: Auth::guard('web')->id(),
        );

        return redirect()->route('expenses.index')->with('status', 'Expense recorded.');
    }

    public function approve(DecideExpenseRequest $request, Expense $expense, ApproveExpense $action): RedirectResponse
    {
        $action->execute($expense, Auth::guard('web')->id());

        return back()->with('status', 'Expense approved.');
    }

    public function reject(DecideExpenseRequest $request, Expense $expense, RejectExpense $action): RedirectResponse
    {
        $action->execute($expense, $request->validated('reason'), Auth::guard('web')->id());

        return back()->with('status', 'Expense rejected.');
    }

    public function downloadAttachment(Expense $expense): StreamedResponse
    {
        $user = Auth::guard('web')->user();
        abort_unless($user->can('expenses.view') && $user->canAccessBranch($expense->branch), 403);
        abort_unless($expense->attachment_path && Storage::disk('local')->exists($expense->attachment_path), 404);

        return Storage::disk('local')->download($expense->attachment_path);
    }

    private function resolveBranch(Request $request, Collection $accessible): ?Branch
    {
        if ($request->filled('branch_id')) {
            $requested = $accessible->firstWhere('id', (int) $request->integer('branch_id'));
            if ($requested) {
                return $requested;
            }
        }

        return null;
    }

    private function accessibleBranches(): Collection
    {
        $user = Auth::guard('web')->user();

        return Branch::where('is_active', true)
            ->orderBy('name')
            ->get()
            ->filter(fn (Branch $branch) => $user->canAccessBranch($branch))
            ->values();
    }
}
