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
        'offsite_disk' => env('DB_BACKUP_OFFSITE_DISK'),
    ],
];
