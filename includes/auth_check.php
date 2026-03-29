<?php
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

// ── Login check ───────────────────────────────────────────────
if (empty($_SESSION['admin_id'])) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header('Location: ' . BASE_URL . '/auth/login.php');
    exit;
}

// ── Idle timeout ──────────────────────────────────────────────
if (isset($_SESSION['last_active'])) {
    $idle = time() - $_SESSION['last_active'];
    if ($idle > SESSION_TIMEOUT) {
        session_unset();
        session_destroy();
        header('Location: ' . BASE_URL . '/auth/login.php?reason=timeout');
        exit;
    }
}
$_SESSION['last_active'] = time();

// ── Periodic session ID regeneration (every 15 min) ──────────
if (empty($_SESSION['regenerated_at'])) {
    $_SESSION['regenerated_at'] = time();
}
if ((time() - $_SESSION['regenerated_at']) > 900) {
    session_regenerate_id(true);
    $_SESSION['regenerated_at'] = time();
}

// ── CSRF token — always available on every protected page ─────
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ── Convenience variables ─────────────────────────────────────
$adminId   = $_SESSION['admin_id']   ?? null;
$adminName = $_SESSION['admin_name'] ?? 'Admin';