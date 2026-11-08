<?php
/**
 * MySQL connection placeholder for the existing database.
 * Replace the placeholder values with your own server configuration when ready.
 */

function getDatabaseConnection(): PDO
{
    $host = 'DB_HOST';
    $database = 'DB_NAME';
    $username = 'DB_USER';
    $password = 'DB_PASS';

    $dsn = "mysql:host={$host};dbname={$database};charset=utf8mb4";

    return new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}
