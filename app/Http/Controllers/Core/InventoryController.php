<?php

namespace App\Http\Controllers\Core;

use App\Domain\Core\Actions\AdjustStock;
use App\Domain\Core\Actions\TransferStock;
use App\Domain\Core\Models\Branch;
use App\Domain\Core\Models\BranchStock;
use App\Domain\Core\Models\Product;
use App\Domain\Core\Models\StockMovement;
use App\Http\Controllers\Controller;
use App\Http\Requests\Core\StoreStockAdjustmentRequest;
use App\Http\Requests\Core\StoreStockTransferRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class InventoryController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:inventory.view')->only(['index', 'movements']);
        $this->middleware('can:inventory.adjust')->only(['adjustForm', 'transferForm']);
    }

    public function index(Request $request): View
    {
        $branch = $this->resolveBranch($request);

        $stocks = $branch
            ? BranchStock::where('branch_id', $branch->id)->with('product.category')->get()->sortBy('product.name')
            : collect();

        return view('core.inventory.index', [
            'branches' => $this->accessibleBranches(),
            'branch' => $branch,
            'stocks' => $stocks,
        ]);
    }

    public function movements(Request $request): View
    {
        $branch = $this->resolveBranch($request);

        $movements = $branch
            ? StockMovement::where('branch_id', $branch->id)->with(['product', 'performedBy'])->latest('occurred_at')->paginate(30)->withQueryString()
            : collect();

        return view('core.inventory.movements', [
            'branches' => $this->accessibleBranches(),
            'branch' => $branch,
            'movements' => $movements,
        ]);
    }

    public function adjustForm(Request $request): View
    {
        return view('core.inventory.adjust', [
            'branches' => $this->accessibleBranches(),
            'branch' => $this->resolveBranch($request),
            'products' => Product::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function adjust(StoreStockAdjustmentRequest $request, AdjustStock $action): RedirectResponse
    {
        $quantity = (float) $request->validated('quantity');
        if ($request->validated('type') === 'adjustment' && $request->validated('direction') === 'decrease') {
            $quantity = -$quantity;
        }

        $action->execute(
            branch: Branch::findOrFail($request->validated('branch_id')),
            product: Product::findOrFail($request->validated('product_id')),
            type: $request->validated('type'),
            quantity: $quantity,
            notes: $request->validated('notes'),
            performedBy: Auth::guard('web')->id(),
        );

        return redirect()->route('inventory.index', ['branch_id' => $request->validated('branch_id')])->with('status', 'Stock adjusted.');
    }

    public function transferForm(Request $request): View
    {
        return view('core.inventory.transfer', [
            'branches' => $this->accessibleBranches(),
            'branch' => $this->resolveBranch($request),
            'products' => Product::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function transfer(StoreStockTransferRequest $request, TransferStock $action): RedirectResponse
    {
        $action->execute(
            fromBranch: Branch::findOrFail($request->validated('from_branch_id')),
            toBranch: Branch::findOrFail($request->validated('to_branch_id')),
            lines: $request->validated('lines'),
            notes: $request->validated('notes'),
            createdBy: Auth::guard('web')->id(),
        );

        return redirect()->route('inventory.index', ['branch_id' => $request->validated('from_branch_id')])->with('status', 'Stock transferred.');
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
