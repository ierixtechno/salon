<?php

namespace App\Jobs;

use App\Domain\Core\Models\NotificationLog;
use App\Domain\Core\Models\PushSubscription;
use App\Domain\Core\Scopes\TenantScope;
use App\Domain\Core\Support\WebPushSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Pushes one in-app notification to every device its recipient has enabled.
 * Best effort and single attempt: the notification already exists in the bell,
 * so a failed push must never block or duplicate anything (CLAUDE.md §37).
 */
class SendWebPush implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public int $notificationLogId) {}

    public function handle(WebPushSender $sender): void
    {
        if (! WebPushSender::configured()) {
            return;
        }

        // Queue workers have no session; this job holds the exact row id, never request input.
        $log = NotificationLog::withoutGlobalScope(TenantScope::class)->find($this->notificationLogId);
        if (! $log || $log->channel !== 'in_app' || $log->recipient_type !== 'user') {
            return;
        }

        $payload = [
            'title' => $log->subject ?: config('app.name', 'StyloBiz'),
            'body' => str($log->body)->limit(140)->toString(),
            'url' => route('notifications.my', absolute: false),
            'tag' => 'notification-'.$log->id,
        ];

        PushSubscription::where('tenant_id', $log->tenant_id)->where('user_id', $log->recipient_id)->get()
            ->each(function (PushSubscription $subscription) use ($sender, $payload) {
                try {
                    $status = $sender->send($subscription, $payload);
                } catch (\Throwable $e) {
                    report($e);

                    return;
                }

                if (in_array($status, [404, 410], true)) {
                    $subscription->delete(); // the device uninstalled / revoked permission
                } elseif ($status === 201) {
                    $subscription->forceFill(['last_used_at' => now()])->save();
                }
            });
    }
}
