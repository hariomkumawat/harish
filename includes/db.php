<?php
// ============================================================
//  includes/db.php — PDO Database Connection
//  Usage: require_once __DIR__ . '/../includes/db.php';
//  Gives you: $pdo  (PDO instance, ready to use)
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
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,   // throw on error
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,         // arrays by default
        PDO::ATTR_EMULATE_PREPARES   => false,                    // real prepared statements
        PDO::MYSQL_ATTR_FOUND_ROWS   => true,                     // rowCount() on UPDATE
    ];

    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

} catch (PDOException $e) {
    if (DEBUG_MODE) {
        die('<pre>DB Connection failed: ' . $e->getMessage() . '</pre>');
    } else {
        error_log('DB Connection failed: ' . $e->getMessage());
        die('Something went wrong. Please try again later.');
    }
}

function getDbConnection() {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        // ←←← THIS IS THE IMPORTANT FIX ←←←
        $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("SET CHARACTER SET utf8mb4");
    }
    return $pdo;
}
// ============================================================
//  Helper functions  (available on every page that includes db.php)
// ============================================================

/**
 * Run a SELECT query and return all matching rows.
 *
 * Example:
 *   $rows = db_fetch_all("SELECT * FROM sales WHERE location_id = ?", [1]);
 */
function db_fetch_all(string $sql, array $params = []): array
{
    global $pdo;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Run a SELECT query and return a single row.
 *
 * Example:
 *   $emp = db_fetch_one("SELECT * FROM employees WHERE id = ?", [$id]);
 */
function db_fetch_one(string $sql, array $params = []): array|false
{
    global $pdo;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch();
}

/**
 * Run an INSERT / UPDATE / DELETE query.
 * Returns the number of affected rows.
 *
 * Example:
 *   $affected = db_run("UPDATE stock_items SET qty_in_hand = ? WHERE id = ?", [5.5, 3]);
 */
function db_run(string $sql, array $params = []): int
{
    global $pdo;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount();
}

/**
 * Run an INSERT and return the new auto-increment ID.
 *
 * Example:
 *   $newId = db_insert("INSERT INTO expenses (amount, expense_date) VALUES (?, ?)", [200, '2024-06-01']);
 */
function db_insert(string $sql, array $params = []): string
{
    global $pdo;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $pdo->lastInsertId();
}

/**
 * Fetch a single scalar value — useful for COUNT, SUM, MAX.
 *
 * Example:
 *   $total = db_value("SELECT SUM(total_amount) FROM sales WHERE sale_date = ?", ['2024-06-01']);
 */
function db_value(string $sql, array $params = []): mixed
{
    global $pdo;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

