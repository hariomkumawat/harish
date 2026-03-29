<?php
// ============================================================
//  reports/index.php — Report selector
// ============================================================
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';

$pageTitle    = 'Reports';
$pageSubtitle = 'Monthly summaries and business insights';

// Available months that have sales data
$availableMonths = db_fetch_all(
    "SELECT DISTINCT DATE_FORMAT(sale_date, '%Y-%m') AS month,
            DATE_FORMAT(sale_date, '%M %Y')          AS label
       FROM sales
      ORDER BY month DESC
      LIMIT 24"
);

require_once __DIR__ . '/../includes/header.php';
?>

<!-- ── Quick links ────────────────────────────────────────────-->
<div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(220px,1fr)); gap:1.25rem; margin-bottom:1.75rem;">

  <a href="<?= BASE_URL ?>/reports/monthly.php?month=<?= date('Y-m') ?>"
     class="card" style="text-decoration:none; cursor:pointer; transition:box-shadow .2s;"
     onmouseover="this.style.boxShadow='0 4px 16px rgba(0,0,0,.12)'"
     onmouseout="this.style.boxShadow=''">
    <div style="font-size:2rem; margin-bottom:.5rem;">📊</div>
    <div style="font-weight:700; font-size:1rem; color:var(--text);">Monthly Report</div>
    <div style="color:var(--text-muted); font-size:.85rem; margin-top:.25rem;">
      Income vs expense, profit summary, product breakdown
    </div>
    <div style="margin-top:.75rem; color:var(--brand); font-size:.85rem; font-weight:600;">
      View <?= date('F Y') ?> →
    </div>
  </a>

  <a href="<?= BASE_URL ?>/reports/export.php?month=<?= date('Y-m') ?>"
     class="card" style="text-decoration:none; cursor:pointer; transition:box-shadow .2s;"
     onmouseover="this.style.boxShadow='0 4px 16px rgba(0,0,0,.12)'"
     onmouseout="this.style.boxShadow=''">
    <div style="font-size:2rem; margin-bottom:.5rem;">🖨️</div>
    <div style="font-weight:700; font-size:1rem; color:var(--text);">Print / Export</div>
    <div style="color:var(--text-muted); font-size:.85rem; margin-top:.25rem;">
      Print-ready report for any month
    </div>
    <div style="margin-top:.75rem; color:var(--brand); font-size:.85rem; font-weight:600;">
      Export <?= date('F Y') ?> →
    </div>
  </a>

</div>

<!-- ── Month picker ───────────────────────────────────────────-->
<div class="card">
  <div class="card-title">Select a Month</div>

  <form method="GET" action="<?= BASE_URL ?>/reports/monthly.php"
        style="display:flex; gap:1rem; align-items:flex-end; flex-wrap:wrap;">
    <div class="form-group">
      <label>Month</label>
      <input type="month" name="month" value="<?= date('Y-m') ?>">
    </div>
    <div class="form-group">
      <label>Location</label>
      <select name="location_id">
        <option value="">All Locations</option>
        <?php
          $locs = db_fetch_all("SELECT id, name FROM locations ORDER BY id");
          foreach ($locs as $loc):
        ?>
          <option value="<?= $loc['id'] ?>"><?= htmlspecialchars($loc['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button type="submit" class="btn btn-primary" style="margin-bottom:0;">
      📊 View Report
    </button>
  </form>
</div>

<!-- ── Past months with data ──────────────────────────────────-->
<?php if (!empty($availableMonths)): ?>
<div class="card">
  <div class="card-title">Months With Data</div>
  <div style="display:flex; flex-wrap:wrap; gap:.6rem;">
    <?php foreach ($availableMonths as $m): ?>
      <a href="<?= BASE_URL ?>/reports/monthly.php?month=<?= $m['month'] ?>"
         class="btn btn-outline btn-sm">
        📅 <?= htmlspecialchars($m['label']) ?>
      </a>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>