<?php
// ============================================================
//  auth/login.php — Admin Login Page
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

// Already logged in → go to dashboard
if (!empty($_SESSION['admin_id'])) {
    header('Location: ' . BASE_URL . '/dashboard/index.php');
    exit;
}

require_once __DIR__ . '/../includes/db.php';

$error   = '';
$reason  = $_GET['reason'] ?? '';

// ── Rate limiting (simple: max 5 attempts per 10 min per IP) ──
$ip          = $_SERVER['REMOTE_ADDR'];
$attemptKey  = 'login_attempts_' . md5($ip);
$lockoutKey  = 'login_lockout_'  . md5($ip);

// We store attempts in the DB-less session-based counter
// (for a production app, store in a `login_attempts` DB table)
if (!isset($_SESSION[$attemptKey]))  $_SESSION[$attemptKey]  = 0;
if (!isset($_SESSION[$lockoutKey]))  $_SESSION[$lockoutKey]  = 0;

$isLockedOut = ($_SESSION[$lockoutKey] > time());

// ── Handle POST ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if ($isLockedOut) {
        $remaining = ceil(($_SESSION[$lockoutKey] - time()) / 60);
        $error = "Too many failed attempts. Try again in {$remaining} minute(s).";

    } else {
        // CSRF token check
        $token = $_POST['csrf_token'] ?? '';
        if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            $error = 'Invalid request. Please try again.';

        } else {
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';

            if ($username === '' || $password === '') {
                $error = 'Please enter both username and password.';

            } else {
                $admin = db_fetch_one(
                    "SELECT id, username, password, full_name
                       FROM admin_users
                      WHERE username = ?
                      LIMIT 1",
                    [$username]
                );

                if ($admin && password_verify($password, $admin['password'])) {
                    // ── Success ──────────────────────────────
                    session_regenerate_id(true);

                    $_SESSION['admin_id']        = $admin['id'];
                    $_SESSION['admin_name']      = $admin['full_name'];
                    $_SESSION['last_active']     = time();
                    $_SESSION['regenerated_at']  = time();

                    // Reset attempt counters
                    $_SESSION[$attemptKey] = 0;
                    $_SESSION[$lockoutKey] = 0;
                    unset($_SESSION['csrf_token']);

                    $redirect = $_SESSION['redirect_after_login']
                                ?? BASE_URL . '/dashboard/index.php';
                    unset($_SESSION['redirect_after_login']);

                    header('Location: ' . $redirect);
                    exit;

                } else {
                    // ── Failure ──────────────────────────────
                    $_SESSION[$attemptKey]++;

                    if ($_SESSION[$attemptKey] >= 5) {
                        $_SESSION[$lockoutKey] = time() + 600; // lock 10 min
                        $_SESSION[$attemptKey] = 0;
                        $error = 'Too many failed attempts. Locked for 10 minutes.';
                    } else {
                        $left  = 5 - $_SESSION[$attemptKey];
                        $error = "Incorrect username or password. {$left} attempt(s) left.";
                    }
                }
            }
        }
    }
}

// ── Generate CSRF token ───────────────────────────────────────
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// ── Reason messages (from auth_check redirect) ────────────────
$reasonMsg = match($reason) {
    'timeout' => 'You were logged out due to inactivity.',
    'logout'  => 'You have been logged out successfully.',
    default   => ''
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login — <?= APP_NAME ?></title>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
  <style>
    /* ── Minimal login-specific styles ── */
    body        { background: #f5f5f5; display: flex; align-items: center;
                  justify-content: center; min-height: 100vh; margin: 0; }
    .login-card { background: #fff; border-radius: 10px; padding: 2.5rem 2rem;
                  width: 100%; max-width: 380px; box-shadow: 0 2px 12px rgba(0,0,0,.08); }
    .login-card h1   { text-align: center; font-size: 1.3rem; margin: 0 0 .25rem; }
    .login-card .sub { text-align: center; color: #777; font-size: .85rem;
                       margin-bottom: 1.75rem; }
    .form-group       { margin-bottom: 1rem; }
    .form-group label { display: block; font-size: .85rem; font-weight: 600;
                        margin-bottom: .35rem; color: #444; }
    .form-group input { width: 100%; padding: .6rem .8rem; border: 1px solid #ddd;
                        border-radius: 6px; font-size: .95rem; box-sizing: border-box; }
    .form-group input:focus { outline: none; border-color: #e65c00; }
    .btn-login  { width: 100%; padding: .7rem; background: #e65c00; color: #fff;
                  border: none; border-radius: 6px; font-size: 1rem;
                  cursor: pointer; font-weight: 600; margin-top: .5rem; }
    .btn-login:hover { background: #c94f00; }
    .alert      { padding: .65rem .9rem; border-radius: 6px; font-size: .875rem;
                  margin-bottom: 1rem; }
    .alert-error   { background: #fdecea; color: #b71c1c; border: 1px solid #f5c6c6; }
    .alert-info    { background: #e8f4fd; color: #0d47a1; border: 1px solid #b3d4f5; }
    .shop-name  { text-align: center; color: #e65c00; font-size: .8rem;
                  margin-top: 1.5rem; letter-spacing: .03em; }
  </style>
</head>
<body>

<div class="login-card">

  <h1>🍽 <?= APP_NAME ?></h1>
  <p class="sub">Admin Panel — Please sign in</p>

  <?php if ($reasonMsg): ?>
    <div class="alert alert-info"><?= htmlspecialchars($reasonMsg) ?></div>
  <?php endif; ?>

  <?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST" action="" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

    <div class="form-group">
      <label for="username">Username</label>
      <input
        type="text"
        id="username"
        name="username"
        value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
        placeholder="Enter username"
        required
        autofocus
        <?= $isLockedOut ? 'disabled' : '' ?>
      >
    </div>

    <div class="form-group">
      <label for="password">Password</label>
      <input
        type="password"
        id="password"
        name="password"
        placeholder="Enter password"
        required
        <?= $isLockedOut ? 'disabled' : '' ?>
      >
    </div>

    <button type="submit" class="btn-login" <?= $isLockedOut ? 'disabled' : '' ?>>
      Sign In
    </button>
  </form>

  <p class="shop-name">Harishji Pav-Vada &nbsp;|&nbsp; <?= date('d M Y') ?></p>

</div>

</body>
</html>