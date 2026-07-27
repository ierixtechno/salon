<?php

namespace App\Jobs;

use App\Domain\Core\Contracts\SmsProvider;
use App\Domain\Core\Contracts\WhatsAppProvider;
use App\Domain\Core\Models\NotificationLog;
use App\Mail\NotificationMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

/**
 * Delivers a single already-created NotificationLog row (email/sms/
 * whatsapp only — `in_app` is written as `sent` synchronously by
 * SendNotification and never reaches this job). One attempt: a failure
 * is recorded on the log and the job does not rethrow, so Laravel's queue
 * worker doesn't auto-retry into a duplicate-send situation (CLAUDE.md
 * §36 Queue Failure Handling) — a failed send can be seen and manually
 * re-triggered from the notification log, but is never silently retried.
 */
class DeliverNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $notificationLogId) {}

    public function handle(SmsProvider $smsProvider, WhatsAppProvider $whatsAppProvider): void
    {
        $log = NotificationLog::find($this->notificationLogId);
        if (! $log || $log->status !== 'queued') {
            return;
        }

        try {
            $provider = match ($log->channel) {
                'email' => $this->sendEmail($log),
                'sms' => $smsProvider->send($log->to_address, $log->body),
                'whatsapp' => $whatsAppProvider->send($log->to_address, $log->body),
                default => throw new \RuntimeException("Unsupported notification channel: {$log->channel}"),
            };

            $log->status = 'sent';
            $log->provider = is_string($provider) ? $provider : config("notifications.{$log->channel}.driver", 'default');
            $log->sent_at = now();
            $log->save();
        } catch (\Throwable $e) {
            $log->status = 'failed';
            $log->error_message = $e->getMessage();
            $log->save();
        }
    }

    private function sendEmail(NotificationLog $log): string
    {
        Mail::to($log->to_address)->send(new NotificationMail($log->subject ?? '', $log->body));

        return config('mail.default');
    }
}
