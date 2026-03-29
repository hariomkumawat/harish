<?php
// ============================================================
//  inventory/delete.php — Delete a stock item
//  Also cascades to stock_transactions (FK ON DELETE CASCADE)
// ============================================================
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';

$id    = (int) ($_GET['id']    ?? 0);
$token =        $_GET['token'] ?? '';

if (!$id || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    $_SESSION['flash_error'] = 'Invalid delete request.';
    header('Location: ' . BASE_URL . '/inventory/index.php');
    exit;
}

// Fetch item name for the success message
$item = db_fetch_one("SELECT name FROM stock_items WHERE id = ?", [$id]);

if (!$item) {
    $_SESSION['flash_error'] = 'Item not found or already deleted.';
    header('Location: ' . BASE_URL . '/inventory/index.php');
    exit;
}

// Delete — stock_transactions cascade automatically (FK ON DELETE CASCADE)
$affected = db_run("DELETE FROM stock_items WHERE id = ?", [$id]);

if ($affected) {
    $_SESSION['flash_success'] = "'{$item['name']}' removed from inventory.";
} else {
    $_SESSION['flash_error'] = 'Could not delete item. Please try again.';
}

header('Location: ' . BASE_URL . '/inventory/index.php');
exit;