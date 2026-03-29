<?php
// ============================================================
//  sales/summary.php — Monthly / date-range income summary
// ============================================================
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';

$pageTitle    = 'Sales Summary';
$pageSubtitle = 'Income breakdown by product and location';

$filterMonth    = $_GET['month']       ?? date('Y-m');
$filterLocation = $_GET['location_id'] ?? '';

$params = [];
$locWhere = '';
if ($filterLocation) {
    $locWhere  = 'AND s.location_id = ?';
    $params[]  = $filterLocation;
}

// ── Total by product ──────────────────────────────────────────
$byProduct = db_fetch_all(
    "SELECT p.name AS product,
            SUM(s.qty_sold)     AS total_qty,
            SUM(s.total_amount) AS total_amount
       FROM sales s
       JOIN products p ON p.id = s.product_id
      WHERE DATE_FORMAT(s.sale_date, '%Y-%m') = ?
            {$locWhere}
      GROUP BY s.product_id
      ORDER BY total_amount DESC",
    array_merge([$filterMonth], $params)
);

// ── Total by location ─────────────────────────────────────────
$byLocation = db_fetch_all(
    "SELECT l.name AS location,
            SUM(s.total_amount) AS total_amount,
            COUNT(s.id)         AS num_entries
       FROM sales s
       JOIN locations l ON l.id = s.location_id
      WHERE DATE_FORMAT(s.sale_date, '%Y-%m') = ?
            {$locWhere}
      GROUP BY s.location_id
      ORDER BY total_amount DESC",
    array_merge([$filterMonth], $params)
);

// ── Total by payment mode ─────────────────────────────────────
$byPayment = db_fetch_all(
    "SELECT payment_mode,
            SUM(total_amount) AS total_amount,
            COUNT(id)         AS num_entries
       FROM sales
      WHERE DATE_FORMAT(sale_date, '%Y-%m') = ?
      GROUP BY payment_mode",
    [$filterMonth]
);

// ── Daily trend for the month ─────────────────────────────────
$dailyTrend = db_fetch_all(
    "SELECT sale_date,
            SUM(total_amount) AS daily_total
       FROM sales
      WHERE DATE_FORMAT(sale_date, '%Y-%m') = ?
      GROUP BY sale_date
      ORDER BY sale_date ASC",
    [$filterMonth]
);

$grandTotal  = array_sum(array_column($byProduct,  'total_amount'));
$locations   = db_fetch_all("SELECT id, name FROM locations ORDER BY id");

require_once __DIR__ . '/../includes/header.php';
?>

<!-- ── Filter bar ─────────────────────────────────────────────-->
<div class="card">
  <form method="GET" action="">
    <div class="form-grid" style="grid-template-columns:200px 200px auto;">

      <div class="form-group">
        <label>Month</label>
        <input type="month" name="month" value="<?= htmlspecialchars($filterMonth) ?>">
      </div>

      <div class="form-group">
        <label>Location</label>
        <select name="location_id">
          <option value="">All Locations</option>
          <?php foreach ($locations as $loc): ?>
            <option value="<?= $loc['id'] ?>"
              <?= $filterLocation == $loc['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($loc['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group" style="justify-content:flex-end;">
        <label>&nbsp;</label>
        <button type="submit" class="btn btn-primary">🔍 Show</button>
      </div>

    </div>
  </form>
</div>

<!-- ── Grand total banner ─────────────────────────────────────-->
<div class="stat-card green" style="margin-bottom:1.25rem;">
  <span class="stat-icon">💰</span>
  <span class="stat-label">Total Income — <?= date('F Y', strtotime($filterMonth . '-01')) ?></span>
  <span class="stat-value"><?= CURRENCY_SYMBOL ?><?= number_format($grandTotal, 2) ?></span>
  <span class="stat-sub"><?= count($dailyTrend) ?> selling day(s) this month</span>
</div>

<!-- ── Two-column breakdown ───────────────────────────────────-->
<div style="display:grid; grid-template-columns:1fr 1fr; gap:1.25rem; margin-bottom:1.25rem;">

  <!-- By Product -->
  <div class="card">
    <div class="card-title">By Product</div>
    <table>
      <thead>
        <tr><th>Product</th><th>Qty Sold</th><th>Amount</th><th>Share</th></tr>
      </thead>
      <tbody>
        <?php foreach ($byProduct as $row): ?>
          <?php $share = $grandTotal > 0 ? ($row['total_amount'] / $grandTotal * 100) : 0; ?>
          <tr>
            <td><?= htmlspecialchars($row['product']) ?></td>
            <td><?= number_format($row['total_qty'], 1) ?></td>
            <td><strong><?= CURRENCY_SYMBOL ?><?= number_format($row['total_amount'], 2) ?></strong></td>
            <td>
              <div style="display:flex; align-items:center; gap:.4rem;">
                <div style="width:60px; background:#eee; border-radius:4px; height:7px;">
                  <div style="width:<?= round($share) ?>%; background:var(--brand); height:7px; border-radius:4px;"></div>
                </div>
                <span style="font-size:.8rem;"><?= number_format($share, 1) ?>%</span>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- By Location -->
  <div class="card">
    <div class="card-title">By Location</div>
    <table>
      <thead>
        <tr><th>Location</th><th>Entries</th><th>Amount</th></tr>
      </thead>
      <tbody>
        <?php foreach ($byLocation as $row): ?>
          <tr>
            <td><?= htmlspecialchars($row['location']) ?></td>
            <td><?= $row['num_entries'] ?></td>
            <td><strong><?= CURRENCY_SYMBOL ?><?= number_format($row['total_amount'], 2) ?></strong></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <div class="card-title" style="margin-top:1.25rem;">By Payment Mode</div>
    <table>
      <thead>
        <tr><th>Mode</th><th>Entries</th><th>Amount</th></tr>
      </thead>
      <tbody>
        <?php foreach ($byPayment as $row): ?>
          <tr>
            <td>
              <span class="badge <?= $row['payment_mode'] === 'cash' ? 'badge-success' : 'badge-info' ?>">
                <?= ucfirst($row['payment_mode']) ?>
              </span>
            </td>
            <td><?= $row['num_entries'] ?></td>
            <td><strong><?= CURRENCY_SYMBOL ?><?= number_format($row['total_amount'], 2) ?></strong></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

</div>

<!-- ── Daily trend table ──────────────────────────────────────-->
<div class="card">
  <div class="card-title">Daily Trend — <?= date('F Y', strtotime($filterMonth . '-01')) ?></div>
  <?php if (empty($dailyTrend)): ?>
    <p style="color:var(--text-muted);">No sales recorded for this month.</p>
  <?php else: ?>
    <?php $maxDay = max(array_column($dailyTrend, 'daily_total')); ?>
    <table>
      <thead>
        <tr><th>Date</th><th>Day</th><th>Sales</th><th>Bar</th></tr>
      </thead>
      <tbody>
        <?php foreach ($dailyTrend as $row): ?>
          <?php $pct = $maxDay > 0 ? ($row['daily_total'] / $maxDay * 100) : 0; ?>
          <tr>
            <td><?= date(DATE_FORMAT, strtotime($row['sale_date'])) ?></td>
            <td style="color:var(--text-muted);"><?= date('D', strtotime($row['sale_date'])) ?></td>
            <td><strong><?= CURRENCY_SYMBOL ?><?= number_format($row['daily_total'], 2) ?></strong></td>
            <td style="width:200px;">
              <div style="background:#eee; border-radius:4px; height:10px;">
                <div style="width:<?= round($pct) ?>%; background:var(--success); height:10px; border-radius:4px;"></div>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>