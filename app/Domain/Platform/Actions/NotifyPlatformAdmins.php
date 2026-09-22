<?php

namespace App\Domain\Platform\Actions;

use App\Domain\Platform\Models\PlatformAdmin;
use App\Mail\NotificationMail;
use Illuminate\Support\Facades\Mail;

/**
 * Emails every active Super Admin. Used for the things only the platform
 * operator can act on — a new self-signup waiting for a quotation, a
 * failed backup, the daily error digest.
 *
 * Deliberately NOT routed through SendNotification/notification_logs: that
 * pipeline is tenant-scoped (every row needs a tenant_id) and these
 * messages have no tenant. Queued (not sent inline) so a slow or failing
 * SMTP server can never slow down or break the request/command that
 * triggered it (CLAUDE.md §37/§43); a failure to even queue is reported
 * and swallowed for the same reason — the alert is secondary to the thing
 * it is alerting about.
 */
class NotifyPlatformAdmins
{
    /**
     * @return int number of admins the message was queued for
     */
    public function execute(string $subject, string $body): int
    {
        $emails = PlatformAdmin::where('is_active', true)->pluck('email')->filter()->unique()->values()->all();

        if ($emails === []) {
            return 0;
        }

        try {
            Mail::to($emails)->queue(new NotificationMail($subject, $body));
        } catch (\Throwable $e) {
            report($e);

            return 0;
        }

        return count($emails);
    }
}
