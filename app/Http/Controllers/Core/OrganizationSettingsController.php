<?php

namespace App\Http\Controllers\Core;

use App\Domain\Core\Actions\UpsertBusinessHours;
use App\Domain\Core\Models\BusinessHour;
use App\Domain\Core\Models\BusinessProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Core\UpdateBusinessHoursRequest;
use App\Http\Requests\Core\UpdateBusinessProfileRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class OrganizationSettingsController extends Controller
{
    public function edit(): View
    {
        $tenant = current_tenant();

        $profile = BusinessProfile::firstOrNew(
            ['tenant_id' => $tenant->id],
            ['display_name' => $tenant->name],
        );

        $existingHours = BusinessHour::where('owner_type', $tenant->getMorphClass())
            ->where('owner_id', $tenant->id)
            ->get()
            ->keyBy('day_of_week');

        $hours = collect(range(0, 6))->map(fn ($day) => $existingHours->get($day) ?? new BusinessHour([
            'day_of_week' => $day,
            'opens_at' => '09:00',
            'closes_at' => '18:00',
            'is_closed' => false,
        ]));

        return view('core.settings.organization', [
            'profile' => $profile,
            'hours' => $hours,
        ]);
    }

    public function update(UpdateBusinessProfileRequest $request): RedirectResponse
    {
        $tenant = current_tenant();

        BusinessProfile::updateOrCreate(
            ['tenant_id' => $tenant->id],
            $request->validated(),
        );

        return back()->with('status', 'Business profile updated.');
    }

    public function updateHours(UpdateBusinessHoursRequest $request, UpsertBusinessHours $upsertBusinessHours): RedirectResponse
    {
        $tenant = current_tenant();

        $upsertBusinessHours->execute($tenant, $tenant->id, $request->validated('hours'));

        return back()->with('status', 'Business hours updated.');
    }
}
