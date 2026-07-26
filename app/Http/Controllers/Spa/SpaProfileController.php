<?php

namespace App\Http\Controllers\Spa;

use App\Domain\Core\Models\Customer;
use App\Domain\Spa\Models\SpaProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Spa\UpdateSpaProfileRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class SpaProfileController extends Controller
{
    public function edit(Customer $customer): View
    {
        return view('spa.spa-profile.edit', [
            'customer' => $customer,
            'profile' => SpaProfile::firstOrNew(['customer_id' => $customer->id]),
        ]);
    }

    public function update(UpdateSpaProfileRequest $request, Customer $customer): RedirectResponse
    {
        abort_if($customer->isErased(), 409, 'This customer\'s data has been erased and can no longer be edited.');

        $profile = SpaProfile::firstOrNew(['customer_id' => $customer->id]);
        $profile->fill($request->validated());
        $profile->save();

        return back()->with('status', 'Spa profile updated.');
    }
}
