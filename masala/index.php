<?php
// ============================================================
//  masala/index.php — Raw Material Purchase Log
// ============================================================
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';

$pageTitle    = 'Masala / Purchases';
$pageSubtitle = 'Raw material purchase log — Munim Ji & vendors';

// ── Filters ───────────────────────────────────────────────────
$filterVendor  = $_GET['vendor_id']  ?? '';
$filterMonth   = $_GET['month']      ?? date('Y-m');
$filterUnpaid  = $_GET['unpaid']     ?? '';

$params = [];
$where  = ['1=1'];

if ($filterVendor) {
    $where[]  = 'p.vendor_id = ?';
    $params[] = $filterVendor;
}
if ($filterMonth) {
    $where[]  = "DATE_FORMAT(p.purchase_date, '%Y-%m') = ?";
    $params[] = $filterMonth;
}
if ($filterUnpaid === '1') {
    $where[] = 'p.is_paid = 0';
}

$whereSQL = implode(' AND ', $where);

$purchases = db_fetch_all(
    "SELECT p.*, v.name AS vendor_name
       FROM purchases p
       JOIN vendors v ON v.id = p.vendor_id
      WHERE {$whereSQL}
      ORDER BY p.purchase_date DESC, p.id DESC",
    $params
);

// ── Summary for filtered result ───────────────────────────────
$grandTotal  = array_sum(array_column($purchases, 'total_amount'));
$unpaidTotal = array_sum(array_column(
    array_filter($purchases, fn($r) => !$r['is_paid']),
    'total_amount'
));

// ── Month-level stats (regardless of filter) ──────────────────
$monthTotal = (float) db_value(
    "SELECT COALESCE(SUM(total_amount),0) FROM purchases
      WHERE DATE_FORMAT(purchase_date,'%Y-%m') = ?",
    [date('Y-m')]
);
$monthUnpaid = (float) db_value(
    "SELECT COALESCE(SUM(total_amount),0) FROM purchases
      WHERE DATE_FORMAT(purchase_date,'%Y-%m') = ? AND is_paid = 0",
    [date('Y-m')]
);
$totalUnpaidEver = (float) db_value(
    "SELECT COALESCE(SUM(total_amount),0) FROM purchases WHERE is_paid = 0"
);
$totalPurchasesCount = (int) db_value("SELECT COUNT(*) FROM purchases");

// ── Vendors for dropdown ──────────────────────────────────────
$vendors = db_fetch_all("SELECT id, name FROM vendors ORDER BY name");

// ── Top items this month ──────────────────────────────────────
$topItems = db_fetch_all(
    "SELECT item_name, SUM(total_amount) AS total, SUM(qty) AS total_qty, unit
       FROM purchases
      WHERE DATE_FORMAT(purchase_date,'%Y-%m') = ?
      GROUP BY item_name, unit
      ORDER BY total DESC
      LIMIT 5",
    [date('Y-m')]
);

require_once __DIR__ . '/../includes/header.php';
?>

<!-- ── Stat cards ─────────────────────────────────────────────-->
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr); margin-bottom:1.25rem;">
  <div class="stat-card green">
    <span class="stat-icon">🛒</span>
    <span class="stat-label">This Month Spend</span>
    <span class="stat-value"><?= CURRENCY_SYMBOL ?><?= number_format($monthTotal, 0) ?></span>
    <span class="stat-sub"><?= date('M Y') ?></span>
  </div>
  <div class="stat-card <?= $monthUnpaid > 0 ? 'red' : 'green' ?>">
    <span class="stat-icon">⏳</span>
    <span class="stat-label">Unpaid — This Month</span>
    <span class="stat-value"><?= CURRENCY_SYMBOL ?><?= number_format($monthUnpaid, 0) ?></span>
    <span class="stat-sub">Credit purchases</span>
  </div>
  <div class="stat-card <?= $totalUnpaidEver > 0 ? 'red' : 'green' ?>">
    <span class="stat-icon">🔔</span>
    <span class="stat-label">Total Unpaid (Ever)</span>
    <span class="stat-value"><?= CURRENCY_SYMBOL ?><?= number_format($totalUnpaidEver, 0) ?></span>
    <span class="stat-sub">All vendors</span>
  </div>
  <div class="stat-card green">
    <span class="stat-icon">📋</span>
    <span class="stat-label">Total Entries</span>
    <span class="stat-value"><?= $totalPurchasesCount ?></span>
    <span class="stat-sub">All time</span>
  </div>
</div>

<!-- ── Unpaid alert ───────────────────────────────────────────-->
<?php if ($totalUnpaidEver > 0 && !$filterUnpaid): ?>
  <div class="alert alert-warning" style="display:flex; justify-content:space-between; align-items:center;">
    <span>⏳ <strong><?= CURRENCY_SYMBOL ?><?= number_format($totalUnpaidEver, 2) ?></strong> worth of purchases are unpaid (credit).</span>
    <a href="?unpaid=1" class="btn btn-outline btn-sm">View Unpaid Only →</a>
  </div>
