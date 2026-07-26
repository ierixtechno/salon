<?php

namespace App\Http\Controllers\BeautyParlour;

use App\Domain\BeautyParlour\Models\SkinConsultation;
use App\Domain\Core\Models\Branch;
use App\Domain\Core\Models\Customer;
use App\Http\Controllers\Controller;
use App\Http\Requests\BeautyParlour\StoreSkinConsultationRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class SkinConsultationController extends Controller
{
    public function index(Customer $customer): View
    {
        return view('beauty-parlour.skin-consultations.index', [
            'customer' => $customer,
            'consultations' => SkinConsultation::where('customer_id', $customer->id)
                ->with(['branch', 'consultant'])
                ->orderByDesc('consultation_date')
                ->get(),
        ]);
    }

    public function create(Customer $customer): View
    {
        return view('beauty-parlour.skin-consultations.create', [
            'customer' => $customer,
            'branches' => Branch::where('is_active', true)->get()->filter(fn (Branch $branch) => $branch->hasModuleEnabled('beauty'))->values(),
        ]);
    }

    public function store(StoreSkinConsultationRequest $request, Customer $customer): RedirectResponse
    {
        abort_if($customer->isErased(), 409, 'This customer\'s data has been erased and can no longer be edited.');

        SkinConsultation::create([
            ...$request->validated(),
            'customer_id' => $customer->id,
        ]);

        return redirect()->route('beauty.consultations.index', $customer)->with('status', 'Consultation recorded.');
    }
}
