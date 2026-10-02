<?php

namespace App\Domain\Core\Support;

use App\Domain\Core\Models\PushSubscription;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * Thin wrapper over minishlink/web-push (CLAUDE.md §42: external services sit
 * behind our own class so tests and a future provider swap don't touch callers).
 */
class WebPushSender
{
    public static function configured(): bool
    {
        return filled(config('webpush.public_key')) && filled(config('webpush.private_key'));
    }

    /**
     * @return int|null HTTP status from the push service (201 = accepted,
     *                  404/410 = subscription is gone), null on a transport failure.
     */
    public function send(PushSubscription $subscription, array $payload): ?int
    {
        $webPush = new WebPush(['VAPID' => [
            'subject' => config('webpush.subject'),
            'publicKey' => config('webpush.public_key'),
            'privateKey' => config('webpush.private_key'),
        ]], ['TTL' => 3600], 10);

        $report = $webPush->sendOneNotification(
            Subscription::create([
                'endpoint' => $subscription->endpoint,
                'publicKey' => $subscription->p256dh,
                'authToken' => $subscription->auth_token,
            ]),
            json_encode($payload, JSON_UNESCAPED_UNICODE),
        );

        if ($report->isSuccess()) {
            return 201;
        }

        return $report->getResponse()?->getStatusCode();
    }
}
