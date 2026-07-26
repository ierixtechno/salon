<?php

namespace App\Http\Controllers\Core;

use App\Domain\Core\Actions\AddInvoiceLine;
use App\Domain\Core\Actions\CheckoutSale;
use App\Domain\Core\Actions\CreateDraftInvoice;
use App\Domain\Core\Actions\ProcessRefund;
use App\Domain\Core\Actions\RecordPayment;
use App\Domain\Core\Actions\RemoveInvoiceLine;
use App\Domain\Core\Actions\VoidInvoice;
use App\Domain\Core\Models\Appointment;
use App\Domain\Core\Models\Branch;
use App\Domain\Core\Models\Customer;
use App\Domain\Core\Models\Invoice;
use App\Domain\Core\Models\InvoiceLine;
use App\Domain\Core\Models\Service;
use App\Domain\Core\Models\ServiceVariant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Core\StoreDraftInvoiceRequest;
use App\Http\Requests\Core\StoreInvoiceLineRequest;
use App\Http\Requests\Core\StorePaymentRequest;
use App\Http\Requests\Core\StoreRefundRequest;
use App\Http\Requests\Core\VoidInvoiceRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class InvoiceController extends Controller
{
    public function __construct()
    {
        // destroy (discardDraft) is a custom action authorized inline
        // below, same reasoning as AppointmentController: there is no
        // `delete` ability on InvoicePolicy since a finalized invoice is
        // never deleted, only voided/refunded (CLAUDE.md §45/§48).
        $this->authorizeResource(Invoice::class, 'invoice');
    }

    public function index(Request $request): View
    {
        $branch = $this->resolveBranch($request);

        $invoices = $branch
            ? Invoice::where('branch_id', $branch->id)
                ->with(['customer'])
                ->latest('created_at')
                ->paginate(20)
                ->withQueryString()
            : collect();

        return view('core.invoices.index', [
            'branches' => $this->accessibleBranches(),
            'branch' => $branch,
            'invoices' => $invoices,
        ]);
    }

    public function create(Request $request): View
    {
        return view('core.invoices.create', [
            'branches' => $this->accessibleBranches(),
            'branch' => $this->resolveBranch($request),
            'customers' => Customer::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(StoreDraftInvoiceRequest $request, CreateDraftInvoice $action): RedirectResponse
    {
        $invoice = $action->execute(
            branch: Branch::findOrFail($request->validated('branch_id')),
            customer: Customer::findOrFail($request->validated('customer_id')),
            createdBy: Auth::guard('web')->id(),
            notes: $request->validated('notes'),
        );

        return redirect()->route('invoices.show', $invoice)->with('status', 'Draft sale started.');
    }

    public function show(Invoice $invoice): View
    {
        $invoice->load(['lines.service', 'lines.serviceVariant', 'payments.recordedBy', 'refunds.refundedBy', 'customer', 'branch']);

        return view('core.invoices.show', [
            'invoice' => $invoice,
            'completedAppointments' => $invoice->status === 'draft'
                ? Appointment::where('customer_id', $invoice->customer_id)
                    ->where('branch_id', $invoice->branch_id)
                    ->where('status', 'completed')
                    ->whereDoesntHave('invoiceLine')
                    ->with('service')
                    ->orderByDesc('starts_at')
                    ->get()
                : collect(),
            'services' => $invoice->status === 'draft'
                ? Service::where('is_active', true)->with('variants')->get()->filter(fn (Service $s) => $s->isAvailableAtBranch($invoice->branch))->values()
                : collect(),
        ]);
    }

    public function discardDraft(Invoice $invoice): RedirectResponse
    {
        $this->authorize('update', $invoice);
        abort_unless($invoice->status === 'draft', 409, 'Only a draft sale can be discarded.');

        $invoice->delete();

        return redirect()->route('invoices.index', ['branch_id' => $invoice->branch_id])->with('status', 'Draft discarded.');
    }

    public function addLine(StoreInvoiceLineRequest $request, Invoice $invoice, AddInvoiceLine $action): RedirectResponse
    {
        $appointment = $request->validated('appointment_id') ? Appointment::findOrFail($request->validated('appointment_id')) : null;
        $service = $appointment ? $appointment->service : Service::findOrFail($request->validated('service_id'));
        $variant = $appointment
            ? $appointment->serviceVariant
            : ($request->validated('service_variant_id') ? ServiceVariant::findOrFail($request->validated('service_variant_id')) : null);

        $action->execute(
            invoice: $invoice,
            service: $service,
            variant: $variant,
            appointment: $appointment,
            quantity: (int) ($request->validated('quantity') ?? 1),
            discountAmount: (float) ($request->validated('discount_amount') ?? 0),
        );

        return back()->with('status', 'Item added.');
    }

    public function removeLine(Invoice $invoice, InvoiceLine $invoiceLine, RemoveInvoiceLine $action): RedirectResponse
    {
        $this->authorize('update', $invoice);
        $action->execute($invoice, $invoiceLine);

        return back()->with('status', 'Item removed.');
    }

    public function checkout(Invoice $invoice, CheckoutSale $action): RedirectResponse
    {
        $this->authorize('update', $invoice);
        $action->execute($invoice);

        return redirect()->route('invoices.show', $invoice)->with('status', 'Sale finalized.');
    }

    public function storePayment(StorePaymentRequest $request, Invoice $invoice, RecordPayment $action): RedirectResponse
    {
        $action->execute(
            invoice: $invoice,
            method: $request->validated('method'),
            amount: (float) $request->validated('amount'),
            idempotencyKey: $request->validated('idempotency_key'),
            reference: $request->validated('reference'),
            tipAmount: (float) ($request->validated('tip_amount') ?? 0),
            recordedBy: Auth::guard('web')->id(),
        );

        return back()->with('status', 'Payment recorded.');
    }

    public function storeRefund(StoreRefundRequest $request, Invoice $invoice, ProcessRefund $action): RedirectResponse
    {
        $action->execute(
            invoice: $invoice,
            amount: (float) $request->validated('amount'),
            method: $request->validated('method'),
            reason: $request->validated('reason'),
            refundedBy: Auth::guard('web')->id(),
        );

        return back()->with('status', 'Refund recorded.');
    }

    public function void(VoidInvoiceRequest $request, Invoice $invoice, VoidInvoice $action): RedirectResponse
    {
        $action->execute($invoice, $request->validated('reason'));

        return back()->with('status', 'Invoice voided.');
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
