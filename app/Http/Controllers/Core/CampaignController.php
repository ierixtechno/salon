<?php

namespace App\Http\Controllers\Core;

use App\Domain\Core\Actions\SendCampaign;
use App\Domain\Core\Models\Campaign;
use App\Domain\Core\Models\CustomerSegment;
use App\Domain\Core\Models\NotificationTemplate;
use App\Domain\Platform\Models\Tenant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Core\StoreCampaignRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class CampaignController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:marketing.campaigns.view')->only(['index']);
        $this->middleware('can:marketing.campaigns.create')->only(['create', 'store']);
        $this->middleware('can:marketing.campaigns.send')->only(['send', 'cancel']);
    }

    public function index(): View
    {
        return view('core.campaigns.index', [
            'campaigns' => Campaign::with(['template', 'segment'])->withCount('recipients')->latest()->paginate(20),
            'whatsappCreditBalance' => Tenant::find(Auth::user()->tenant_id)?->whatsappCreditBalance() ?? 0,
        ]);
    }

    public function create(): View
    {
        return view('core.campaigns.create', [
            'segments' => CustomerSegment::orderBy('name')->get(),
            'templates' => NotificationTemplate::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(StoreCampaignRequest $request): RedirectResponse
    {
        $template = NotificationTemplate::findOrFail($request->validated('template_id'));

        $campaign = new Campaign([
            'name' => $request->validated('name'),
            'type' => 'manual',
            'channel' => $template->channel,
            'template_id' => $template->id,
            'segment_id' => $request->validated('segment_id'),
            'scheduled_at' => $request->validated('scheduled_at'),
            'created_by' => Auth::guard('web')->id(),
        ]);
        $campaign->status = $request->validated('scheduled_at') ? 'scheduled' : 'draft';
        $campaign->save();

        return redirect()->route('campaigns.index')->with('status', 'Campaign created.');
    }

    public function send(Campaign $campaign, SendCampaign $action): RedirectResponse
    {
        $action->execute($campaign);

        return back()->with('status', 'Campaign sent.');
    }

    public function cancel(Campaign $campaign): RedirectResponse
    {
        abort_unless($campaign->canTransitionTo('cancelled'), 409, "Cannot cancel a campaign that is currently {$campaign->status}.");

        $campaign->status = 'cancelled';
        $campaign->save();

        return back()->with('status', 'Campaign cancelled.');
    }
}
