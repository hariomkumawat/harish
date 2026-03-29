

<?php
// ============================================================
//  config.php — Harishji Pav-Vada Admin Panel
// ============================================================

// ── Database ──────────────────────────────────────────────────
define('DB_HOST',    'localhost');

// define('DB_NAME', 'harishji_db');
// define('DB_USER', 'root');         
// define('DB_PASS', '');  
define('DB_NAME', 'u480865533_harishji_db');
define('DB_USER', 'u480865533_harishji');         
define('DB_PASS', 'Harishji@patidar#2@26');              

define('DB_CHARSET', 'utf8mb4');
// ── Application ───────────────────────────────────────────────
define('APP_NAME',    'Harishji Pav-Vada');
define('APP_VERSION', '1.0.0');
define('BASE_URL',    'https://harishji.harvesthavengoods.com');

// ── Session ───────────────────────────────────────────────────
define('SESSION_NAME',    'harishji_session');
define('SESSION_TIMEOUT', 3600);

// ── Business ──────────────────────────────────────────────────
define('DEFAULT_LOCATION_ID', 1);
define('LOW_STOCK_ALERT',     true);
define('CURRENCY_SYMBOL', 'Rs.');
define('DATE_FORMAT',         'd/m/Y');
define('DATE_FORMAT_DB',      'Y-m-d');

// ── Timezone ──────────────────────────────────────────────────
date_default_timezone_set('Asia/Kolkata');

// ── Error reporting ───────────────────────────────────────────
define('DEBUG_MODE', false);   // always false on live server

if (DEBUG_MODE) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}