<?php
// ============================================================
//  index.php — Entry point
//  harishji-admin/index.php
//  Just resolves session state and routes accordingly.
// ============================================================

require_once __DIR__ . '/config.php';

session_name(SESSION_NAME);
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => isset($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Strict',
]);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── If already logged in → dashboard ─────────────────────────
if (!empty($_SESSION['admin_id'])) {
    header('Location: ' . BASE_URL . '/dashboard/index.php');
    exit;
}

// ── Not logged in → login page ────────────────────────────────
header('Location: ' . BASE_URL . '/auth/login.php');
exit;