<?php

namespace App\Http\Controllers\Tattoo;

use App\Domain\Core\Models\Branch;
use App\Domain\Core\Models\Customer;
use App\Domain\Tattoo\Models\TattooConsultation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tattoo\StoreTattooConsultationRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class TattooConsultationController extends Controller
{
    public function index(Customer $customer): View
    {
        return view('tattoo.tattoo-consultations.index', [
            'customer' => $customer,
            'consultations' => TattooConsultation::where('customer_id', $customer->id)
                ->with(['branch', 'consultant'])
                ->orderByDesc('consultation_date')
                ->get(),
        ]);
    }

    public function create(Customer $customer): View
    {
        return view('tattoo.tattoo-consultations.create', [
            'customer' => $customer,
            'branches' => Branch::where('is_active', true)->get()->filter(fn (Branch $branch) => $branch->hasModuleEnabled('tattoo'))->values(),
        ]);
    }

    public function store(StoreTattooConsultationRequest $request, Customer $customer): RedirectResponse
    {
        abort_if($customer->isErased(), 409, 'This customer\'s data has been erased and can no longer be edited.');

        TattooConsultation::create([
            ...$request->validated(),
            'customer_id' => $customer->id,
        ]);

        return redirect()->route('tattoo.consultations.index', $customer)->with('status', 'Consultation recorded.');
    }
}
