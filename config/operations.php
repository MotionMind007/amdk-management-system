<?php

return [
    'uat' => [
        'password' => env('UAT_PASSWORD'),
    ],
    'database_backup' => [
        'pg_dump_binary' => env('DB_BACKUP_PG_DUMP_BINARY', 'pg_dump'),
        'psql_binary' => env('DB_BACKUP_PSQL_BINARY', 'psql'),
    ],
];
