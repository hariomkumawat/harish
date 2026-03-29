<?php
// ============================================================
//  includes/header.php — Global Nav + HTML Head
//  Included at the top of every protected page AFTER auth_check
// ============================================================

// Current page detection for active nav highlighting
$currentFile = basename($_SERVER['PHP_SELF']);
$currentDir  = basename(dirname($_SERVER['PHP_SELF']));

// Build logout URL with CSRF token
$logoutUrl = BASE_URL . '/auth/logout.php?token=' . ($_SESSION['csrf_token'] ?? '');

// Nav items: [label, folder, icon]
$navItems = [
    ['Dashboard',  'dashboard',  '🏠'],
    ['Sales',      'sales',      '💰'],
    ['Expenses',   'expenses',   '📋'],
    ['Inventory',  'inventory',  '📦'],
    ['Masala',     'masala',     '🌶'],
    ['Employees',  'employees',  '👥'],
    ['Dues / EMI', 'dues',       '🔔'],
    ['Reports',    'reports',    '📊'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' — ' : '' ?><?= APP_NAME ?></title>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
  <meta name="robots" content="noindex, nofollow">
</head>
<body>

<!-- ── Top bar ─────────────────────────────────────────────── -->
<header class="topbar">
  <div class="topbar-left">
    <button class="burger" id="burgerBtn" aria-label="Toggle menu">&#9776;</button>
    <span class="topbar-title">
      🍽 <?= APP_NAME ?>
    </span>
  </div>
  <div class="topbar-right">
    <span class="topbar-date"><?= date('D, d M Y') ?></span>
    <span class="topbar-admin">👤 <?= htmlspecialchars($adminName) ?></span>
    <a href="<?= $logoutUrl ?>" class="btn-logout">Logout</a>
  </div>
</header>

<!-- ── Sidebar ─────────────────────────────────────────────── -->
<aside class="sidebar" id="sidebar">
  <nav class="sidebar-nav">
    <?php foreach ($navItems as [$label, $folder, $icon]): ?>
      <?php $isActive = ($currentDir === $folder); ?>
      
        <a href="<?= BASE_URL ?>/<?= $folder ?>/index.php"
        class="nav-item <?= $isActive ? 'active' : '' ?>"
      >
        <span class="nav-icon"><?= $icon ?></span>
        <span class="nav-label"><?= $label ?></span>
      </a>
    <?php endforeach; ?>
  </nav>

  <div class="sidebar-footer">
    <span><?= APP_NAME ?></span>
    <span>v<?= APP_VERSION ?></span>
  </div>
</aside>

<!-- ── Page wrapper ────────────────────────────────────────── -->
<div class="page-wrapper" id="pageWrapper">
  <main class="main-content">

    <?php if (isset($pageTitle)): ?>
      <div class="page-header">
        <h1 class="page-heading"><?= htmlspecialchars($pageTitle) ?></h1>
        <?php if (isset($pageSubtitle)): ?>
          <p class="page-subtitle"><?= htmlspecialchars($pageSubtitle) ?></p>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <!-- Flash messages -->
    <?php if (!empty($_SESSION['flash_success'])): ?>
      <div class="alert alert-success">
        ✅ <?= htmlspecialchars($_SESSION['flash_success']) ?>
      </div>
      <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['flash_error'])): ?>
      <div class="alert alert-error">
        ❌ <?= htmlspecialchars($_SESSION['flash_error']) ?>
      </div>
      <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>