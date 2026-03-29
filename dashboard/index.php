<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';

$pageTitle    = 'Dashboard';
$pageSubtitle = 'Good to see you, ' . $adminName . '!';

// HARDCODED TEST - sab zero
$todaySales    = 0.0;
$todayExpenses = 0.0;
$todayProfit   = 0.0;
$monthSales    = 0.0;
$monthExpenses = 0.0;
$monthProfit   = 0.0;
$pendingDues   = 0.0;
$lowStockItems = [];
$recentSales   = [];
$recentExpenses = [];

require_once __DIR__ . '/../includes/header.php';