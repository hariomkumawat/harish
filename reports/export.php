<?php
// ============================================================
//  reports/export.php — Print-ready report
// ============================================================
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';

$month      = $_GET['month']       ?? date('Y-m');
$locationId = $_GET['location_id'] ?? '';

if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    $month = date('Y-m');
}

$monthLabel = date('F Y', strtotime($month . '-01'));

$locParam   = [];
$locSales   = '';
$locExpense = '';
if ($locationId) {
    $locSales   = 'AND location_id = ?';
    $locExpense = 'AND location_id = ?';
    $locParam   = [$locationId];
}

$totalSales = (float) db_value(
    "SELECT COALESCE(SUM(total_amount),0) FROM sales
      WHERE DATE_FORMAT(sale_date,'%Y-%m')=? {$locSales}",
    array_merge([$month], $locParam)
);
$totalExpenses = (float) db_value(
    "SELECT COALESCE(SUM(amount),0) FROM expenses
      WHERE DATE_FORMAT(expense_date,'%Y-%m')=? {$locExpense}",
    array_merge([$month], $locParam)
);
$totalPurchases = (float) db_value(
    "SELECT COALESCE(SUM(total_amount),0) FROM purchases
      WHERE DATE_FORMAT(purchase_date,'%Y-%m')=?", [$month]
);
$totalSalary = (float) db_value(
    "SELECT COALESCE(SUM(amount_paid),0) FROM salary_payments
      WHERE pay_month=?", [$month]
);
$netProfit = $totalSales - $totalExpenses - $totalPurchases - $totalSalary;

