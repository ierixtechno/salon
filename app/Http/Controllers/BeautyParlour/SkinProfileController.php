<?php

namespace App\Http\Controllers\BeautyParlour;

use App\Domain\BeautyParlour\Models\SkinProfile;
use App\Domain\Core\Models\Customer;
use App\Http\Controllers\Controller;
use App\Http\Requests\BeautyParlour\UpdateSkinProfileRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class SkinProfileController extends Controller
{
    public function edit(Customer $customer): View
    {
        return view('beauty-parlour.skin-profile.edit', [
            'customer' => $customer,
            'profile' => SkinProfile::firstOrNew(['customer_id' => $customer->id]),
        ]);
    }

    public function update(UpdateSkinProfileRequest $request, Customer $customer): RedirectResponse
    {
        abort_if($customer->isErased(), 409, 'This customer\'s data has been erased and can no longer be edited.');

        $profile = SkinProfile::firstOrNew(['customer_id' => $customer->id]);
        $profile->fill($request->validated());
        $profile->save();

        return back()->with('status', 'Skin profile updated.');
    }
}
