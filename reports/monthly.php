<?php
// ============================================================
//  reports/monthly.php — Full monthly report
// ============================================================
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';

$month      = $_GET['month']       ?? date('Y-m');
$locationId = $_GET['location_id'] ?? '';

// Validate month format
if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    $month = date('Y-m');
}

$monthLabel  = date('F Y', strtotime($month . '-01'));
$pageTitle   = 'Monthly Report';
$pageSubtitle = $monthLabel;

// ── Location filter ───────────────────────────────────────────
$locParam   = [];
$locSales   = '';
$locExpense = '';
if ($locationId) {
    $locSales   = 'AND s.location_id = ?';
    $locExpense = 'AND e.location_id = ?';
    $locParam   = [$locationId];
}

// ── 1. Total Sales ────────────────────────────────────────────
$totalSales = (float) db_value(
    "SELECT COALESCE(SUM(total_amount), 0)
       FROM sales s
      WHERE DATE_FORMAT(sale_date, '%Y-%m') = ? {$locSales}",
    array_merge([$month], $locParam)
);

// ── 2. Total Expenses ─────────────────────────────────────────
$totalExpenses = (float) db_value(
    "SELECT COALESCE(SUM(amount), 0)
       FROM expenses e
      WHERE DATE_FORMAT(expense_date, '%Y-%m') = ? {$locExpense}",
    array_merge([$month], $locParam)
);

// ── 3. Total Purchases (masala/raw materials) ─────────────────
$totalPurchases = (float) db_value(
    "SELECT COALESCE(SUM(total_amount), 0)
       FROM purchases
      WHERE DATE_FORMAT(purchase_date, '%Y-%m') = ?",
    [$month]
);

// ── 4. Salary paid this month ─────────────────────────────────
$totalSalary = (float) db_value(
    "SELECT COALESCE(SUM(amount_paid), 0)
       FROM salary_payments
      WHERE pay_month = ?",
    [$month]
);

// ── 5. Net profit ─────────────────────────────────────────────
$totalOutflow = $totalExpenses + $totalPurchases + $totalSalary;
$netProfit    = $totalSales - $totalOutflow;

// ── 6. Sales by product ───────────────────────────────────────
$salesByProduct = db_fetch_all(
    "SELECT p.name AS product,
            SUM(s.qty_sold)     AS total_qty,
            SUM(s.total_amount) AS total_amount
       FROM sales s
       JOIN products p ON p.id = s.product_id
      WHERE DATE_FORMAT(s.sale_date, '%Y-%m') = ? {$locSales}
      GROUP BY s.product_id
      ORDER BY total_amount DESC",
    array_merge([$month], $locParam)
);

// ── 7. Sales by location ──────────────────────────────────────
$salesByLocation = db_fetch_all(
    "SELECT l.name AS location,
            SUM(s.total_amount) AS total_amount
       FROM sales s
       JOIN locations l ON l.id = s.location_id
      WHERE DATE_FORMAT(s.sale_date, '%Y-%m') = ?
      GROUP BY s.location_id
      ORDER BY total_amount DESC",
    [$month]
);

// ── 8. Expenses by category ───────────────────────────────────
$expensesByCategory = db_fetch_all(
    "SELECT ec.name AS category,
            SUM(e.amount) AS total_amount
       FROM expenses e
       JOIN expense_categories ec ON ec.id = e.category_id
      WHERE DATE_FORMAT(e.expense_date, '%Y-%m') = ? {$locExpense}
      GROUP BY e.category_id
      ORDER BY total_amount DESC",
    array_merge([$month], $locParam)
);

// ── 9. Daily sales trend ──────────────────────────────────────
$dailyTrend = db_fetch_all(
    "SELECT sale_date,
            SUM(total_amount) AS daily_total,
            SUM(qty_sold)     AS daily_qty
       FROM sales s
      WHERE DATE_FORMAT(sale_date, '%Y-%m') = ? {$locSales}
      GROUP BY sale_date
      ORDER BY sale_date ASC",
    array_merge([$month], $locParam)
);

// ── 10. Purchases breakdown ───────────────────────────────────
$purchases = db_fetch_all(
    "SELECT p.item_name, v.name AS vendor,
            SUM(p.qty)          AS total_qty,
            p.unit,
            SUM(p.total_amount) AS total_amount
       FROM purchases p
       JOIN vendors v ON v.id = p.vendor_id
      WHERE DATE_FORMAT(p.purchase_date, '%Y-%m') = ?
      GROUP BY p.item_name, p.vendor_id, p.unit
      ORDER BY total_amount DESC",
    [$month]
);

