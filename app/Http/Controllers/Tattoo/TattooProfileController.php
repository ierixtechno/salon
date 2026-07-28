<?php

namespace App\Http\Controllers\Tattoo;

use App\Domain\Core\Models\Customer;
use App\Domain\Tattoo\Models\TattooProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tattoo\UpdateTattooProfileRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class TattooProfileController extends Controller
{
    public function edit(Customer $customer): View
    {
        return view('tattoo.tattoo-profile.edit', [
            'customer' => $customer,
            'profile' => TattooProfile::firstOrNew(['customer_id' => $customer->id]),
        ]);
    }

    public function update(UpdateTattooProfileRequest $request, Customer $customer): RedirectResponse
    {
        abort_if($customer->isErased(), 409, 'This customer\'s data has been erased and can no longer be edited.');

        $profile = TattooProfile::firstOrNew(['customer_id' => $customer->id]);
        $profile->fill($request->validated());
        $profile->save();

        return back()->with('status', 'Tattoo profile updated.');
    }
}
