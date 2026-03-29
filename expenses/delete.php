<?php
// ============================================================
//  expenses/delete.php — Delete an expense entry
// ============================================================
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';

$id    = (int) ($_GET['id']    ?? 0);
$token =        $_GET['token'] ?? '';

if (!$id || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    $_SESSION['flash_error'] = 'Invalid delete request.';
    header('Location: ' . BASE_URL . '/expenses/index.php');
    exit;
}

$affected = db_run("DELETE FROM expenses WHERE id = ?", [$id]);

if ($affected) {
    $_SESSION['flash_success'] = 'Expense deleted successfully.';
} else {
    $_SESSION['flash_error'] = 'Entry not found or already deleted.';
}

header('Location: ' . BASE_URL . '/expenses/index.php');
exit;