// ── 11. Salary breakdown ──────────────────────────────────────
$salaries = db_fetch_all(
    "SELECT e.name AS employee, sp.pay_month,
            SUM(sp.amount_paid) AS total_paid
       FROM salary_payments sp
       JOIN employees e ON e.id = sp.employee_id
      WHERE sp.pay_month = ?
      GROUP BY sp.employee_id
      ORDER BY total_paid DESC",
    [$month]
);

// ── 12. Pending dues as of this month ─────────────────────────
$pendingDues = db_fetch_all(
    "SELECT party_name, due_type, amount_left, due_date
       FROM dues
      WHERE is_cleared = 0
      ORDER BY due_date ASC
      LIMIT 10"
);

$locations = db_fetch_all("SELECT id, name FROM locations ORDER BY id");

require_once __DIR__ . '/../includes/header.php';
?>

<!-- ── Month selector ────────────────────────────────────────-->
<div class="card" style="margin-bottom:1.25rem;">
  <form method="GET" action=""
        style="display:flex; gap:1rem; align-items:flex-end; flex-wrap:wrap;">
    <div class="form-group">
      <label>Month</label>
      <input type="month" name="month" value="<?= htmlspecialchars($month) ?>">
    </div>
    <div class="form-group">
      <label>Location</label>
      <select name="location_id">
        <option value="">All Locations</option>
        <?php foreach ($locations as $loc): ?>
          <option value="<?= $loc['id'] ?>"
            <?= $locationId == $loc['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($loc['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <button type="submit" class="btn btn-primary">📊 Update Report</button>
    <a href="<?= BASE_URL ?>/reports/export.php?month=<?= $month ?>&location_id=<?= $locationId ?>"
       class="btn btn-outline">🖨️ Print / Export</a>
  </form>
</div>

<!-- ── P&L Summary strip ──────────────────────────────────────-->
<div class="stats-grid" style="grid-template-columns:repeat(5,1fr); margin-bottom:1.5rem;">

  <div class="stat-card green">
    <span class="stat-icon">💰</span>
    <span class="stat-label">Total Sales</span>
    <span class="stat-value"><?= CURRENCY_SYMBOL ?><?= number_format($totalSales, 2) ?></span>
    <span class="stat-sub">Income</span>
  </div>

  <div class="stat-card orange">
    <span class="stat-icon">📋</span>
    <span class="stat-label">Expenses</span>
    <span class="stat-value"><?= CURRENCY_SYMBOL ?><?= number_format($totalExpenses, 2) ?></span>
    <span class="stat-sub">Daily kharch</span>
  </div>

  <div class="stat-card orange">
    <span class="stat-icon">🌶</span>
    <span class="stat-label">Purchases</span>
    <span class="stat-value"><?= CURRENCY_SYMBOL ?><?= number_format($totalPurchases, 2) ?></span>
    <span class="stat-sub">Raw materials</span>
  </div>

  <div class="stat-card orange">
    <span class="stat-icon">👥</span>
    <span class="stat-label">Salary</span>
    <span class="stat-value"><?= CURRENCY_SYMBOL ?><?= number_format($totalSalary, 2) ?></span>
    <span class="stat-sub">Staff paid</span>
  </div>

  <div class="stat-card <?= $netProfit >= 0 ? 'green' : 'red' ?>">
    <span class="stat-icon"><?= $netProfit >= 0 ? '📈' : '📉' ?></span>
    <span class="stat-label">Net Profit</span>
    <span class="stat-value"><?= CURRENCY_SYMBOL ?><?= number_format(abs($netProfit), 2) ?></span>
    <span class="stat-sub"><?= $netProfit >= 0 ? 'Profit' : 'Loss' ?></span>
  </div>

</div>

<!-- ── P&L bar visual ─────────────────────────────────────────-->
<div class="card" style="margin-bottom:1.25rem;">
  <div class="card-title">Profit & Loss — <?= $monthLabel ?></div>
  <?php
    $maxBar = max($totalSales, $totalOutflow, 1);
    $bars = [
      ['Income',    $totalSales,     'var(--success)'],
      ['Expenses',  $totalExpenses,  'var(--brand)'],
      ['Purchases', $totalPurchases, '#f59e0b'],
      ['Salary',    $totalSalary,    '#6366f1'],
      ['Net Profit',$netProfit,      $netProfit >= 0 ? 'var(--success)' : 'var(--error)'],
    ];
  ?>
  <div style="display:flex; flex-direction:column; gap:.75rem;">
    <?php foreach ($bars as [$label, $val, $color]): ?>
      <?php $pct = abs($val) / $maxBar * 100; ?>
      <div style="display:grid; grid-template-columns:110px 1fr 120px; gap:.75rem; align-items:center;">
        <span style="font-size:.85rem; color:var(--text-muted);"><?= $label ?></span>
        <div style="background:#eee; border-radius:4px; height:14px;">
          <div style="width:<?= round($pct) ?>%; background:<?= $color ?>;
                      height:14px; border-radius:4px; transition:width .4s;"></div>
        </div>
        <span style="font-size:.88rem; font-weight:600; color:<?= $color ?>; text-align:right;">
          <?= $val < 0 ? '-' : '' ?><?= CURRENCY_SYMBOL ?><?= number_format(abs($val), 2) ?>
        </span>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- ── Row 1: Sales by product + Sales by location ───────────-->
<div style="display:grid; grid-template-columns:1fr 1fr; gap:1.25rem; margin-bottom:1.25rem;">

  <div class="card">
    <div class="card-title">Sales by Product</div>
    <?php if (empty($salesByProduct)): ?>
      <p style="color:var(--text-muted);">No sales this month.</p>
    <?php else: ?>
      <?php $maxProd = max(array_column($salesByProduct, 'total_amount')); ?>
      <table>
        <thead><tr><th>Product</th><th>Qty</th><th>Amount</th><th>Share</th></tr></thead>
        <tbody>
          <?php foreach ($salesByProduct as $row): ?>
            <?php $pct = $maxProd > 0 ? ($row['total_amount'] / $totalSales * 100) : 0; ?>
            <tr>
              <td><?= htmlspecialchars($row['product']) ?></td>
              <td><?= number_format($row['total_qty'], 0) ?></td>
              <td><strong><?= CURRENCY_SYMBOL ?><?= number_format($row['total_amount'], 2) ?></strong></td>
              <td>
                <div style="display:flex; align-items:center; gap:.4rem;">
                  <div style="width:50px; background:#eee; border-radius:4px; height:7px;">
                    <div style="width:<?= round($pct) ?>%; background:var(--success);
                                height:7px; border-radius:4px;"></div>
                  </div>
                  <span style="font-size:.78rem;"><?= number_format($pct, 0) ?>%</span>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr style="font-weight:600; background:#f9f9f9;">
            <td>Total</td><td></td>
            <td><?= CURRENCY_SYMBOL ?><?= number_format($totalSales, 2) ?></td>
            <td></td>
          </tr>
        </tfoot>
      </table>
    <?php endif; ?>
  </div>

  <div class="card">
    <div class="card-title">Sales by Location</div>
    <?php if (empty($salesByLocation)): ?>
      <p style="color:var(--text-muted);">No data.</p>
    <?php else: ?>
      <?php foreach ($salesByLocation as $row): ?>
        <?php $pct = $totalSales > 0 ? ($row['total_amount'] / $totalSales * 100) : 0; ?>
        <div style="margin-bottom:1rem;">
          <div style="display:flex; justify-content:space-between; font-size:.88rem; margin-bottom:.3rem;">
            <strong><?= htmlspecialchars($row['location']) ?></strong>
            <span><?= CURRENCY_SYMBOL ?><?= number_format($row['total_amount'], 2) ?>
              <span style="color:var(--text-muted); font-size:.78rem;">(<?= number_format($pct,1) ?>%)</span>
            </span>
          </div>
          <div style="background:#eee; border-radius:4px; height:10px;">
            <div style="width:<?= round($pct) ?>%; background:var(--brand);
                        height:10px; border-radius:4px;"></div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>

    <div class="card-title" style="margin-top:1.25rem;">Expenses by Category</div>
    <?php if (empty($expensesByCategory)): ?>
      <p style="color:var(--text-muted);">No expenses this month.</p>
    <?php else: ?>
      <table>
        <thead><tr><th>Category</th><th>Amount</th></tr></thead>
        <tbody>
          <?php foreach ($expensesByCategory as $row): ?>
            <tr>
              <td><?= htmlspecialchars($row['category']) ?></td>
              <td><strong style="color:var(--error)">
                <?= CURRENCY_SYMBOL ?><?= number_format($row['total_amount'], 2) ?>
              </strong></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr style="font-weight:600; background:#f9f9f9;">
            <td>Total</td>
            <td style="color:var(--error)"><?= CURRENCY_SYMBOL ?><?= number_format($totalExpenses, 2) ?></td>
          </tr>
        </tfoot>
      </table>
    <?php endif; ?>
  </div>

</div>

<!-- ── Daily trend ────────────────────────────────────────────-->
<div class="card" style="margin-bottom:1.25rem;">
  <div class="card-title">Daily Sales Trend — <?= $monthLabel ?></div>
  <?php if (empty($dailyTrend)): ?>
    <p style="color:var(--text-muted);">No daily data.</p>
  <?php else: ?>
    <?php $maxDay = max(array_column($dailyTrend, 'daily_total')); ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>Date</th><th>Day</th><th>Sales</th><th>Qty</th><th>Bar</th></tr>
        </thead>
        <tbody>
          <?php foreach ($dailyTrend as $row): ?>
            <?php $pct = $maxDay > 0 ? ($row['daily_total'] / $maxDay * 100) : 0; ?>
            <tr>
              <td><?= date(DATE_FORMAT, strtotime($row['sale_date'])) ?></td>
              <td style="color:var(--text-muted);"><?= date('D', strtotime($row['sale_date'])) ?></td>
              <td><strong><?= CURRENCY_SYMBOL ?><?= number_format($row['daily_total'], 2) ?></strong></td>
              <td><?= number_format($row['daily_qty'], 0) ?></td>
              <td style="width:180px;">
                <div style="background:#eee; border-radius:4px; height:10px;">
                  <div style="width:<?= round($pct) ?>%; background:var(--success);
                              height:10px; border-radius:4px;"></div>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<!-- ── Row 2: Purchases + Salary ─────────────────────────────-->
<div style="display:grid; grid-template-columns:1fr 1fr; gap:1.25rem; margin-bottom:1.25rem;">

  <div class="card">
    <div class="card-title">Raw Material Purchases</div>
    <?php if (empty($purchases)): ?>
      <p style="color:var(--text-muted);">No purchases this month.</p>
    <?php else: ?>
      <table>
        <thead><tr><th>Item</th><th>Vendor</th><th>Qty</th><th>Amount</th></tr></thead>
        <tbody>
          <?php foreach ($purchases as $row): ?>
            <tr>
              <td><?= htmlspecialchars($row['item_name']) ?></td>
              <td style="color:var(--text-muted); font-size:.82rem;">
                <?= htmlspecialchars($row['vendor']) ?>
              </td>
              <td><?= number_format($row['total_qty'], 2) ?> <?= $row['unit'] ?></td>
              <td><strong><?= CURRENCY_SYMBOL ?><?= number_format($row['total_amount'], 2) ?></strong></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr style="font-weight:600; background:#f9f9f9;">
            <td colspan="3">Total</td>
            <td><?= CURRENCY_SYMBOL ?><?= number_format($totalPurchases, 2) ?></td>
          </tr>
        </tfoot>
      </table>
    <?php endif; ?>
  </div>

  <div class="card">
    <div class="card-title">Salary Paid</div>
    <?php if (empty($salaries)): ?>
      <p style="color:var(--text-muted);">No salary payments this month.</p>
    <?php else: ?>
      <table>
        <thead><tr><th>Employee</th><th>Amount Paid</th></tr></thead>
        <tbody>
          <?php foreach ($salaries as $row): ?>
            <tr>
              <td><?= htmlspecialchars($row['employee']) ?></td>
              <td><strong><?= CURRENCY_SYMBOL ?><?= number_format($row['total_paid'], 2) ?></strong></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr style="font-weight:600; background:#f9f9f9;">
            <td>Total</td>
            <td><?= CURRENCY_SYMBOL ?><?= number_format($totalSalary, 2) ?></td>
          </tr>
        </tfoot>
      </table>
    <?php endif; ?>

    <!-- Pending dues snapshot -->
    <?php if (!empty($pendingDues)): ?>
      <div class="card-title" style="margin-top:1.25rem;">Pending Dues Snapshot</div>
      <table>
        <thead><tr><th>Party</th><th>Type</th><th>Remaining</th></tr></thead>
        <tbody>
          <?php foreach ($pendingDues as $row): ?>
            <tr>
              <td><?= htmlspecialchars($row['party_name']) ?></td>
              <td><span class="badge badge-warning">
                <?= ucfirst(str_replace('_',' ', $row['due_type'])) ?>
              </span></td>
              <td style="color:var(--error); font-weight:600;">
                <?= CURRENCY_SYMBOL ?><?= number_format($row['amount_left'], 2) ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>