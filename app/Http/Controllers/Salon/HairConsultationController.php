<?php

namespace App\Http\Controllers\Salon;

use App\Domain\Core\Models\Branch;
use App\Domain\Core\Models\Customer;
use App\Domain\Salon\Models\HairConsultation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Salon\StoreHairConsultationRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class HairConsultationController extends Controller
{
    public function index(Customer $customer): View
    {
        return view('salon.hair-consultations.index', [
            'customer' => $customer,
            'consultations' => HairConsultation::where('customer_id', $customer->id)
                ->with(['branch', 'consultant'])
                ->orderByDesc('consultation_date')
                ->get(),
        ]);
    }

    public function create(Customer $customer): View
    {
        return view('salon.hair-consultations.create', [
            'customer' => $customer,
            'branches' => Branch::where('is_active', true)->get()->filter(fn (Branch $branch) => $branch->hasModuleEnabled('salon'))->values(),
        ]);
    }

    public function store(StoreHairConsultationRequest $request, Customer $customer): RedirectResponse
    {
        abort_if($customer->isErased(), 409, 'This customer\'s data has been erased and can no longer be edited.');

        HairConsultation::create([
            ...$request->validated(),
            'customer_id' => $customer->id,
        ]);

        return redirect()->route('salon.consultations.index', $customer)->with('status', 'Consultation recorded.');
    }
}
