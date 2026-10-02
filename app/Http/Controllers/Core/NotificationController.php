<?php

namespace App\Http\Controllers\Core;

use App\Domain\Core\Models\NotificationLog;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
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

    /** Feeds the top-bar bell: unread count plus the latest few, current user only. */
    public function summary(): JsonResponse
    {
        return response()->json(self::bellPayload(Auth::guard('web')->user()));
    }

    public function markAllRead(): JsonResponse|RedirectResponse
    {
        $user = Auth::guard('web')->user();

        NotificationLog::where('channel', 'in_app')
            ->where('recipient_type', 'user')
            ->where('recipient_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return request()->expectsJson() ? response()->json(self::bellPayload($user)) : back();
    }

    public static function bellPayload($user): array
    {
        $base = fn () => NotificationLog::where('channel', 'in_app')
            ->where('recipient_type', 'user')
            ->where('recipient_id', $user->id);

        return [
            'unread' => $base()->whereNull('read_at')->count(),
            'items' => $base()->latest()->limit(6)->get(['id', 'subject', 'body', 'read_at', 'created_at'])
                ->map(fn ($n) => [
                    'id' => $n->id,
                    'title' => $n->subject ?: str($n->body)->limit(60)->toString(),
                    'body' => $n->subject ? str($n->body)->limit(90)->toString() : '',
                    'unread' => $n->read_at === null,
                    'time' => $n->created_at->diffForHumans(),
                ])->all(),
        ];
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
