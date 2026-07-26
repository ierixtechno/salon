<?php

namespace App\Http\Controllers\Core;

use App\Domain\Core\Actions\EraseCustomer;
use App\Domain\Core\Actions\RecordCustomerConsent;
use App\Domain\Core\Models\Customer;
use App\Http\Controllers\Controller;
use App\Http\Requests\Core\RecordCustomerConsentRequest;
use App\Http\Requests\Core\StoreCustomerNoteRequest;
use App\Http\Requests\Core\StoreCustomerRequest;
use App\Http\Requests\Core\UpdateCustomerRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class CustomerController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Customer::class, 'customer');
    }

    public function index(Request $request): View
    {
        $search = $request->string('search')->trim()->value();

        $customers = Customer::query()
            ->when($search, fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('core.customers.index', ['customers' => $customers, 'search' => $search]);
    }

    public function create(): View
    {
        return view('core.customers.create');
    }

    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['tags'] = $this->parseTags($data['tags'] ?? null);

        $customer = Customer::create($data);

        return redirect()->route('customers.edit', $customer)->with('status', 'Customer added.');
    }

    public function edit(Customer $customer): View
    {
        return view('core.customers.edit', [
            'customer' => $customer,
            'notes' => $customer->notes()->with('user')->get(),
            'consents' => $customer->consents()->with('recordedBy')->get(),
        ]);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $this->abortIfErased($customer);

        $data = $request->validated();
        $data['tags'] = $this->parseTags($data['tags'] ?? null);

        $customer->update($data);

        return back()->with('status', 'Customer updated.');
    }

    /**
     * Deactivates only — never hard-deletes (same pattern as Branch and
     * EmployeeProfile). DPDP erasure is the separate erase() action below.
     */
    public function destroy(Customer $customer): RedirectResponse
    {
        $this->authorize('deactivate', $customer);

        $customer->update(['is_active' => false]);

        return redirect()->route('customers.index')->with('status', 'Customer deactivated.');
    }

    public function storeNote(StoreCustomerNoteRequest $request, Customer $customer): RedirectResponse
    {
        $this->authorize('update', $customer);
        $this->abortIfErased($customer);

        $customer->notes()->create([
            'tenant_id' => $customer->tenant_id,
            'user_id' => Auth::guard('web')->id(),
            'body' => $request->validated('body'),
        ]);

        return back()->with('status', 'Note added.');
    }

    public function recordConsent(RecordCustomerConsentRequest $request, Customer $customer, RecordCustomerConsent $recordCustomerConsent): RedirectResponse
    {
        $this->authorize('update', $customer);
        $this->abortIfErased($customer);

        $recordCustomerConsent->execute(
            $customer,
            $request->validated('purpose'),
            $request->validated('granted'),
            Auth::guard('web')->id(),
            $request->validated('notes'),
        );

        return back()->with('status', 'Consent recorded.');
    }

    public function erase(Customer $customer, EraseCustomer $eraseCustomer): RedirectResponse
    {
        $this->authorize('erase', $customer);

        $eraseCustomer->execute($customer, Auth::guard('web')->id());

        return redirect()->route('customers.index')->with('status', 'Customer data erased.');
    }

    /**
     * A business-rule conflict (CLAUDE.md §39/§26 error taxonomy — not an
     * authorization failure): an erased customer can still be *viewed*
     * (the edit page renders a read-only "erased" notice), just not
     * mutated further.
     */
    private function abortIfErased(Customer $customer): void
    {
        abort_if($customer->isErased(), 409, 'This customer\'s data has been erased and can no longer be edited.');
    }

    private function parseTags(?string $tags): array
    {
        if (blank($tags)) {
            return [];
        }

        return collect(explode(',', $tags))
            ->map(fn ($tag) => Str::trim($tag))
            ->filter()
            ->values()
            ->all();
    }
}
