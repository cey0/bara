<?php

declare(strict_types=1);

/**
 * Default is SQLite so the demo runs with zero setup. Switch to MySQL with:
 *   DB_DRIVER=mysql DB_DATABASE=bara php bin/console migrate
 */
return [
    'driver' => getenv('DB_DRIVER') ?: 'sqlite',

    // SQLite: file path. MySQL: database name.
    'database' => getenv('DB_DATABASE') ?: dirname(__DIR__) . '/database/database.sqlite',

    'host' => getenv('DB_HOST') ?: '127.0.0.1',
    'port' => getenv('DB_PORT') ?: '3306',
    'username' => getenv('DB_USERNAME') ?: 'root',
    'password' => getenv('DB_PASSWORD') ?: '',
    'charset' => 'utf8mb4',
];
