<?php
// ============================================================
//  expenses/index.php — Daily Expenses Log
// ============================================================
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';

$pageTitle    = 'Expenses';
$pageSubtitle = 'Daily kharch — all locations';

// ── Filters ───────────────────────────────────────────────────
$filterDate     = $_GET['date']        ?? date('Y-m-d');
$filterLocation = $_GET['location_id'] ?? '';
$filterCategory = $_GET['category_id'] ?? '';

$params = [];
$where  = ['1=1'];

if ($filterDate) {
    $where[]  = 'e.expense_date = ?';
    $params[] = $filterDate;
}
if ($filterLocation) {
    $where[]  = 'e.location_id = ?';
    $params[] = $filterLocation;
}
if ($filterCategory) {
    $where[]  = 'e.category_id = ?';
    $params[] = $filterCategory;
}

$whereSQL = implode(' AND ', $where);

$expenses = db_fetch_all(
    "SELECT e.id, e.expense_date, e.amount, e.description,
            ec.name AS category, l.name AS location
       FROM expenses e
       JOIN expense_categories ec ON ec.id = e.category_id
       JOIN locations           l  ON l.id  = e.location_id
      WHERE {$whereSQL}
      ORDER BY e.id DESC",
    $params
);

$grandTotal = array_sum(array_column($expenses, 'amount'));

// ── Category totals for the filtered result ───────────────────
$categoryTotals = [];
foreach ($expenses as $row) {
    $categoryTotals[$row['category']] = ($categoryTotals[$row['category']] ?? 0) + $row['amount'];
}
arsort($categoryTotals);

$locations  = db_fetch_all("SELECT id, name FROM locations ORDER BY id");
$categories = db_fetch_all("SELECT id, name FROM expense_categories ORDER BY name");

require_once __DIR__ . '/../includes/header.php';
?>

<!-- ── Filter bar ─────────────────────────────────────────────-->
<div class="card">
  <form method="GET" action="">
    <div class="form-grid" style="grid-template-columns:repeat(auto-fill,minmax(180px,1fr));">

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
        <label>Category</label>
        <select name="category_id">
          <option value="">All Categories</option>
          <?php foreach ($categories as $cat): ?>
            <option value="<?= $cat['id'] ?>"
              <?= $filterCategory == $cat['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($cat['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group" style="justify-content:flex-end;">
        <label>&nbsp;</label>
        <div style="display:flex; gap:.5rem;">
          <button type="submit" class="btn btn-primary">🔍 Filter</button>
          <a href="<?= BASE_URL ?>/expenses/index.php" class="btn btn-outline">Reset</a>
        </div>
      </div>

    </div>
  </form>
</div>

<!-- ── Summary strip ──────────────────────────────────────────-->
<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
  <div>
    <strong><?= count($expenses) ?></strong> record(s)
    &nbsp;|&nbsp;
    Total: <strong style="color:var(--error)"><?= CURRENCY_SYMBOL ?><?= number_format($grandTotal, 2) ?></strong>
  </div>
  <a href="<?= BASE_URL ?>/expenses/add.php" class="btn btn-primary">➕ Add Expense</a>
</div>

<!-- ── Two-column layout ──────────────────────────────────────-->
<div style="display:grid; grid-template-columns:1fr 280px; gap:1.25rem; align-items:start;">

  <!-- Main table -->
  <div class="card">
    <div class="table-wrap">
      <?php if (empty($expenses)): ?>
        <p style="color:var(--text-muted); padding:.5rem 0;">No expenses found for selected filters.</p>
      <?php else: ?>
        <table>
          <thead>
            <tr>
              <th>#</th>
              <th>Date</th>
              <th>Category</th>
              <th>Location</th>
              <th>Amount</th>
              <th>Description</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($expenses as $i => $row): ?>
              <tr>
                <td><?= $i + 1 ?></td>
                <td><?= date(DATE_FORMAT, strtotime($row['expense_date'])) ?></td>
                <td>
                  <span class="badge badge-warning">
                    <?= htmlspecialchars($row['category']) ?>
                  </span>
                </td>
                <td><?= htmlspecialchars($row['location']) ?></td>
                <td><strong style="color:var(--error)">
                  <?= CURRENCY_SYMBOL ?><?= number_format($row['amount'], 2) ?>
                </strong></td>
                <td style="color:var(--text-muted); font-size:.82rem;">
                  <?= htmlspecialchars($row['description'] ?? '—') ?>
                </td>
                <td>
                  <a href="<?= BASE_URL ?>/expenses/delete.php?id=<?= $row['id'] ?>&token=<?= $_SESSION['csrf_token'] ?>"
                     class="btn btn-danger btn-sm"
                     data-confirm="Delete this expense entry?">🗑</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr style="background:#f9f9f9; font-weight:600;">
              <td colspan="4" style="text-align:right;">Total</td>
              <td style="color:var(--error)"><?= CURRENCY_SYMBOL ?><?= number_format($grandTotal, 2) ?></td>
              <td colspan="2"></td>
            </tr>
          </tfoot>
        </table>
      <?php endif; ?>
    </div>
  </div>

  <!-- Category breakdown sidebar -->
  <div class="card">
    <div class="card-title">By Category</div>
    <?php if (empty($categoryTotals)): ?>
      <p style="color:var(--text-muted); font-size:.88rem;">No data.</p>
    <?php else: ?>
      <?php $maxVal = max($categoryTotals); ?>
      <?php foreach ($categoryTotals as $cat => $amt): ?>
        <?php $pct = $maxVal > 0 ? ($amt / $maxVal * 100) : 0; ?>
        <div style="margin-bottom:.85rem;">
          <div style="display:flex; justify-content:space-between; font-size:.83rem; margin-bottom:.25rem;">
            <span><?= htmlspecialchars($cat) ?></span>
            <strong><?= CURRENCY_SYMBOL ?><?= number_format($amt, 2) ?></strong>
          </div>
          <div style="background:#eee; border-radius:4px; height:7px;">
            <div style="width:<?= round($pct) ?>%; background:var(--error); height:7px; border-radius:4px;"></div>
          </div>
        </div>
      <?php endforeach; ?>
      <div style="border-top:1px solid var(--border); padding-top:.75rem; margin-top:.25rem;
                  display:flex; justify-content:space-between; font-weight:600; font-size:.9rem;">
        <span>Total</span>
        <span style="color:var(--error)"><?= CURRENCY_SYMBOL ?><?= number_format($grandTotal, 2) ?></span>
      </div>
    <?php endif; ?>
  </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>