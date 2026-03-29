<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';

$pageTitle = 'Dashboard';
$pageSubtitle = 'Good to see you, ' . $adminName . '!';

// Direct query test
$r1 = db_value("SELECT COALESCE(SUM(qty_sold * unit_price), 0) FROM sales WHERE sale_date = ?", [date('Y-m-d')]);
$r2 = db_value("SELECT COUNT(*) FROM sales");

require_once __DIR__ . '/../includes/header.php';
?>

<div style="padding:2rem; font-size:1.5rem;">
  <p>Sales SUM raw: <strong><?= var_export($r1, true) ?></strong></p>
  <p>Sales COUNT: <strong><?= var_export($r2, true) ?></strong></p>
  <p>float cast: <strong><?= (float)$r1 ?></strong></p>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>