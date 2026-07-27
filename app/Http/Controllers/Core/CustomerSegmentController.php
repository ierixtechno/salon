<?php

namespace App\Http\Controllers\Core;

use App\Domain\Core\Actions\ResolveSegmentCustomers;
use App\Domain\Core\Models\CustomerSegment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Core\StoreCustomerSegmentRequest;
use App\Http\Requests\Core\UpdateCustomerSegmentRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class CustomerSegmentController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:marketing.segments.manage');
    }

    public function index(ResolveSegmentCustomers $resolve): View
    {
        $segments = CustomerSegment::orderBy('name')->get();
        $tenantId = Auth::guard('web')->user()->tenant_id;

        return view('core.customer-segments.index', [
            'segments' => $segments,
            'counts' => $segments->mapWithKeys(fn (CustomerSegment $s) => [$s->id => $resolve->execute($tenantId, $s)->count()]),
        ]);
    }

    public function create(): View
    {
        return view('core.customer-segments.create');
    }

    public function store(StoreCustomerSegmentRequest $request): RedirectResponse
    {
        CustomerSegment::create([
            'name' => $request->validated('name'),
            'type' => $request->validated('type'),
            'criteria' => $this->criteriaFrom($request->validated()),
        ]);

        return redirect()->route('customer-segments.index')->with('status', 'Segment created.');
    }

    public function edit(CustomerSegment $customerSegment): View
    {
        return view('core.customer-segments.edit', ['segment' => $customerSegment]);
    }

    public function update(UpdateCustomerSegmentRequest $request, CustomerSegment $customerSegment): RedirectResponse
    {
        $customerSegment->update([
            'name' => $request->validated('name'),
            'type' => $request->validated('type'),
            'criteria' => $this->criteriaFrom($request->validated()),
        ]);

        return redirect()->route('customer-segments.index')->with('status', 'Segment updated.');
    }

    public function destroy(CustomerSegment $customerSegment): RedirectResponse
    {
        $customerSegment->delete();

        return redirect()->route('customer-segments.index')->with('status', 'Segment deleted.');
    }

    private function criteriaFrom(array $validated): ?array
    {
        return match ($validated['type']) {
            'tag' => ['tag' => $validated['tag'] ?? null],
            'inactive_days' => ['days' => $validated['days'] ?? null],
            default => null,
        };
    }
}
