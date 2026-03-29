<?php
// ============================================================
//  dashboard/index.php — Home screen summary
// ============================================================
opcache_reset();
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';

$pageTitle    = 'Dashboard';
$pageSubtitle = 'Good to see you, ' . $adminName . '!';
$today        = date('Y-m-d');
$thisMonth    = date('Y-m');

// ── Today's total sales (avoid GENERATED column) ─────────────
$raw = db_value(
    "SELECT COALESCE(SUM(qty_sold * unit_price), 0)
       FROM sales
      WHERE sale_date = ?",
    [$today]
);
$todaySales = $raw === false ? 0.0 : (float) $raw;

// ── Today's total expenses ────────────────────────────────────
$raw = db_value(
    "SELECT COALESCE(SUM(amount), 0)
       FROM expenses
      WHERE expense_date = ?",
    [$today]
);
$todayExpenses = $raw === false ? 0.0 : (float) $raw;

// ── Today's net profit ────────────────────────────────────────
$todayProfit = $todaySales - $todayExpenses;

// ── This month's totals ───────────────────────────────────────
$raw = db_value(
    "SELECT COALESCE(SUM(qty_sold * unit_price), 0)
       FROM sales
      WHERE DATE_FORMAT(sale_date, '%Y-%m') = ?",
    [$thisMonth]
);
$monthSales = $raw === false ? 0.0 : (float) $raw;

$raw = db_value(
    "SELECT COALESCE(SUM(amount), 0)
       FROM expenses
      WHERE DATE_FORMAT(expense_date, '%Y-%m') = ?",
    [$thisMonth]
);
$monthExpenses = $raw === false ? 0.0 : (float) $raw;

$monthProfit = $monthSales - $monthExpenses;

// ── Total pending dues (avoid GENERATED column) ───────────────
$raw = db_value(
    "SELECT COALESCE(SUM(total_amount - amount_paid), 0)
       FROM dues
      WHERE is_cleared = 0"
);
$pendingDues = $raw === false ? 0.0 : (float) $raw;

// ── Low stock items ───────────────────────────────────────────
$lowStockItems = db_fetch_all(
    "SELECT * FROM vw_low_stock LIMIT 5"
);

// ── Recent sales (last 7 rows) ────────────────────────────────
$recentSales = db_fetch_all(
    "SELECT s.sale_date, p.name AS product, s.qty_sold,
            s.unit_price,
            (s.qty_sold * s.unit_price) AS total_amount,
            l.name AS location
       FROM sales s
       JOIN products  p ON p.id = s.product_id
       JOIN locations l ON l.id = s.location_id
      ORDER BY s.id DESC
      LIMIT 7"
);

// ── Recent expenses (last 5 rows) ────────────────────────────
$recentExpenses = db_fetch_all(
    "SELECT e.expense_date, ec.name AS category,
            e.amount, l.name AS location
       FROM expenses e
       JOIN expense_categories ec ON ec.id = e.category_id
       JOIN locations           l ON l.id  = e.location_id
      ORDER BY e.id DESC
      LIMIT 5"
);

require_once __DIR__ . '/../includes/header.php';
?>

<!-- ── Stat cards ────────────────────────────────────────────-->
<div class="stats-grid">

  <div class="stat-card green">
    <span class="stat-icon">💰</span>
    <span class="stat-label">Today's Sales</span>
    <span class="stat-value"><?= CURRENCY_SYMBOL ?><?= number_format($todaySales, 2) ?></span>
    <span class="stat-sub"><?= date('d M Y') ?></span>
  </div>

  <div class="stat-card orange">
    <span class="stat-icon">📋</span>
    <span class="stat-label">Today's Expenses</span>
    <span class="stat-value"><?= CURRENCY_SYMBOL ?><?= number_format($todayExpenses, 2) ?></span>
    <span class="stat-sub">All locations</span>
  </div>

  <div class="stat-card <?= $todayProfit >= 0 ? 'green' : 'red' ?>">
    <span class="stat-icon">📈</span>
    <span class="stat-label">Today's Profit</span>
    <span class="stat-value"><?= CURRENCY_SYMBOL ?><?= number_format($todayProfit, 2) ?></span>
    <span class="stat-sub">Sales − Expenses</span>
  </div>

  <div class="stat-card green">
    <span class="stat-icon">🗓</span>
    <span class="stat-label">Month Sales</span>
    <span class="stat-value"><?= CURRENCY_SYMBOL ?><?= number_format($monthSales, 2) ?></span>
    <span class="stat-sub"><?= date('F Y') ?></span>
  </div>

  <div class="stat-card <?= $monthProfit >= 0 ? 'green' : 'red' ?>">
    <span class="stat-icon">📊</span>
    <span class="stat-label">Month Profit</span>
    <span class="stat-value"><?= CURRENCY_SYMBOL ?><?= number_format($monthProfit, 2) ?></span>
    <span class="stat-sub"><?= date('F Y') ?></span>
  </div>

  <div class="stat-card <?= $pendingDues > 0 ? 'red' : 'green' ?>">
    <span class="stat-icon">🔔</span>
    <span class="stat-label">Pending Dues</span>
    <span class="stat-value"><?= CURRENCY_SYMBOL ?><?= number_format($pendingDues, 2) ?></span>
    <span class="stat-sub">EMI + vendor dues</span>
  </div>

