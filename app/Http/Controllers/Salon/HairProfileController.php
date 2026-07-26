<?php

namespace App\Http\Controllers\Salon;

use App\Domain\Core\Models\Customer;
use App\Domain\Salon\Models\HairProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Salon\UpdateHairProfileRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class HairProfileController extends Controller
{
    public function edit(Customer $customer): View
    {
        return view('salon.hair-profile.edit', [
            'customer' => $customer,
            'profile' => HairProfile::firstOrNew(['customer_id' => $customer->id]),
        ]);
    }

    public function update(UpdateHairProfileRequest $request, Customer $customer): RedirectResponse
    {
        abort_if($customer->isErased(), 409, 'This customer\'s data has been erased and can no longer be edited.');

        $profile = HairProfile::firstOrNew(['customer_id' => $customer->id]);
        $profile->fill($request->validated());
        $profile->save();

        return back()->with('status', 'Hair profile updated.');
    }
}
