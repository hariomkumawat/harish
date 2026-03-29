<?php
// ============================================================
//  inventory/index.php — Stock Items List
// ============================================================
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';

$pageTitle    = 'Inventory';
$pageSubtitle = 'Stock levels & raw material tracking';

// ── Filters ───────────────────────────────────────────────────
$filterCategory  = $_GET['category']   ?? '';
$filterLowStock  = $_GET['low_stock']  ?? '';
$filterSearch    = trim($_GET['search'] ?? '');

$params = [];
$where  = ['1=1'];

if ($filterCategory) {
    $where[]  = 'category = ?';
    $params[] = $filterCategory;
}
if ($filterLowStock === '1') {
    $where[] = 'qty_in_hand <= low_stock_at';
}
if ($filterSearch !== '') {
    $where[]  = 'name LIKE ?';
    $params[] = '%' . $filterSearch . '%';
}

$whereSQL = implode(' AND ', $where);

$items = db_fetch_all(
    "SELECT *,
            CASE WHEN qty_in_hand <= low_stock_at THEN 1 ELSE 0 END AS is_low
       FROM stock_items
      WHERE {$whereSQL}
      ORDER BY category, name",
    $params
);

// ── Summary counts ────────────────────────────────────────────
$totalItems    = (int) db_value("SELECT COUNT(*) FROM stock_items");
$lowStockCount = (int) db_value("SELECT COUNT(*) FROM stock_items WHERE qty_in_hand <= low_stock_at");
$outOfStock    = (int) db_value("SELECT COUNT(*) FROM stock_items WHERE qty_in_hand = 0");

// ── Distinct categories for filter dropdown ───────────────────
$categories = db_fetch_all("SELECT DISTINCT category FROM stock_items ORDER BY category");

// ── Category labels (for display) ────────────────────────────
$categoryLabels = [
    'masala'      => '🌶 Masala',
    'chatni'      => '🥫 Chatni',
    'sev'         => '🍟 Sev',
    'flour'       => '🌾 Flour',
    'oil'         => '🫙 Oil',
    'vegetable'   => '🥦 Vegetable',
    'packaging'   => '📦 Packaging',
    'other'       => '🗂 Other',
];

require_once __DIR__ . '/../includes/header.php';
?>

<!-- ── Stat cards ─────────────────────────────────────────────-->
<div class="stats-grid" style="grid-template-columns:repeat(3,1fr); margin-bottom:1.25rem;">
  <div class="stat-card green">
    <span class="stat-icon">📦</span>
    <span class="stat-label">Total Items</span>
    <span class="stat-value"><?= $totalItems ?></span>
    <span class="stat-sub">In inventory</span>
  </div>
  <div class="stat-card <?= $lowStockCount > 0 ? 'orange' : 'green' ?>">
    <span class="stat-icon">⚠️</span>
    <span class="stat-label">Low Stock</span>
    <span class="stat-value"><?= $lowStockCount ?></span>
    <span class="stat-sub">Below threshold</span>
  </div>
  <div class="stat-card <?= $outOfStock > 0 ? 'red' : 'green' ?>">
    <span class="stat-icon">🚫</span>
    <span class="stat-label">Out of Stock</span>
    <span class="stat-value"><?= $outOfStock ?></span>
    <span class="stat-sub">Zero quantity</span>
  </div>
</div>

<!-- ── Low stock alert banner ─────────────────────────────────-->
<?php if ($lowStockCount > 0 && !$filterLowStock): ?>
  <div class="alert alert-warning" style="display:flex; justify-content:space-between; align-items:center;">
    <span>⚠️ <strong><?= $lowStockCount ?> item(s)</strong> are running low on stock!</span>
    <a href="?low_stock=1" class="btn btn-outline btn-sm">View Low Stock Only →</a>
  </div>
<?php endif; ?>