$salesByProduct = db_fetch_all(
    "SELECT p.name AS product,
            SUM(s.qty_sold) AS qty,
            SUM(s.total_amount) AS amount
       FROM sales s JOIN products p ON p.id=s.product_id
      WHERE DATE_FORMAT(s.sale_date,'%Y-%m')=? {$locSales}
      GROUP BY s.product_id ORDER BY amount DESC",
    array_merge([$month], $locParam)
);
$expensesByCategory = db_fetch_all(
    "SELECT ec.name AS category, SUM(e.amount) AS amount
       FROM expenses e JOIN expense_categories ec ON ec.id=e.category_id
      WHERE DATE_FORMAT(e.expense_date,'%Y-%m')=? {$locExpense}
      GROUP BY e.category_id ORDER BY amount DESC",
    array_merge([$month], $locParam)
);
$pendingDues = db_fetch_all(
    "SELECT party_name, due_type, amount_left
       FROM dues WHERE is_cleared=0 ORDER BY due_date ASC"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?= APP_NAME ?> — Report <?= $monthLabel ?></title>
  <style>
    * { box-sizing:border-box; margin:0; padding:0; }
    body { font-family:'Segoe UI',sans-serif; font-size:13px;
           color:#111; background:#fff; padding:24px; }
    h1   { font-size:18px; margin-bottom:2px; }
    h2   { font-size:13px; font-weight:600; margin:16px 0 6px;
           border-bottom:1px solid #ddd; padding-bottom:4px; }
    .meta { color:#666; font-size:11px; margin-bottom:16px; }
    table { width:100%; border-collapse:collapse; margin-bottom:12px; font-size:12px; }
    th,td { padding:5px 8px; border:1px solid #ddd; text-align:left; }
    th    { background:#f5f5f5; font-weight:600; }
    .summary { display:grid; grid-template-columns:repeat(5,1fr); gap:8px; margin-bottom:16px; }
    .sbox    { border:1px solid #ddd; border-radius:4px; padding:8px; text-align:center; }
    .sbox .val { font-size:15px; font-weight:700; margin-top:4px; }
    .green { color:#2e7d32; } .red { color:#b71c1c; } .orange { color:#e65c00; }
    .footer { margin-top:20px; font-size:11px; color:#999; border-top:1px solid #eee;
              padding-top:8px; display:flex; justify-content:space-between; }
    @media print {
      .no-print { display:none; }
      body { padding:12px; }
    }
  </style>
</head>
<body>

<!-- Print button -->
<div class="no-print" style="margin-bottom:16px; display:flex; gap:8px;">
  <button onclick="window.print()"
          style="padding:6px 16px; background:#e65c00; color:#fff;
                 border:none; border-radius:4px; cursor:pointer; font-size:13px;">
    🖨️ Print
  </button>
  <a href="<?= BASE_URL ?>/reports/monthly.php?month=<?= $month ?>"
     style="padding:6px 16px; border:1px solid #ccc; border-radius:4px;
            text-decoration:none; color:#333; font-size:13px;">
    ← Back to Report
  </a>
</div>

<!-- Header -->
<h1>🍽 <?= APP_NAME ?> — Monthly Report</h1>
<div class="meta">
  Month: <strong><?= $monthLabel ?></strong>
  &nbsp;|&nbsp; Printed: <?= date('d/m/Y h:i A') ?>
  &nbsp;|&nbsp; By: <?= htmlspecialchars($adminName) ?>
</div>

<!-- P&L Summary -->
<h2>Profit & Loss Summary</h2>
<div class="summary">
  <div class="sbox">
    <div style="font-size:11px; color:#666;">Sales</div>
    <div class="val green"><?= CURRENCY_SYMBOL ?><?= number_format($totalSales, 2) ?></div>
  </div>
  <div class="sbox">
    <div style="font-size:11px; color:#666;">Expenses</div>
    <div class="val orange"><?= CURRENCY_SYMBOL ?><?= number_format($totalExpenses, 2) ?></div>
  </div>
  <div class="sbox">
    <div style="font-size:11px; color:#666;">Purchases</div>
    <div class="val orange"><?= CURRENCY_SYMBOL ?><?= number_format($totalPurchases, 2) ?></div>
  </div>
  <div class="sbox">
    <div style="font-size:11px; color:#666;">Salary</div>
    <div class="val orange"><?= CURRENCY_SYMBOL ?><?= number_format($totalSalary, 2) ?></div>
  </div>
  <div class="sbox">
    <div style="font-size:11px; color:#666;">Net Profit</div>
    <div class="val <?= $netProfit >= 0 ? 'green' : 'red' ?>">
      <?= $netProfit < 0 ? '-' : '' ?><?= CURRENCY_SYMBOL ?><?= number_format(abs($netProfit), 2) ?>
    </div>
  </div>
</div>

<!-- Sales by product -->
<h2>Sales by Product</h2>
<table>
  <thead><tr><th>Product</th><th>Qty Sold</th><th>Amount</th></tr></thead>
  <tbody>
    <?php foreach ($salesByProduct as $row): ?>
      <tr>
        <td><?= htmlspecialchars($row['product']) ?></td>
        <td><?= number_format($row['qty'], 0) ?></td>
        <td><?= CURRENCY_SYMBOL ?><?= number_format($row['amount'], 2) ?></td>
      </tr>
    <?php endforeach; ?>
    <tr style="font-weight:600; background:#f5f5f5;">
      <td colspan="2">Total</td>
      <td><?= CURRENCY_SYMBOL ?><?= number_format($totalSales, 2) ?></td>
    </tr>
  </tbody>
</table>

<!-- Expenses by category -->
<h2>Expenses by Category</h2>
<table>
  <thead><tr><th>Category</th><th>Amount</th></tr></thead>
  <tbody>
    <?php foreach ($expensesByCategory as $row): ?>
      <tr>
        <td><?= htmlspecialchars($row['category']) ?></td>
        <td><?= CURRENCY_SYMBOL ?><?= number_format($row['amount'], 2) ?></td>
      </tr>
    <?php endforeach; ?>
    <tr style="font-weight:600; background:#f5f5f5;">
      <td>Total</td>
      <td><?= CURRENCY_SYMBOL ?><?= number_format($totalExpenses, 2) ?></td>
    </tr>
  </tbody>
</table>

<!-- Pending dues -->
<?php if (!empty($pendingDues)): ?>
<h2>Pending Dues & EMI</h2>
<table>
  <thead><tr><th>Party</th><th>Type</th><th>Remaining</th></tr></thead>
  <tbody>
    <?php foreach ($pendingDues as $row): ?>
      <tr>
        <td><?= htmlspecialchars($row['party_name']) ?></td>
        <td><?= ucfirst(str_replace('_',' ',$row['due_type'])) ?></td>
        <td><?= CURRENCY_SYMBOL ?><?= number_format($row['amount_left'], 2) ?></td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>

<div class="footer">
  <span><?= APP_NAME ?> Admin Panel v<?= APP_VERSION ?></span>
  <span>Report generated: <?= date('d/m/Y h:i A') ?></span>
</div>

</body>
</html>