<?php endif; ?>

<!-- ── Filter bar ─────────────────────────────────────────────-->
<div class="card">
  <form method="GET" action="">
    <div class="form-grid" style="grid-template-columns:repeat(auto-fill,minmax(180px,1fr));">

      <div class="form-group">
        <label>Month</label>
        <input type="month" name="month" value="<?= htmlspecialchars($filterMonth) ?>">
      </div>

      <div class="form-group">
        <label>Vendor</label>
        <select name="vendor_id">
          <option value="">All Vendors</option>
          <?php foreach ($vendors as $v): ?>
            <option value="<?= $v['id'] ?>" <?= $filterVendor == $v['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($v['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label>Payment Status</label>
        <select name="unpaid">
          <option value="">All</option>
          <option value="1" <?= $filterUnpaid === '1' ? 'selected' : '' ?>>Unpaid / Credit Only</option>
        </select>
      </div>

      <div class="form-group" style="justify-content:flex-end;">
        <label>&nbsp;</label>
        <div style="display:flex; gap:.5rem;">
          <button type="submit" class="btn btn-primary">🔍 Filter</button>
          <a href="<?= BASE_URL ?>/masala/index.php" class="btn btn-outline">Reset</a>
        </div>
      </div>

    </div>
  </form>
</div>

<!-- ── Actions strip ──────────────────────────────────────────-->
<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; flex-wrap:wrap; gap:.75rem;">
  <div style="color:var(--text-muted); font-size:.88rem;">
    <strong><?= count($purchases) ?></strong> record(s)
    &nbsp;|&nbsp; Total: <strong style="color:var(--brand);"><?= CURRENCY_SYMBOL ?><?= number_format($grandTotal, 2) ?></strong>
    <?php if ($unpaidTotal > 0): ?>
      &nbsp;|&nbsp; Unpaid: <strong style="color:var(--error);"><?= CURRENCY_SYMBOL ?><?= number_format($unpaidTotal, 2) ?></strong>
    <?php endif; ?>
  </div>
  <a href="<?= BASE_URL ?>/masala/add.php" class="btn btn-primary">➕ New Purchase Entry</a>
</div>

<!-- ── Two-column layout ──────────────────────────────────────-->
<div style="display:grid; grid-template-columns:1fr 260px; gap:1.25rem; align-items:start;">

  <!-- Main table -->
  <div class="card">
    <div class="table-wrap">
      <?php if (empty($purchases)): ?>
        <p style="color:var(--text-muted); padding:.5rem 0;">
          No purchases found.
          <a href="<?= BASE_URL ?>/masala/add.php">Add first entry →</a>
        </p>
      <?php else: ?>
        <table>
          <thead>
            <tr>
              <th>#</th>
              <th>Date</th>
              <th>Vendor</th>
              <th>Item</th>
              <th>Qty</th>
              <th>Rate</th>
              <th>Total</th>
              <th>Payment</th>
              <th>Status</th>
              <th>Note</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($purchases as $i => $row): ?>
              <?php $rowStyle = !$row['is_paid'] ? 'background:#fff8f0;' : ''; ?>
              <tr style="<?= $rowStyle ?>">
                <td><?= $i + 1 ?></td>
                <td><?= date(DATE_FORMAT, strtotime($row['purchase_date'])) ?></td>
                <td>
                  <span style="font-weight:600; font-size:.88rem;">
                    <?= htmlspecialchars($row['vendor_name']) ?>
                  </span>
                </td>
                <td><?= htmlspecialchars($row['item_name']) ?></td>
                <td><?= number_format((float)$row['qty'], 3) ?> <?= htmlspecialchars($row['unit']) ?></td>
                <td><?= CURRENCY_SYMBOL ?><?= number_format((float)$row['rate'], 2) ?></td>
                <td>
                  <strong><?= CURRENCY_SYMBOL ?><?= number_format((float)$row['total_amount'], 2) ?></strong>
                </td>
                <td>
                  <span class="badge <?= $row['payment_mode'] === 'cash' ? 'badge-success' : ($row['payment_mode'] === 'upi' ? 'badge-info' : 'badge-warning') ?>">
                    <?= ucfirst($row['payment_mode']) ?>
                  </span>
                </td>
                <td>
                  <?php if ($row['is_paid']): ?>
                    <span class="badge badge-success">✅ Paid</span>
                  <?php else: ?>
                    <a href="<?= BASE_URL ?>/masala/index.php?mark_paid=<?= $row['id'] ?>&token=<?= $_SESSION['csrf_token'] ?>"
                       class="badge badge-warning"
                       style="cursor:pointer; text-decoration:none;"
                       title="Click to mark as paid">
                      ⏳ Unpaid
                    </a>
                  <?php endif; ?>
                </td>
                <td style="color:var(--text-muted); font-size:.82rem;">
                  <?= htmlspecialchars($row['note'] ?? '—') ?>
                </td>
                <td>
                  <div style="display:flex; gap:.35rem;">
                    <a href="<?= BASE_URL ?>/masala/add.php?edit=<?= $row['id'] ?>"
                       class="btn btn-outline btn-sm" title="Edit">✏️</a>
                    <a href="<?= BASE_URL ?>/masala/delete.php?id=<?= $row['id'] ?>&token=<?= $_SESSION['csrf_token'] ?>"
                       class="btn btn-danger btn-sm"
                       data-confirm="Delete this purchase entry?"
                       title="Delete">🗑</a>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr style="background:#f9f9f9; font-weight:600;">
              <td colspan="6" style="text-align:right;">Total</td>
              <td><?= CURRENCY_SYMBOL ?><?= number_format($grandTotal, 2) ?></td>
              <td colspan="4"></td>
            </tr>
          </tfoot>
        </table>
      <?php endif; ?>
    </div>
  </div>

  <!-- Sidebar: top items -->
  <div>
    <?php if (!empty($topItems)): ?>
    <div class="card">
      <div class="card-title">Top Items — <?= date('M Y') ?></div>
      <?php $maxVal = max(array_column($topItems, 'total')); ?>
      <?php foreach ($topItems as $item): ?>
        <?php $pct = $maxVal > 0 ? ((float)$item['total'] / $maxVal * 100) : 0; ?>
        <div style="margin-bottom:.9rem;">
          <div style="display:flex; justify-content:space-between; font-size:.83rem; margin-bottom:.25rem;">
            <span style="font-weight:600;"><?= htmlspecialchars($item['item_name']) ?></span>
            <strong><?= CURRENCY_SYMBOL ?><?= number_format((float)$item['total'], 0) ?></strong>
          </div>
          <div style="font-size:.76rem; color:var(--text-muted); margin-bottom:.2rem;">
            <?= number_format((float)$item['total_qty'], 2) ?> <?= htmlspecialchars($item['unit']) ?>
          </div>
          <div style="background:#eee; border-radius:4px; height:7px;">
            <div style="width:<?= round($pct) ?>%; background:var(--brand); height:7px; border-radius:4px;"></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Vendor summary -->
    <?php
    $vendorBreakdown = db_fetch_all(
        "SELECT v.name AS vendor_name, COUNT(p.id) AS count,
                SUM(p.total_amount) AS total,
                SUM(CASE WHEN p.is_paid=0 THEN p.total_amount ELSE 0 END) AS unpaid
           FROM purchases p
           JOIN vendors v ON v.id = p.vendor_id
          WHERE DATE_FORMAT(p.purchase_date,'%Y-%m') = ?
          GROUP BY p.vendor_id
          ORDER BY total DESC",
        [$filterMonth ?: date('Y-m')]
    );
    ?>
    <?php if (!empty($vendorBreakdown)): ?>
    <div class="card" style="margin-top:1.25rem;">
      <div class="card-title">By Vendor — <?= date('M Y', strtotime(($filterMonth ?: date('Y-m')) . '-01')) ?></div>
      <?php foreach ($vendorBreakdown as $vb): ?>
        <div style="margin-bottom:.85rem; padding-bottom:.85rem; border-bottom:1px solid var(--border);">
          <div style="font-weight:600; font-size:.88rem; margin-bottom:.2rem;">
            <?= htmlspecialchars($vb['vendor_name']) ?>
          </div>
          <div style="display:flex; justify-content:space-between; font-size:.82rem; color:var(--text-muted);">
            <span><?= $vb['count'] ?> entries</span>
            <strong style="color:var(--text-primary);"><?= CURRENCY_SYMBOL ?><?= number_format((float)$vb['total'], 2) ?></strong>
          </div>
          <?php if ((float)$vb['unpaid'] > 0): ?>
            <div style="font-size:.78rem; color:var(--error); margin-top:.15rem;">
              Unpaid: <?= CURRENCY_SYMBOL ?><?= number_format((float)$vb['unpaid'], 2) ?>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

</div>

<?php
// ── Mark as paid (inline quick action) ───────────────────────
if (isset($_GET['mark_paid']) && isset($_GET['token'])) {
    $markId = (int)$_GET['mark_paid'];
    if ($markId && hash_equals($_SESSION['csrf_token'], $_GET['token'])) {
        db_run("UPDATE purchases SET is_paid = 1 WHERE id = ?", [$markId]);
        $_SESSION['flash_success'] = 'Purchase marked as paid.';
    }
    header('Location: ' . BASE_URL . '/masala/index.php' . (isset($_GET['month']) ? '?month=' . $_GET['month'] : ''));
    exit;
}
?>

<script>
document.querySelectorAll('[data-confirm]').forEach(el => {
  el.addEventListener('click', e => {
    if (!confirm(el.dataset.confirm)) e.preventDefault();
  });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>