<!-- ── Filter bar ─────────────────────────────────────────────-->
<div class="card">
  <form method="GET" action="">
    <div class="form-grid" style="grid-template-columns:repeat(auto-fill,minmax(180px,1fr));">

      <div class="form-group">
        <label>Search</label>
        <input type="text" name="search"
               value="<?= htmlspecialchars($filterSearch) ?>"
               placeholder="Item name…">
      </div>

      <div class="form-group">
        <label>Category</label>
        <select name="category">
          <option value="">All Categories</option>
          <?php foreach ($categories as $cat): ?>
            <option value="<?= $cat['category'] ?>"
              <?= $filterCategory === $cat['category'] ? 'selected' : '' ?>>
              <?= $categoryLabels[$cat['category']] ?? ucfirst($cat['category']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label>Stock Status</label>
        <select name="low_stock">
          <option value="">All Items</option>
          <option value="1" <?= $filterLowStock === '1' ? 'selected' : '' ?>>Low Stock Only</option>
        </select>
      </div>

      <div class="form-group" style="justify-content:flex-end;">
        <label>&nbsp;</label>
        <div style="display:flex; gap:.5rem;">
          <button type="submit" class="btn btn-primary">🔍 Filter</button>
          <a href="<?= BASE_URL ?>/inventory/index.php" class="btn btn-outline">Reset</a>
        </div>
      </div>

    </div>
  </form>
</div>

<!-- ── Actions strip ──────────────────────────────────────────-->
<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
  <div style="color:var(--text-muted); font-size:.88rem;">
    Showing <strong><?= count($items) ?></strong> item(s)
    <?php if ($filterLowStock === '1'): ?>
      <span class="badge badge-warning" style="margin-left:.5rem;">Low Stock Filter Active</span>
    <?php endif; ?>
  </div>
  <div style="display:flex; gap:.5rem;">
    <a href="<?= BASE_URL ?>/inventory/add.php" class="btn btn-primary">➕ Add Item</a>
  </div>
</div>

<!-- ── Items table ────────────────────────────────────────────-->
<div class="card">
  <div class="table-wrap">
    <?php if (empty($items)): ?>
      <p style="color:var(--text-muted); padding:.5rem 0;">No items found. <a href="<?= BASE_URL ?>/inventory/add.php">Add your first stock item →</a></p>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>Item Name</th>
            <th>Category</th>
            <th>In Hand</th>
            <th>Unit</th>
            <th>Alert At</th>
            <th>Status</th>
            <th>Last Updated</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($items as $i => $row): ?>
            <?php
              $isLow    = (float)$row['qty_in_hand'] <= (float)$row['low_stock_at'];
              $isEmpty  = (float)$row['qty_in_hand'] == 0;
              $rowStyle = $isEmpty ? 'background:#fff5f5;' : ($isLow ? 'background:#fffbf0;' : '');
            ?>
            <tr style="<?= $rowStyle ?>">
              <td><?= $i + 1 ?></td>
              <td>
                <strong><?= htmlspecialchars($row['name']) ?></strong>
                <?php if ($isEmpty): ?>
                  <span class="badge badge-danger" style="margin-left:.4rem;">OUT</span>
                <?php elseif ($isLow): ?>
                  <span class="badge badge-warning" style="margin-left:.4rem;">LOW</span>
                <?php endif; ?>
              </td>
              <td>
                <span class="badge badge-info">
                  <?= $categoryLabels[$row['category']] ?? ucfirst($row['category']) ?>
                </span>
              </td>
              <td>
                <strong style="color:<?= $isEmpty ? 'var(--error)' : ($isLow ? '#e67e00' : 'var(--success)') ?>; font-size:1.05rem;">
                  <?= number_format((float)$row['qty_in_hand'], 3) ?>
                </strong>
              </td>
              <td style="color:var(--text-muted);"><?= htmlspecialchars($row['unit']) ?></td>
              <td style="color:var(--text-muted);"><?= number_format((float)$row['low_stock_at'], 3) ?></td>
              <td>
                <?php if ($isEmpty): ?>
                  <span class="badge badge-danger">Out of Stock</span>
                <?php elseif ($isLow): ?>
                  <span class="badge badge-warning">Low Stock</span>
                <?php else: ?>
                  <span class="badge badge-success">OK</span>
                <?php endif; ?>
              </td>
              <td style="color:var(--text-muted); font-size:.82rem;">
                <?= date(DATE_FORMAT, strtotime($row['updated_at'])) ?>
              </td>
              <td>
                <div style="display:flex; gap:.35rem;">
                  <a href="<?= BASE_URL ?>/inventory/edit.php?id=<?= $row['id'] ?>"
                     class="btn btn-outline btn-sm" title="Edit / Adjust Stock">✏️</a>
                  <a href="<?= BASE_URL ?>/inventory/delete.php?id=<?= $row['id'] ?>&token=<?= $_SESSION['csrf_token'] ?>"
                     class="btn btn-danger btn-sm"
                     data-confirm="Delete '<?= htmlspecialchars($row['name']) ?>'? This will also remove all stock transactions."
                     title="Delete">🗑</a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>