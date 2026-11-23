<?php
/**
 * Database access (MySQL / MariaDB through PDO).
 *
 * Every query uses prepared statements. Settings are in config/database.php.
 * Results that many pages need (users, PPAs, funds ...) are cached for the
 * rest of the request and the cache is cleared after every write.
 */

/** The shared PDO connection. Shows a setup page when the database cannot be reached. */
function db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }
    $c = require dirname(__DIR__) . '/config/database.php';
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $c['host'], (int) $c['port'], $c['database']);
    try {
        $pdo = new PDO($dsn, $c['username'], $c['password'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        $pdo->exec("SET time_zone = '+08:00'");
    } catch (PDOException $e) {
        error_log('AIP database connection failed: ' . $e->getMessage());
        dbUnavailable($c['database']);
    }
    return $pdo;
}

/** Friendly page when MySQL is not running or the database was not imported. */
function dbUnavailable(string $database): void
{
    http_response_code(503);
    $name = htmlspecialchars($database, ENT_QUOTES);
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Database unavailable</title>'
       . '<style>body{font-family:Arial,sans-serif;background:#f4f6f9;color:#1d2a3a;display:grid;place-items:center;min-height:100vh;margin:0}'
       . 'main{background:#fff;border:1px solid #dde3ea;border-radius:10px;padding:28px 32px;max-width:520px}h1{font-size:20px;margin:0 0 12px}'
       . 'li{margin:6px 0}code{background:#eef2f6;padding:1px 5px;border-radius:4px}</style></head><body><main>'
       . '<h1>The system cannot connect to the database</h1><ol>'
       . '<li>Start <b>MySQL</b> in the XAMPP Control Panel.</li>'
       . '<li>In phpMyAdmin, import <code>database/aip_db.sql</code> to create the <code>' . $name . '</code> database.</li>'
       . '<li>If your MySQL user has a password, put it in <code>config/database.local.php</code>.</li>'
       . '</ol><p>Then reload this page.</p></main></body></html>';
    exit;
}

/** Run a statement with parameters; returns the PDOStatement. */
function dbRun(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function dbAll(string $sql, array $params = []): array
{
    return dbRun($sql, $params)->fetchAll();
}

function dbOne(string $sql, array $params = []): ?array
{
    $row = dbRun($sql, $params)->fetch();
    return $row === false ? null : $row;
}

function dbValue(string $sql, array $params = [])
{
    $value = dbRun($sql, $params)->fetchColumn();
    return $value === false ? null : $value;
}

/** INSERT/UPDATE/DELETE; clears the request cache. Returns affected rows. */
function dbExec(string $sql, array $params = []): int
{
    $count = dbRun($sql, $params)->rowCount();
    dbCacheClear();
    return $count;
}

/** Run a function inside a transaction (nested calls join the outer one). */
function dbTransaction(callable $fn)
{
    $pdo = db();
    if ($pdo->inTransaction()) {
        return $fn();
    }
    $pdo->beginTransaction();
    try {
        $result = $fn();
        $pdo->commit();
        dbCacheClear();
        return $result;
    } catch (Throwable $e) {
        $pdo->rollBack();
        dbCacheClear();
        throw $e;
    }
}

/* ---------------- Request cache ---------------- */

function &dbCacheStore(): array
{
    static $cache = [];
    return $cache;
}

/** Cached result of $fn for this request, until the next write. */
function dbCached(string $key, callable $fn)
{
    $cache = &dbCacheStore();
    if (!array_key_exists($key, $cache)) {
        $cache[$key] = $fn();
    }
    return $cache[$key];
}

function dbCacheClear(): void
{
    $cache = &dbCacheStore();
    $cache = [];
}

/* ---------------- Small helpers ---------------- */

/** Empty string → NULL (for optional columns). */
function dbNull($value)
{
    return $value === '' || $value === null ? null : $value;
}

/** "Name (username)" label for a user row, as shown in histories and logs. */
function dbActorLabel(?string $name, ?string $username): string
{
    if ($name === null || $name === '') {
        return '';
    }
    return $username ? $name . ' (' . $username . ')' : $name;
}

/** The signed-in user's id and display label, for "created by" / "updated by" columns. */
function dbActor(): array
{
    $user = function_exists('currentUser') ? currentUser() : null;
    return [$user['id'] ?? null, $user ? $user['name'] . ' (' . $user['username'] . ')' : 'System'];
}
