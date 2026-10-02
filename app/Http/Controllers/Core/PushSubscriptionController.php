<?php

namespace App\Http\Controllers\Core;

use App\Domain\Core\Models\PushSubscription;
use App\Domain\Core\Support\WebPushSender;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** The signed-in user's own devices only — no permission gate, same as "my notifications". */
class PushSubscriptionController extends Controller
{
    public function key(): JsonResponse
    {
        return response()->json(['enabled' => WebPushSender::configured(), 'key' => config('webpush.public_key')]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless(WebPushSender::configured(), 422, 'Push notifications are not set up on this server.');

        $data = $request->validate([
            'endpoint' => ['required', 'url:https', 'max:2000'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
        ]);

        $user = Auth::guard('web')->user();

        // A device that switches accounts re-subscribes under the new user.
        PushSubscription::updateOrCreate(['endpoint_hash' => hash('sha256', $data['endpoint'])], [
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'endpoint' => $data['endpoint'],
            'p256dh' => $data['keys']['p256dh'],
            'auth_token' => $data['keys']['auth'],
            'user_agent' => str((string) $request->userAgent())->limit(250, '')->toString(),
        ]);

        return response()->json(['subscribed' => true]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate(['endpoint' => ['required', 'string', 'max:2000']]);

        PushSubscription::where('user_id', Auth::guard('web')->id())
            ->where('endpoint_hash', hash('sha256', $data['endpoint']))
            ->delete();

        return response()->json(['subscribed' => false]);
    }
}