</div>

<!-- ── Low stock alert ───────────────────────────────────────-->
<?php if (!empty($lowStockItems)): ?>
<div class="alert alert-warning">
  ⚠️ <strong><?= count($lowStockItems) ?> item(s)</strong> are running low on stock:
  <?= implode(', ', array_column($lowStockItems, 'name')) ?>
  — <a href="<?= BASE_URL ?>/inventory/index.php">View Inventory</a>
</div>
<?php endif; ?>

<!-- ── Two-column row ─────────────────────────────────────────-->
<div style="display:grid; grid-template-columns:1fr 1fr; gap:1.25rem; margin-bottom:1.25rem;">

  <!-- Recent Sales -->
  <div class="card">
    <div class="card-title">Recent Sales</div>
    <?php if (empty($recentSales)): ?>
      <p style="color:var(--text-muted); font-size:.88rem;">No sales recorded today.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Date</th>
              <th>Item</th>
              <th>Qty</th>
              <th>Amount</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recentSales as $row): ?>
              <tr>
                <td><?= date(DATE_FORMAT, strtotime($row['sale_date'])) ?></td>
                <td><?= htmlspecialchars($row['product']) ?></td>
                <td><?= $row['qty_sold'] ?></td>
                <td><?= CURRENCY_SYMBOL ?><?= number_format($row['total_amount'], 2) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div style="margin-top:.75rem;">
        <a href="<?= BASE_URL ?>/sales/index.php" class="btn btn-outline btn-sm">View All Sales →</a>
      </div>
    <?php endif; ?>
  </div>

  <!-- Recent Expenses -->
  <div class="card">
    <div class="card-title">Recent Expenses</div>
    <?php if (empty($recentExpenses)): ?>
      <p style="color:var(--text-muted); font-size:.88rem;">No expenses recorded today.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Date</th>
              <th>Category</th>
              <th>Location</th>
              <th>Amount</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recentExpenses as $row): ?>
              <tr>
                <td><?= date(DATE_FORMAT, strtotime($row['expense_date'])) ?></td>
                <td><?= htmlspecialchars($row['category']) ?></td>
                <td><?= htmlspecialchars($row['location']) ?></td>
                <td><?= CURRENCY_SYMBOL ?><?= number_format($row['amount'], 2) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div style="margin-top:.75rem;">
        <a href="<?= BASE_URL ?>/expenses/index.php" class="btn btn-outline btn-sm">View All Expenses →</a>
      </div>
    <?php endif; ?>
  </div>

</div>

<!-- ── Quick links ────────────────────────────────────────────-->
<div class="card">
  <div class="card-title">Quick Actions</div>
  <div style="display:flex; flex-wrap:wrap; gap:.75rem;">
    <a href="<?= BASE_URL ?>/sales/add.php"       class="btn btn-primary">➕ Add Sale</a>
    <a href="<?= BASE_URL ?>/expenses/add.php"    class="btn btn-primary">➕ Add Expense</a>
    <a href="<?= BASE_URL ?>/masala/add.php"      class="btn btn-primary">➕ Purchase Entry</a>
    <a href="<?= BASE_URL ?>/inventory/add.php"   class="btn btn-outline">📦 Update Stock</a>
    <a href="<?= BASE_URL ?>/dues/add.php"        class="btn btn-outline">🔔 Add Due / EMI</a>
    <a href="<?= BASE_URL ?>/reports/monthly.php" class="btn btn-outline">📊 Monthly Report</a>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>