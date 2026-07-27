<?php

namespace App\Http\Controllers\Core;

use App\Domain\Core\Actions\CancelPurchaseOrder;
use App\Domain\Core\Actions\CreatePurchaseOrder;
use App\Domain\Core\Actions\OrderPurchaseOrder;
use App\Domain\Core\Actions\ReceiveGoods;
use App\Domain\Core\Models\Branch;
use App\Domain\Core\Models\Product;
use App\Domain\Core\Models\PurchaseOrder;
use App\Domain\Core\Models\Supplier;
use App\Http\Controllers\Controller;
use App\Http\Requests\Core\StoreGoodsReceiptRequest;
use App\Http\Requests\Core\StorePurchaseOrderRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class PurchaseOrderController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(PurchaseOrder::class, 'purchase_order');
    }

    public function index(Request $request): View
    {
        $branch = $this->resolveBranch($request);

        $orders = $branch
            ? PurchaseOrder::where('branch_id', $branch->id)->with('supplier')->latest()->paginate(20)->withQueryString()
            : collect();

        return view('core.purchase-orders.index', [
            'branches' => $this->accessibleBranches(),
            'branch' => $branch,
            'orders' => $orders,
        ]);
    }

    public function create(Request $request): View
    {
        return view('core.purchase-orders.create', [
            'branches' => $this->accessibleBranches(),
            'branch' => $this->resolveBranch($request),
            'suppliers' => Supplier::where('is_active', true)->orderBy('name')->get(),
            'products' => Product::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(StorePurchaseOrderRequest $request, CreatePurchaseOrder $action): RedirectResponse
    {
        $order = $action->execute(
            branch: Branch::findOrFail($request->validated('branch_id')),
            supplier: Supplier::findOrFail($request->validated('supplier_id')),
            lines: $request->validated('lines'),
            expectedDate: $request->validated('expected_date'),
            notes: $request->validated('notes'),
            createdBy: Auth::guard('web')->id(),
        );

        return redirect()->route('purchase-orders.show', $order)->with('status', 'Purchase order created.');
    }

    public function show(PurchaseOrder $purchaseOrder): View
    {
        $purchaseOrder->load(['lines.product', 'supplier', 'branch', 'goodsReceipts.receivedBy']);

        return view('core.purchase-orders.show', ['order' => $purchaseOrder]);
    }

    public function order(PurchaseOrder $purchaseOrder, OrderPurchaseOrder $action): RedirectResponse
    {
        $this->authorize('update', $purchaseOrder);
        $action->execute($purchaseOrder);

        return back()->with('status', 'Order placed.');
    }

    public function cancel(PurchaseOrder $purchaseOrder, CancelPurchaseOrder $action): RedirectResponse
    {
        $this->authorize('update', $purchaseOrder);
        $action->execute($purchaseOrder);

        return back()->with('status', 'Order cancelled.');
    }

    public function receiveGoods(StoreGoodsReceiptRequest $request, PurchaseOrder $purchaseOrder, ReceiveGoods $action): RedirectResponse
    {
        $action->execute(
            purchaseOrder: $purchaseOrder,
            lines: $request->validated('lines'),
            supplierInvoiceNumber: $request->validated('supplier_invoice_number'),
            supplierInvoiceAmount: $request->validated('supplier_invoice_amount') !== null ? (float) $request->validated('supplier_invoice_amount') : null,
            receivedBy: Auth::guard('web')->id(),
        );

        return back()->with('status', 'Goods received.');
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
