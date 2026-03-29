<?php
// ============================================================
//  masala/delete.php — Delete a purchase entry
// ============================================================
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';

$id    = (int) ($_GET['id']    ?? 0);
$token =        $_GET['token'] ?? '';

if (!$id || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    $_SESSION['flash_error'] = 'Invalid delete request.';
    header('Location: ' . BASE_URL . '/masala/index.php');
    exit;
}

$purchase = db_fetch_one("SELECT item_name FROM purchases WHERE id = ?", [$id]);

if (!$purchase) {
    $_SESSION['flash_error'] = 'Entry not found or already deleted.';
    header('Location: ' . BASE_URL . '/masala/index.php');
    exit;
}

db_run("DELETE FROM purchases WHERE id = ?", [$id]);

$_SESSION['flash_success'] = "Purchase entry '{$purchase['item_name']}' deleted.";
header('Location: ' . BASE_URL . '/masala/index.php');
exit;