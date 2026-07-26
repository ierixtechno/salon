<?php

namespace App\Http\Controllers\Spa;

use App\Domain\Core\Models\Branch;
use App\Domain\Core\Models\Customer;
use App\Domain\Spa\Models\SpaConsultation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Spa\StoreSpaConsultationRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class SpaConsultationController extends Controller
{
    public function index(Customer $customer): View
    {
        return view('spa.spa-consultations.index', [
            'customer' => $customer,
            'consultations' => SpaConsultation::where('customer_id', $customer->id)
                ->with(['branch', 'consultant'])
                ->orderByDesc('consultation_date')
                ->get(),
        ]);
    }

    public function create(Customer $customer): View
    {
        return view('spa.spa-consultations.create', [
            'customer' => $customer,
            'branches' => Branch::where('is_active', true)->get()->filter(fn (Branch $branch) => $branch->hasModuleEnabled('spa'))->values(),
        ]);
    }

    public function store(StoreSpaConsultationRequest $request, Customer $customer): RedirectResponse
    {
        abort_if($customer->isErased(), 409, 'This customer\'s data has been erased and can no longer be edited.');

        SpaConsultation::create([
            ...$request->validated(),
            'customer_id' => $customer->id,
        ]);

        return redirect()->route('spa.consultations.index', $customer)->with('status', 'Consultation recorded.');
    }
}
