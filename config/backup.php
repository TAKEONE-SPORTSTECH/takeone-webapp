<?php

/*
|--------------------------------------------------------------------------
| Backups
|--------------------------------------------------------------------------
| A backup nobody has restored is a rumour, so `takeone:backup` verifies every
| artifact it writes before counting it as a success, and exits non-zero when it
| cannot — a cron that silently produces corrupt files is worse than no cron.
|
| Off-server is the part that actually matters: a snapshot on the same disk as
| the database survives a bad migration but not a dead disk. Set BACKUP_DISK to
| a remote filesystem (s3, sftp, …) to copy each artifact off the box.
*/

return [

    // Where artifacts are written locally before any off-server copy.
    'path' => env('BACKUP_PATH', storage_path('app/backups')),

    /*
    | A configured Laravel disk to copy each artifact to — this is what makes
    | the backup survive losing the server. Null = local only, which the
    | command warns about on every run so it can't be quietly forgotten.
    */
    'disk' => env('BACKUP_DISK'),

    // Delete local artifacts older than this many days (0 = keep everything).
    // The off-server copy is governed by that provider's own lifecycle rules.
    'retain_days' => (int) env('BACKUP_RETAIN_DAYS', 14),

    // Always keep at least this many of each kind, however old — so a box that
    // sat idle for a month still has something to restore from.
    'keep_minimum' => (int) env('BACKUP_KEEP_MINIMUM', 3),

    /*
    | Upload directories to archive alongside the database. Losing these loses
    | proof-of-payment images, profile pictures and chat attachments — rows
    | pointing at files that no longer exist.
    */
    'uploads' => [
        storage_path('app'),
    ],

    // Skip an uploads archive larger than this (MB); 0 = no limit. Guards a
    // cron against filling the disk once media grows.
    'max_uploads_mb' => (int) env('BACKUP_MAX_UPLOADS_MB', 2048),
];
