<?php

namespace App\Jobs;

use App\Domain\Core\Actions\ChargeWhatsappCredit;
use App\Domain\Core\Contracts\SmsProvider;
use App\Domain\Core\Contracts\WhatsAppProvider;
use App\Domain\Core\Models\NotificationLog;
use App\Domain\Core\Scopes\TenantScope;
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
 *
 * WhatsApp is additionally tenant-credit-gated (ChargeWhatsappCredit) — a
 * tenant with no balance gets 'skipped', never reaches the provider, and
 * is never charged; a successful send debits exactly 1 credit, a failed
 * one debits nothing.
 */
class DeliverNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $notificationLogId) {}

    public function handle(SmsProvider $smsProvider, WhatsAppProvider $whatsAppProvider, ChargeWhatsappCredit $whatsappCredit): void
    {
        // A real queue worker process has no authenticated session, so
        // current_tenant_id() is null and TenantScope's fail-closed
        // behaviour would otherwise make this ALWAYS find nothing — this
        // job already has the exact row id it wants (never derived from
        // request input), so bypassing the scope here is safe, mirroring
        // ProcessSubscriptionRenewals::alreadyNotifiedToday()'s identical
        // reasoning for the same class.
        $log = NotificationLog::withoutGlobalScope(TenantScope::class)->find($this->notificationLogId);
        if (! $log || $log->status !== 'queued') {
            return;
        }

        if ($log->channel === 'whatsapp' && ! $whatsappCredit->hasBalance($log->tenant_id)) {
            $log->status = 'skipped';
            $log->error_message = 'Insufficient WhatsApp credits.';
            $log->save();

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

            if ($log->channel === 'whatsapp') {
                $whatsappCredit->chargeForSuccessfulSend($log->tenant_id, $log->id);
            }
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
