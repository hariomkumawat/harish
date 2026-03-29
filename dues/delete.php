<?php
// ============================================================
//  dues/delete.php — Delete a due record
// ============================================================
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';

$id    = (int) ($_GET['id']    ?? 0);
$token =        $_GET['token'] ?? '';

if (!$id || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    $_SESSION['flash_error'] = 'Invalid delete request.';
    header('Location: ' . BASE_URL . '/dues/index.php');
    exit;
}

// due_payments deleted automatically via ON DELETE CASCADE
$affected = db_run("DELETE FROM dues WHERE id = ?", [$id]);

if ($affected) {
    $_SESSION['flash_success'] = 'Due record deleted successfully.';
} else {
    $_SESSION['flash_error'] = 'Record not found or already deleted.';
}

header('Location: ' . BASE_URL . '/dues/index.php');
exit;