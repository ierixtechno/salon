<?php

namespace App\Http\Controllers\Core;

use App\Domain\Core\Models\NotificationLog;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Self-service "my notifications" — no permission gate beyond
 * authentication, same precedent as attendance/leave/commission "my"
 * pages, always scoped to the current user (CLAUDE.md §9/§32).
 */
class NotificationController extends Controller
{
    public function index(): View
    {
        $user = Auth::guard('web')->user();

        return view('core.notifications.index', [
            'notifications' => NotificationLog::where('channel', 'in_app')
                ->where('recipient_type', 'user')
                ->where('recipient_id', $user->id)
                ->latest()
                ->paginate(20),
        ]);
    }

    public function markRead(NotificationLog $notificationLog): RedirectResponse
    {
        $user = Auth::guard('web')->user();
        abort_unless($notificationLog->channel === 'in_app' && $notificationLog->recipient_type === 'user' && (int) $notificationLog->recipient_id === $user->id, 403);

        $notificationLog->read_at ??= now();
        $notificationLog->save();

        return back();
    }
}
