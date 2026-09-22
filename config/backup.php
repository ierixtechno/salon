<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Where backups are written
    |--------------------------------------------------------------------------
    |
    | Deliberately outside the app's private file disk (storage/app/private —
    | that is what gets *backed up*, so backing up into it would recurse) and
    | outside public/ (CLAUDE.md §67: backups must never be web-accessible).
    | Downloads go through the authorised Platform > Backups endpoint only.
    |
    */

    'path' => env('BACKUP_PATH', storage_path('backups')),

    /*
    |--------------------------------------------------------------------------
    | Retention
    |--------------------------------------------------------------------------
    |
    | Archives older than this are deleted after a *successful* backup — and
    | the newest few are always kept regardless of age, so a stretch of
    | failed runs can never prune you down to nothing.
    |
    */

    'retention_days' => (int) env('BACKUP_RETENTION_DAYS', 14),

    'always_keep_latest' => 3,

    /*
    |--------------------------------------------------------------------------
    | Include uploaded files
    |--------------------------------------------------------------------------
    |
    | Expense receipts and other private uploads (the app's `local` disk).
    | Tenant data-export bundles are always excluded — they are transient
    | PII copies that expire on their own after 7 days.
    |
    */

    'include_uploads' => (bool) env('BACKUP_INCLUDE_UPLOADS', true),

    /*
    |--------------------------------------------------------------------------
    | "No recent backup" warning threshold
    |--------------------------------------------------------------------------
    |
    | Backups run daily; if the last success is older than this, Platform >
    | Backups and the sidebar turn red. Slightly over 24h so a run that is
    | merely a little late doesn't cry wolf.
    |
    */

    'stale_after_hours' => (int) env('BACKUP_STALE_AFTER_HOURS', 26),

];
