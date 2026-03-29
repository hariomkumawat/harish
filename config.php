<?php
// ============================================================
//  config.php — Harishji Pav-Vada Admin Panel
//  Central configuration file. Include this FIRST everywhere.
// ============================================================

// ── Database ─────────────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_NAME', 'harishji_db');
// define('DB_USER', 'root');         
// define('DB_PASS', '');  
define('DB_USER', 'u480865533_harishji');         
define('DB_PASS', 'Harishji@patidar#2@26');              
define('DB_CHARSET', 'utf8mb4');

// ── Application ───────────────────────────────────────────────
define('APP_NAME',    'Harishji Pav-Vada');
define('APP_VERSION', '1.0.0');
define('BASE_URL',    'https://harishji.harvesthavengoods.com/'); // no trailing slash
// define('BASE_URL',    'http://localhost/php/harish'); // no trailing slash

// ── Session ───────────────────────────────────────────────────
define('SESSION_NAME',    'harishji_session');
define('SESSION_TIMEOUT', 3600); // seconds — 1 hour idle logout

// ── Business ──────────────────────────────────────────────────
define('DEFAULT_LOCATION_ID', 1);   // 1 = Main Shop
define('LOW_STOCK_ALERT',     true);
define('CURRENCY_SYMBOL',     '₹');
define('DATE_FORMAT',         'd/m/Y');      // display format
define('DATE_FORMAT_DB',      'Y-m-d');      // MySQL format

// ── Timezone ──────────────────────────────────────────────────
date_default_timezone_set('Asia/Kolkata');

// ── Error reporting (set to 0 in production) ──────────────────
define('DEBUG_MODE', true);

if (DEBUG_MODE) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/logs/error.log');
}