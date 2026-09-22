<?php

namespace App\Console\Commands;

use App\Domain\Platform\Actions\NotifyPlatformAdmins;
use App\Domain\Platform\Models\ErrorLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * The once-a-day error report emailed to every Super Admin. Sends nothing
 * on a day with nothing to report — a digest that always arrives gets
 * ignored, one that only arrives when something's wrong gets read.
 *
 * "Something to report" = any open error seen in the last 24 hours.
 * Idempotent per day (CLAUDE.md §44): a doubled cron firing doesn't send
 * two emails.
 */
class SendErrorLogDigest extends Command
{
    protected $signature = 'error-logs:send-digest';

    protected $description = 'Email Super Admin a summary of application errors seen in the last 24 hours (nothing is sent if there were none).';

    public function handle(NotifyPlatformAdmins $notify): int
    {
        $since = now()->subDay();

        $recent = ErrorLog::where('status', ErrorLog::OPEN)
            ->where('last_seen_at', '>=', $since)
            ->orderByDesc('last_seen_at')
            ->get();

        if ($recent->isEmpty()) {
            $this->info('No errors in the last 24 hours — no digest sent.');

            return self::SUCCESS;
        }

        // add() is atomic: only the first caller today gets true.
        if (! Cache::add('error-log-digest-sent:'.today()->toDateString(), true, now()->addDay())) {
            $this->info('Digest already sent today.');

            return self::SUCCESS;
        }

        $new = $recent->filter(fn (ErrorLog $log) => $log->first_seen_at->gte($since));

        $lines = $recent->take(15)->map(function (ErrorLog $log) use ($since) {
            $flag = $log->first_seen_at->gte($since) ? '[NEW] ' : '';
            $where = $log->path ? "{$log->http_method} {$log->path}" : ($log->context === 'cli' ? 'scheduled/console' : 'unrouted request');

            return "{$flag}{$log->shortClass()}: ".mb_substr($log->message, 0, 140)."\n"
                ."    where: {$where} | seen {$log->occurrences}x in total | last: {$log->last_seen_at->format('d M H:i')}";
        })->implode("\n\n");

        $more = $recent->count() > 15 ? "\n\n...and ".($recent->count() - 15).' more.' : '';

        $notify->execute(
            subject: "Error digest: {$recent->count()} open error(s), {$new->count()} new",
            body: "Application errors seen in the last 24 hours ({$recent->count()} distinct, {$new->count()} first seen in this period):\n\n"
                .$lines.$more
                ."\n\nFull details, stack traces and the resolve button:\n".route('platform.error-logs.index'),
        );

        $this->info("Digest sent ({$recent->count()} error(s)).");

        return self::SUCCESS;
    }
}
