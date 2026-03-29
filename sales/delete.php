<?php
// ============================================================
//  sales/delete.php — Delete a sale entry
// ============================================================
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';

$id    = (int) ($_GET['id']    ?? 0);
$token =        $_GET['token'] ?? '';

if (!$id || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    $_SESSION['flash_error'] = 'Invalid delete request.';
    header('Location: ' . BASE_URL . '/sales/index.php');
    exit;
}

$affected = db_run("DELETE FROM sales WHERE id = ?", [$id]);

if ($affected) {
    $_SESSION['flash_success'] = 'Sale entry deleted.';
} else {
    $_SESSION['flash_error'] = 'Entry not found or already deleted.';
}

header('Location: ' . BASE_URL . '/sales/index.php');
exit;