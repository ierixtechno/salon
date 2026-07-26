# Shared Hosting Compatibility & Future Scalability

Full authority: `CLAUDE.md` §2, §43–44, §54–56, §64–67; `.claude/skills/beauty-saas-development/SKILL.md` §35, §42.

## Initial production target

Shared hosting: MySQL, cron, database-backed queue (no persistent worker), file/database cache, Laravel filesystem abstraction for uploads. No mandatory dependency on: a persistent worker process, Redis, a WebSocket server, root server access, or a system daemon — unless explicitly approved as an exception.

## Queues & scheduler

Slow/non-critical work (email, WhatsApp, SMS, exports, report generation, image processing, non-critical notifications) goes through queued jobs, processed via cron-triggered `schedule:run` where persistent workers aren't available. Business-critical state changes never depend exclusively on a queue succeeding. Scheduled jobs (reminders, expiry checks, birthday/inactive-customer campaigns, low-stock alerts, reporting aggregation) must be idempotent — running one twice must not double-charge, double-notify, or double-book.

## Performance

Avoid N+1 queries, unbounded queries, loading huge collections into memory, unnecessary joins, repeated settings/permission queries. Use eager loading, pagination, indexes, aggregation, caching, chunking, and queues where appropriate — but don't optimize blindly ahead of an actual pattern.

Reporting must not degrade operational screens as data grows — use aggregate queries, summary tables, scheduled aggregation, and export jobs instead of computing lifetime analytics on every dashboard load.

## Cache

Good candidates: tenant settings, branch settings, module assignments, plan features, permissions, service configuration, tax settings. Invalidate on relevant config changes. Never cache authorization-sensitive data without tenant/user-aware cache keys.

## Portability to VPS/cloud

Code must not depend on a particular shared-host filesystem layout. Use Laravel's storage/cache/queue/mail/database/filesystem abstractions throughout, so swapping to Redis/object storage/dedicated workers later is a config change, not a rewrite.

## Backup, export, portability

Production docs must cover database backups, uploaded files, environment config, and restore process; backups are never publicly accessible. Tenant data export is tenant-scoped, permission-protected, and queued for large exports once infrastructure allows.

## Dependency policy

Before adding any Composer/npm package: confirm it's actually necessary, check whether Laravel already provides it, verify it's actively maintained, verify compatibility, assess security, assess shared-hosting compatibility. Avoid unnecessary dependencies — this project already makes two exceptions worth naming: `spatie/laravel-permission` (RBAC — see [04-RBAC.md](04-RBAC.md)) and Laravel Breeze (auth scaffolding), both because Laravel doesn't provide the equivalent out of the box and both are shared-hosting compatible with no extra runtime requirements.
