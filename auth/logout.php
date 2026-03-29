<?php
// ============================================================
//  auth/logout.php — Destroys session and redirects to login
// ============================================================

require_once __DIR__ . '/../config.php';

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

// ── CSRF check (logout must be intentional, not triggered by a rogue image/link) ──
$token      = $_GET['token'] ?? '';
$validToken = $_SESSION['csrf_token'] ?? '';

if (!hash_equals($validToken, $token)) {
    // Invalid token — just redirect silently, don't error
    header('Location: ' . BASE_URL . '/dashboard/index.php');
    exit;
}

// ── Wipe everything ───────────────────────────────────────────
$_SESSION = [];

// Delete the session cookie from the browser
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();

header('Location: ' . BASE_URL . '/auth/login.php?reason=logout');
exit;