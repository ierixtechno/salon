<?php

namespace App\Domain\Platform\Support;

use App\Domain\Platform\Models\ErrorLog;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use App\Domain\Platform\Actions\NotifyPlatformAdmins;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Records every reported exception into the Platform error log (see
 * bootstrap/app.php's `$exceptions->report()`), grouped by fingerprint so
 * a repeating error is one row with a counter.
 *
 * Two hard rules:
 *
 *  1. Never let error logging cause an error. Anything that goes wrong in
 *     here (database down, table not migrated yet, disk full) is swallowed
 *     — the normal log file still gets the exception either way, since
 *     this hooks in *alongside* Laravel's default reporting, not instead
 *     of it.
 *
 *  2. Never store what must not be stored (CLAUDE.md §35). No request
 *     body, no query string, no stack-frame arguments. The path is the
 *     route *pattern* (`/reset-password/{token}`), never the real URL —
 *     several routes carry secrets in the URL itself (reset tokens, the
 *     public booking confirmation token). Database exception messages
 *     embed the offending values ("Duplicate entry 'a@b.com'"), so quoted
 *     values are masked.
 */
class RecordErrorLog
{
    private const MAX_MESSAGE = 1000;

    private const MAX_FRAMES = 20;

    private static bool $recording = false;

    public function record(Throwable $e): void
    {
        // A failure inside record() must not re-enter it via report().
        if (self::$recording) {
            return;
        }

        self::$recording = true;

        try {
            $this->persist($e);
        } catch (Throwable) {
            // See rule 1 above.
        } finally {
            self::$recording = false;
        }
    }

    private function persist(Throwable $e): void
    {
        $message = $this->sanitizeMessage($e);
        $file = $this->relativePath($e->getFile());
        $fingerprint = sha1(get_class($e).'|'.$file.':'.$e->getLine().'|'.$this->normalize($message));

        $request = request();
        $route = $request?->route();
        $isWeb = $route !== null || ! app()->runningInConsole();

        $now = now();
        $latest = [
            'context' => $isWeb ? 'web' : 'cli',
            'http_method' => $isWeb ? $request->method() : null,
            'path' => $route ? '/'.ltrim($route->uri(), '/') : null,
            'request_id' => $isWeb ? $request->attributes->get('request_id') : null,
            'tenant_id' => $this->safely(fn () => current_tenant_id()),
            'user_id' => $this->safely(fn () => auth('web')->id()),
        ];

        $existing = ErrorLog::where('fingerprint', $fingerprint)->first();

        if (! $existing) {
            try {
                $created = ErrorLog::create($latest + [
                    'fingerprint' => $fingerprint,
                    'exception_class' => get_class($e),
                    'message' => $message,
                    'file' => $file,
                    'line' => $e->getLine(),
                    'trace' => $this->sanitizeTrace($e),
                    'first_seen_at' => $now,
                    'last_seen_at' => $now,
                ]);

                $this->alertNewError($created);

                return;
            } catch (UniqueConstraintViolationException) {
                // Two requests hit the same brand-new error at once; the
                // other one won the insert — fall through and count this one.
            }
        }

        // Atomic increment (not read-modify-write) so concurrent hits are
        // all counted. A recurrence of a "resolved" error reopens it — it
        // clearly wasn't fixed.
        ErrorLog::where('fingerprint', $fingerprint)->update($latest + [
            'occurrences' => DB::raw('occurrences + 1'),
            'last_seen_at' => $now,
            'status' => ErrorLog::OPEN,
            'resolved_at' => null,
            'resolved_by' => null,
        ]);
    }

    /**
     * A never-before-seen error emails Super Admin straight away instead of
     * waiting for the daily digest. Capped at 5 per hour so an outage that
     * throws a dozen different errors cannot flood the inbox (the rest
     * still land in the digest). Same rule 1 as everything here: an
     * alerting failure is swallowed.
     */
    private function alertNewError(ErrorLog $log): void
    {
        try {
            if (! config('platform.health.instant_error_alerts')) {
                return;
            }

            $key = 'error-alerts-sent:'.now()->format('YmdH');
            $sent = (int) Cache::get($key, 0);

            if ($sent >= 5) {
                return;
            }

            Cache::put($key, $sent + 1, now()->addHour());

            $where = $log->path ? "{$log->http_method} {$log->path}" : ($log->context === 'cli' ? 'scheduled/console job' : 'unrouted request');

            app(NotifyPlatformAdmins::class)->execute(
                subject: 'New error: '.$log->shortClass(),
                body: "A new kind of error just occurred.\n\n"
                    .$log->shortClass().': '.mb_substr($log->message, 0, 300)."\n"
                    ."Where: {$where}\n"
                    ."At: {$log->first_seen_at->format('d M Y H:i')} UTC\n\n"
                    ."Details and stack trace:\n".route('platform.error-logs.index'),
            );
        } catch (Throwable) {
            // See rule 1.
        }
    }

    private function sanitizeMessage(Throwable $e): string
    {
        $message = $e->getMessage();

        if ($e instanceof QueryException) {
            // QueryException's own message appends the full SQL *with its
            // bound values*; the driver's message underneath does not.
            $message = $e->getPrevious()?->getMessage() ?? $message;
        }

        if ($e instanceof QueryException || $e instanceof \PDOException) {
            $message = preg_replace("/'[^']*'/", "'?'", $message) ?? $message;
            $message = preg_replace('/ \(Connection:.*$/s', '', $message) ?? $message;
        }

        $message = trim($message);

        return Str::limit($message !== '' ? $message : get_class($e), self::MAX_MESSAGE, '...');
    }

    /**
     * Frames as "file:line Class::method()" — built by hand from getTrace()
     * rather than getTraceAsString(), because the latter can include
     * argument values (a password string passed to a function, say).
     */
    private function sanitizeTrace(Throwable $e): string
    {
        return collect($e->getTrace())
            ->take(self::MAX_FRAMES)
            ->map(function (array $frame) {
                $where = isset($frame['file'])
                    ? $this->relativePath($frame['file']).':'.($frame['line'] ?? '?')
                    : '[internal]';

                return $where.' '.($frame['class'] ?? '').($frame['type'] ?? '').($frame['function'] ?? '').'()';
            })
            ->implode("\n");
    }

    /** So "Order 412 failed" and "Order 977 failed" are the same error. */
    private function normalize(string $message): string
    {
        $message = preg_replace("/'[^']*'/", "'?'", $message) ?? $message;

        return preg_replace('/\d+/', 'N', $message) ?? $message;
    }

    private function relativePath(string $path): string
    {
        $normalized = str_replace('\\', '/', $path);
        $base = str_replace('\\', '/', base_path()).'/';

        return Str::startsWith($normalized, $base) ? substr($normalized, strlen($base)) : $normalized;
    }

    private function safely(callable $fn): mixed
    {
        try {
            return $fn();
        } catch (Throwable) {
            return null;
        }
    }
}
