<?php

namespace App\Http\Controllers\Core;

use App\Domain\Core\Models\CampaignAutomation;
use App\Domain\Core\Models\NotificationTemplate;
use App\Http\Controllers\Controller;
use App\Http\Requests\Core\UpdateCampaignAutomationRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class CampaignAutomationController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:marketing.automations.manage');
    }

    public function index(): View
    {
        $existing = CampaignAutomation::whereIn('type', CampaignAutomation::TYPES)->get()->keyBy('type');

        return view('core.campaign-automations.index', [
            'automations' => collect(CampaignAutomation::TYPES)->map(fn (string $type) => $existing->get($type) ?? new CampaignAutomation(['type' => $type, 'is_enabled' => false])),
            'templates' => NotificationTemplate::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(UpdateCampaignAutomationRequest $request, string $type): RedirectResponse
    {
        abort_unless(in_array($type, CampaignAutomation::TYPES, true), 404);

        CampaignAutomation::updateOrCreate(
            ['type' => $type],
            [
                'is_enabled' => $request->boolean('is_enabled'),
                'template_id' => $request->validated('template_id'),
                'threshold_days' => $request->validated('threshold_days'),
            ],
        );

        return back()->with('status', 'Automation settings saved.');
    }
}
