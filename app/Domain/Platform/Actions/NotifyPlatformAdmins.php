<?php

namespace App\Domain\Platform\Actions;

use App\Domain\Platform\Models\PlatformAdmin;
use App\Domain\Platform\Models\PlatformNotification;
use App\Mail\NotificationMail;
use Illuminate\Support\Facades\Mail;

/**
 * Alerts every active Super Admin: an entry in their Platform bell, and
 * (unless `$email` is false) an email too. Used for the things only the
 * platform operator can act on — a new quotation or payment, an upcoming
 * renewal, a new self-signup, a failed backup, the daily error digest.
 *
 * Deliberately NOT routed through SendNotification/notification_logs: that
 * pipeline is tenant-scoped (every row needs a tenant_id) and these
 * messages have no tenant. Email is queued (not sent inline) so a slow or
 * failing SMTP server can never slow down or break the request/command that
 * triggered it (CLAUDE.md §37/§43); a failure to alert is reported and
 * swallowed for the same reason — the alert is secondary to the thing it is
 * alerting about.
 */
class NotifyPlatformAdmins
{
    /**
     * @return int number of admins alerted
     */
    public function execute(string $subject, string $body, ?string $url = null, bool $email = true, string $kind = 'system'): int
    {
        $admins = PlatformAdmin::where('is_active', true)->get(['id', 'email']);

        if ($admins->isEmpty()) {
            return 0;
        }

        try {
            $now = now();
            PlatformNotification::insert($admins->map(fn ($admin) => [
                'platform_admin_id' => $admin->id,
                'kind' => $kind,
                'title' => str($subject)->limit(250, '')->toString(),
                'body' => $body,
                'url' => $url,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all());
        } catch (\Throwable $e) {
            report($e);
        }

        if ($email) {
            $emails = $admins->pluck('email')->filter()->unique()->values()->all();

            try {
                if ($emails !== []) {
                    Mail::to($emails)->queue(new NotificationMail($subject, $body));
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $admins->count();
    }
}
