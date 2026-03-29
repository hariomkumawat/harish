<?php
// ============================================================
//  includes/db.php — PDO Database Connection
//  Fixed for MariaDB 11.8.6
// ============================================================

require_once __DIR__ . '/../config.php';

try {
    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=utf8mb4',
        DB_HOST,
        DB_NAME
    );

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_FOUND_ROWS   => true,
        // unicode_ci — matches MariaDB 11.x default connection collation
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
    ];

    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

    // Force unicode_ci — matches @@collation_connection
    $pdo->exec("SET collation_connection = utf8mb4_unicode_ci");

} catch (PDOException $e) {
    if (DEBUG_MODE) {
        die('<pre>DB Connection failed: ' . $e->getMessage() . '</pre>');
    } else {
        error_log('DB Connection failed: ' . $e->getMessage());
        die('Something went wrong. Please try again later.');
    }
}

// ============================================================
//  Helper functions
// ============================================================

function db_fetch_all(string $sql, array $params = []): array
{
    global $pdo;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function db_fetch_one(string $sql, array $params = []): array|false
{
    global $pdo;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch();
}

function db_run(string $sql, array $params = []): int
{
    global $pdo;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount();
}

function db_insert(string $sql, array $params = []): string
{
    global $pdo;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $pdo->lastInsertId();
}

function db_value(string $sql, array $params = []): mixed
{
    global $pdo;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}