<?php

return [
    'initial_admin' => [
        'name' => env('ADMIN_NAME', 'Administrator'),
        'email' => env('ADMIN_EMAIL', 'admin@amdk.local'),
        'password' => env('ADMIN_PASSWORD'),
    ],
    'uat' => [
        'password' => env('UAT_PASSWORD'),
    ],
    'database_backup' => [
        'pg_dump_binary' => env('DB_BACKUP_PG_DUMP_BINARY', 'pg_dump'),
        'psql_binary' => env('DB_BACKUP_PSQL_BINARY', 'psql'),
        'verify_username' => env('DB_BACKUP_VERIFY_USERNAME'),
        'verify_password' => env('DB_BACKUP_VERIFY_PASSWORD'),
        'offsite_disk' => env('DB_BACKUP_OFFSITE_DISK'),
    ],
    'system_monitor' => [
        'backup_max_age_hours' => (int) env('SYSTEM_MONITOR_BACKUP_MAX_AGE_HOURS', 30),
        'disk_free_percent_threshold' => (int) env('SYSTEM_MONITOR_DISK_FREE_PERCENT_THRESHOLD', 15),
    ],
];
