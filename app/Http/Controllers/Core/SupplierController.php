<?php

namespace App\Http\Controllers\Core;

use App\Domain\Core\Actions\RecordSupplierPayment;
use App\Domain\Core\Models\Supplier;
use App\Http\Controllers\Controller;
use App\Http\Requests\Core\StoreSupplierPaymentRequest;
use App\Http\Requests\Core\StoreSupplierRequest;
use App\Http\Requests\Core\UpdateSupplierRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class SupplierController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Supplier::class, 'supplier');
    }

    public function index(): View
    {
        return view('core.suppliers.index', [
            'suppliers' => Supplier::orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('core.suppliers.create');
    }

    public function store(StoreSupplierRequest $request): RedirectResponse
    {
        Supplier::create($request->validated());

        return redirect()->route('suppliers.index')->with('status', 'Supplier added.');
    }

    public function edit(Supplier $supplier): View
    {
        return view('core.suppliers.edit', [
            'supplier' => $supplier,
            'purchaseOrders' => $supplier->purchaseOrders()->latest()->limit(10)->get(),
            'payments' => $supplier->payments()->with('paidBy')->latest('paid_at')->get(),
        ]);
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($request->validated());

        return redirect()->route('suppliers.index')->with('status', 'Supplier updated.');
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        $supplier->update(['is_active' => false]);

        return redirect()->route('suppliers.index')->with('status', 'Supplier deactivated.');
    }

    public function storePayment(StoreSupplierPaymentRequest $request, Supplier $supplier, RecordSupplierPayment $action): RedirectResponse
    {
        $action->execute(
            supplier: $supplier,
            purchaseOrder: $request->validated('purchase_order_id') ? $supplier->purchaseOrders()->findOrFail($request->validated('purchase_order_id')) : null,
            amount: (float) $request->validated('amount'),
            method: $request->validated('method'),
            reference: $request->validated('reference'),
            paidBy: Auth::guard('web')->id(),
        );

        return back()->with('status', 'Payment recorded.');
    }
}
