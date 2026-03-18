<?php
/**
 * Конфигурация на базата данни
 * Редактирайте стойностите спрямо вашата PostgreSQL инсталация.
 */
return [
    'driver'   => 'pgsql',
    'host'     => getenv('DB_HOST') ?: 'localhost',
    'port'     => (int) (getenv('DB_PORT') ?: 5432),
    'dbname'   => getenv('DB_NAME') ?: 'venues_db',
    'username' => getenv('DB_USER') ?: 'postgres',
    'password' => getenv('DB_PASS') ?: 'postgres',
    'charset'  => 'UTF8',
];
