<?php
// ============================================================
//  sales/index.php — Sales Log
// ============================================================
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';

$pageTitle    = 'Sales';
$pageSubtitle = 'Daily sales log';

// ── Filters ───────────────────────────────────────────────────
$filterDate     = $_GET['date']        ?? date('Y-m-d');
$filterLocation = $_GET['location_id'] ?? '';
$filterProduct  = $_GET['product_id']  ?? '';

$params = [];
$where  = ['1=1'];

if ($filterDate) {
    $where[]  = 's.sale_date = ?';
    $params[] = $filterDate;
}
if ($filterLocation) {
    $where[]  = 's.location_id = ?';
    $params[] = $filterLocation;
}
if ($filterProduct) {
    $where[]  = 's.product_id = ?';
    $params[] = $filterProduct;
}

$whereSQL = implode(' AND ', $where);

$sales = db_fetch_all(
    "SELECT s.id, s.sale_date, s.qty_sold, s.unit_price,
            s.total_amount, s.payment_mode, s.note,
            p.name AS product, l.name AS location
       FROM sales s
       JOIN products  p ON p.id = s.product_id
       JOIN locations l ON l.id = s.location_id
      WHERE {$whereSQL}
      ORDER BY s.id DESC",
    $params
);

// Totals for filtered result
$grandTotal = array_sum(array_column($sales, 'total_amount'));

$locations = db_fetch_all("SELECT id, name FROM locations ORDER BY id");
$products  = db_fetch_all("SELECT id, name FROM products WHERE is_active = 1 ORDER BY name");

require_once __DIR__ . '/../includes/header.php';
?>

<!-- ── Filter bar ─────────────────────────────────────────────-->
<div class="card">
  <form method="GET" action="">
    <div class="form-grid" style="grid-template-columns: repeat(auto-fill, minmax(180px,1fr));">

      <div class="form-group">
        <label>Date</label>
        <input type="date" name="date" value="<?= htmlspecialchars($filterDate) ?>">
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

      <div class="form-group">
        <label>Product</label>
        <select name="product_id">
          <option value="">All Products</option>
          <?php foreach ($products as $prod): ?>
            <option value="<?= $prod['id'] ?>"
              <?= $filterProduct == $prod['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($prod['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group" style="justify-content:flex-end;">
        <label>&nbsp;</label>
        <div style="display:flex; gap:.5rem;">
          <button type="submit" class="btn btn-primary">🔍 Filter</button>
          <a href="<?= BASE_URL ?>/sales/index.php" class="btn btn-outline">Reset</a>
        </div>
      </div>

    </div>
  </form>
</div>

<!-- ── Summary strip ──────────────────────────────────────────-->
<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
  <div>
    <strong><?= count($sales) ?></strong> record(s) found
    &nbsp;|&nbsp;
    Total: <strong style="color:var(--success)"><?= CURRENCY_SYMBOL ?><?= number_format($grandTotal, 2) ?></strong>
  </div>
  <a href="<?= BASE_URL ?>/sales/add.php" class="btn btn-primary">➕ Add Sale</a>
</div>

<!-- ── Table ──────────────────────────────────────────────────-->
<div class="card">
  <div class="table-wrap">
    <?php if (empty($sales)): ?>
      <p style="color:var(--text-muted); padding:.5rem 0;">No sales found for the selected filters.</p>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>Date</th>
            <th>Product</th>
            <th>Location</th>
            <th>Qty</th>
            <th>Rate</th>
            <th>Total</th>
            <th>Payment</th>
            <th>Note</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($sales as $i => $row): ?>
            <tr>
              <td><?= $i + 1 ?></td>
              <td><?= date(DATE_FORMAT, strtotime($row['sale_date'])) ?></td>
              <td><?= htmlspecialchars($row['product']) ?></td>
              <td><?= htmlspecialchars($row['location']) ?></td>
              <td><?= $row['qty_sold'] ?></td>
              <td><?= CURRENCY_SYMBOL ?><?= number_format($row['unit_price'], 2) ?></td>
              <td><strong><?= CURRENCY_SYMBOL ?><?= number_format($row['total_amount'], 2) ?></strong></td>
              <td>
                <span class="badge <?= $row['payment_mode'] === 'cash' ? 'badge-success' : 'badge-info' ?>">
                  <?= ucfirst($row['payment_mode']) ?>
                </span>
              </td>
              <td style="color:var(--text-muted); font-size:.82rem;">
                <?= htmlspecialchars($row['note'] ?? '—') ?>
              </td>
              <td>
                <a href="<?= BASE_URL ?>/sales/delete.php?id=<?= $row['id'] ?>&token=<?= $_SESSION['csrf_token'] ?>"
                   class="btn btn-danger btn-sm"
                   data-confirm="Delete this sale entry?">🗑</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr style="background:#f9f9f9; font-weight:600;">
            <td colspan="6" style="text-align:right;">Grand Total</td>
            <td><?= CURRENCY_SYMBOL ?><?= number_format($grandTotal, 2) ?></td>
            <td colspan="3"></td>
          </tr>
        </tfoot>
      </table>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>