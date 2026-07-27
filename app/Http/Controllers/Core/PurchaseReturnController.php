<?php

namespace App\Http\Controllers\Core;

use App\Domain\Core\Actions\CreatePurchaseReturn;
use App\Domain\Core\Models\Branch;
use App\Domain\Core\Models\Product;
use App\Domain\Core\Models\PurchaseOrder;
use App\Domain\Core\Models\PurchaseReturn;
use App\Domain\Core\Models\Supplier;
use App\Http\Controllers\Controller;
use App\Http\Requests\Core\StorePurchaseReturnRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class PurchaseReturnController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', PurchaseOrder::class);
        $branch = $this->resolveBranch($request);

        $returns = $branch
            ? PurchaseReturn::where('branch_id', $branch->id)->with(['supplier', 'movements.product'])->latest()->paginate(20)->withQueryString()
            : collect();

        return view('core.purchase-returns.index', [
            'branches' => $this->accessibleBranches(),
            'branch' => $branch,
            'returns' => $returns,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', PurchaseOrder::class);

        return view('core.purchase-returns.create', [
            'branches' => $this->accessibleBranches(),
            'branch' => $this->resolveBranch($request),
            'suppliers' => Supplier::where('is_active', true)->orderBy('name')->get(),
            'products' => Product::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(StorePurchaseReturnRequest $request, CreatePurchaseReturn $action): RedirectResponse
    {
        $return = $action->execute(
            branch: Branch::findOrFail($request->validated('branch_id')),
            supplier: Supplier::findOrFail($request->validated('supplier_id')),
            purchaseOrder: $request->validated('purchase_order_id') ? PurchaseOrder::find($request->validated('purchase_order_id')) : null,
            lines: $request->validated('lines'),
            reason: $request->validated('reason'),
            createdBy: Auth::guard('web')->id(),
        );

        return redirect()->route('purchase-returns.index', ['branch_id' => $return->branch_id])->with('status', 'Return recorded.');
    }

    private function resolveBranch(Request $request): ?Branch
    {
        $accessible = $this->accessibleBranches();

        if ($request->filled('branch_id')) {
            $requested = $accessible->firstWhere('id', (int) $request->integer('branch_id'));
            if ($requested) {
                return $requested;
            }
        }

        return $accessible->first();
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
