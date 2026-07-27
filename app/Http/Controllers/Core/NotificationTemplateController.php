<?php

namespace App\Http\Controllers\Core;

use App\Domain\Core\Models\NotificationTemplate;
use App\Http\Controllers\Controller;
use App\Http\Requests\Core\StoreNotificationTemplateRequest;
use App\Http\Requests\Core\UpdateNotificationTemplateRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class NotificationTemplateController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:marketing.templates.manage');
    }

    public function index(): View
    {
        return view('core.notification-templates.index', [
            'templates' => NotificationTemplate::orderBy('channel')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('core.notification-templates.create');
    }

    public function store(StoreNotificationTemplateRequest $request): RedirectResponse
    {
        NotificationTemplate::create($request->validated());

        return redirect()->route('notification-templates.index')->with('status', 'Template created.');
    }

    public function edit(NotificationTemplate $notificationTemplate): View
    {
        return view('core.notification-templates.edit', ['template' => $notificationTemplate]);
    }

    public function update(UpdateNotificationTemplateRequest $request, NotificationTemplate $notificationTemplate): RedirectResponse
    {
        $notificationTemplate->update($request->validated());

        return redirect()->route('notification-templates.index')->with('status', 'Template updated.');
    }

    public function destroy(NotificationTemplate $notificationTemplate): RedirectResponse
    {
        $notificationTemplate->update(['is_active' => false]);

        return redirect()->route('notification-templates.index')->with('status', 'Template deactivated.');
    }
}
