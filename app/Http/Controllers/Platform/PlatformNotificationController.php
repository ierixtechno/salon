<?php

namespace App\Http\Controllers\Platform;

use App\Domain\Platform\Models\PlatformNotification;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/** The signed-in Super Admin's own notifications only. */
class PlatformNotificationController extends Controller
{
    public function index(): View
    {
        return view('platform.notifications.index', [
            'notifications' => PlatformNotification::where('platform_admin_id', Auth::guard('platform')->id())->latest()->paginate(30),
        ]);
    }

    public function summary(): JsonResponse
    {
        return response()->json(self::bellPayload(Auth::guard('platform')->id()));
    }

    public function markAllRead(): JsonResponse|RedirectResponse
    {
        $id = Auth::guard('platform')->id();

        PlatformNotification::where('platform_admin_id', $id)->whereNull('read_at')->update(['read_at' => now()]);

        return request()->expectsJson() ? response()->json(self::bellPayload($id)) : back();
    }

    /** Opening an item marks it read, then goes to what it is about. */
    public function open(PlatformNotification $platform_notification): RedirectResponse
    {
        abort_unless($platform_notification->platform_admin_id === Auth::guard('platform')->id(), 404);

        $platform_notification->read_at ??= now();
        $platform_notification->save();

        $url = $platform_notification->url;

        return $url && str_starts_with($url, '/') && ! str_starts_with($url, '//')
            ? redirect($url)
            : redirect()->route('platform.notifications.index');
    }

    public static function bellPayload(?int $adminId): array
    {
        $base = fn () => PlatformNotification::where('platform_admin_id', $adminId);

        return [
            'unread' => $base()->whereNull('read_at')->count(),
            'items' => $base()->latest()->limit(8)->get()->map(fn ($n) => [
                'id' => $n->id,
                'title' => $n->title,
                'body' => str((string) $n->body)->limit(90)->toString(),
                'unread' => $n->read_at === null,
                'time' => $n->created_at->diffForHumans(),
                'open' => route('platform.notifications.open', $n),
            ])->all(),
        ];
    }
}
