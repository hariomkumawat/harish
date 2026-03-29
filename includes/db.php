<?php
// ============================================================
//  includes/db.php — PDO Database Connection
//  Fixed for MariaDB 11.8.6
// ============================================================

require_once __DIR__ . '/../config.php';

try {
    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        DB_HOST,
        DB_NAME,
        DB_CHARSET
    );

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_FOUND_ROWS   => true,
        // MariaDB 11.x — only single SET statement works here
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_general_ci",
    ];

    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

    // MariaDB 11.x fix — set collation separately after connect
    $pdo->exec("SET collation_connection = utf8mb4_general_ci");
    $pdo->exec("SET collation_server     = utf8mb4_general_ci");

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