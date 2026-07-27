<?php

namespace App\Domain\Core\Actions;

use App\Domain\Core\Models\NotificationLog;
use App\Jobs\DeliverNotification;

/**
 * The single entry point for every outbound notification — transactional
 * or marketing, any channel. `$tenantId` is always explicit rather than
 * relying on BelongsToTenant's session auto-fill, since this is also
 * called from scheduled console commands with no authenticated session
 * (CLAUDE.md §28/§11).
 *
 * `in_app` has no external provider — it's written directly as `sent`
 * (the row's existence in notification_logs *is* the notification; see
 * NotificationController for the recipient's own "my notifications" view)
 * rather than queued through DeliverNotification (CLAUDE.md §43 only
 * applies to genuinely slow/external channels).
 */
class SendNotification
{
    public function execute(
        int $tenantId,
        string $channel,
        string $recipientType,
        int $recipientId,
        ?string $toAddress,
        ?string $subject,
        string $body,
        ?string $referenceType = null,
        ?int $referenceId = null,
    ): NotificationLog {
        abort_unless(in_array($channel, NotificationLog::CHANNELS, true), 422, 'Invalid notification channel.');
        abort_unless(in_array($recipientType, ['customer', 'user'], true), 422, 'Invalid recipient type.');
        abort_if($channel !== 'in_app' && ! $toAddress, 422, 'A destination address is required for this channel.');

        $log = new NotificationLog([
            'channel' => $channel,
            'recipient_type' => $recipientType,
            'recipient_id' => $recipientId,
            'to_address' => $toAddress,
            'subject' => $subject,
            'body' => $body,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
        ]);
        $log->tenant_id = $tenantId;

        if ($channel === 'in_app') {
            $log->status = 'sent';
            $log->sent_at = now();
            $log->save();

            return $log;
        }

        $log->status = 'queued';
        $log->save();

        DeliverNotification::dispatch($log->id);

        return $log;
    }
}
