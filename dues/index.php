<?php
// ============================================================
//  dues/index.php — Pending EMI & Dues
// ============================================================
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';

$pageTitle    = 'Dues & EMI';
$pageSubtitle = 'Pending payments, loans and vendor dues';

// ── Filters ───────────────────────────────────────────────────
$filterType    = $_GET['due_type']   ?? '';
$filterCleared = $_GET['is_cleared'] ?? '0'; // default: show pending only

$params = [];
$where  = ['1=1'];

if ($filterType) {
    $where[]  = 'd.due_type = ?';
    $params[] = $filterType;
}
if ($filterCleared !== '') {
    $where[]  = 'd.is_cleared = ?';
    $params[] = (int)$filterCleared;
}

$whereSQL = implode(' AND ', $where);

$dues = db_fetch_all(
    "SELECT d.id, d.due_type, d.party_name, d.description,
            d.total_amount, d.amount_paid, d.amount_left,
            d.due_date, d.is_cleared, d.created_at
       FROM dues d
      WHERE {$whereSQL}
      ORDER BY d.is_cleared ASC, d.due_date ASC",
    $params
);

// ── Summary totals ────────────────────────────────────────────
$totalLeft  = array_sum(array_column($dues, 'amount_left'));
$totalPaid  = array_sum(array_column($dues, 'amount_paid'));
$totalDue   = array_sum(array_column($dues, 'total_amount'));

// ── Overdue count (due_date < today and not cleared) ──────────
$overdueCount = db_value(
    "SELECT COUNT(*) FROM dues
      WHERE is_cleared = 0
        AND due_date < ?
        AND due_date IS NOT NULL",
    [date('Y-m-d')]
);

require_once __DIR__ . '/../includes/header.php';
?>

<!-- ── Stat strip ─────────────────────────────────────────────-->
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr); margin-bottom:1.25rem;">

  <div class="stat-card red">
    <span class="stat-icon">🔔</span>
    <span class="stat-label">Total Pending</span>
    <span class="stat-value"><?= CURRENCY_SYMBOL ?><?= number_format($totalLeft, 2) ?></span>
    <span class="stat-sub">Amount still owed</span>
  </div>

  <div class="stat-card orange">
    <span class="stat-icon">💸</span>
    <span class="stat-label">Total Paid So Far</span>
    <span class="stat-value"><?= CURRENCY_SYMBOL ?><?= number_format($totalPaid, 2) ?></span>
    <span class="stat-sub">Across all dues</span>
  </div>

  <div class="stat-card">
    <span class="stat-icon">📋</span>
    <span class="stat-label">Total Borrowed</span>
    <span class="stat-value"><?= CURRENCY_SYMBOL ?><?= number_format($totalDue, 2) ?></span>
    <span class="stat-sub">Original amounts</span>
  </div>

  <div class="stat-card <?= $overdueCount > 0 ? 'red' : 'green' ?>">
    <span class="stat-icon">⚠️</span>
    <span class="stat-label">Overdue</span>
    <span class="stat-value"><?= $overdueCount ?></span>
    <span class="stat-sub">Past due date</span>
  </div>

</div>

<?php if ($overdueCount > 0): ?>
  <div class="alert alert-error">
    ⚠️ <strong><?= $overdueCount ?> due(s)</strong> have passed their due date.
    <a href="?due_type=&is_cleared=0">View pending dues</a>
  </div>
<?php endif; ?>

<!-- ── Filter bar ─────────────────────────────────────────────-->
<div class="card">
  <form method="GET" action="">
    <div class="form-grid" style="grid-template-columns:repeat(auto-fill,minmax(180px,1fr));">

      <div class="form-group">
        <label>Type</label>
        <select name="due_type">
          <option value="">All Types</option>
          <option value="emi"        <?= $filterType === 'emi'        ? 'selected' : '' ?>>EMI</option>
          <option value="vendor_due" <?= $filterType === 'vendor_due' ? 'selected' : '' ?>>Vendor Due</option>
          <option value="loan"       <?= $filterType === 'loan'       ? 'selected' : '' ?>>Loan</option>
          <option value="other"      <?= $filterType === 'other'      ? 'selected' : '' ?>>Other</option>
        </select>
      </div>

      <div class="form-group">
        <label>Status</label>
        <select name="is_cleared">
          <option value="0" <?= $filterCleared === '0' ? 'selected' : '' ?>>Pending</option>
          <option value="1" <?= $filterCleared === '1' ? 'selected' : '' ?>>Cleared</option>
          <option value=""  <?= $filterCleared === ''  ? 'selected' : '' ?>>All</option>
        </select>
      </div>

      <div class="form-group" style="justify-content:flex-end;">
        <label>&nbsp;</label>
        <div style="display:flex; gap:.5rem;">
          <button type="submit" class="btn btn-primary">🔍 Filter</button>
          <a href="<?= BASE_URL ?>/dues/index.php" class="btn btn-outline">Reset</a>
        </div>
      </div>

    </div>
  </form>
</div>

<!-- ── Action bar ─────────────────────────────────────────────-->
<div style="display:flex; justify-content:flex-end; margin-bottom:1rem;">
  <a href="<?= BASE_URL ?>/dues/add.php" class="btn btn-primary">➕ Add Due / EMI</a>
</div>

<!-- ── Dues table ─────────────────────────────────────────────-->
<div class="card">
  <div class="table-wrap">
    <?php if (empty($dues)): ?>
      <p style="color:var(--text-muted); padding:.5rem 0;">No records found.</p>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>Type</th>
            <th>Party</th>
            <th>Description</th>
            <th>Total</th>
            <th>Paid</th>
            <th>Remaining</th>
            <th>Due Date</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($dues as $i => $row): ?>
            <?php
              $isOverdue = !$row['is_cleared']
                           && $row['due_date']
                           && $row['due_date'] < date('Y-m-d');
              $progress  = $row['total_amount'] > 0
                           ? ($row['amount_paid'] / $row['total_amount'] * 100)
                           : 0;
            ?>
            <tr style="<?= $isOverdue ? 'background:#fff5f5;' : '' ?>">
              <td><?= $i + 1 ?></td>
              <td>
                <?php
                  $typeColors = [
                    'emi'        => 'badge-info',
                    'vendor_due' => 'badge-warning',
                    'loan'       => 'badge-error',
                    'other'      => 'badge-success',
                  ];
                ?>
                <span class="badge <?= $typeColors[$row['due_type']] ?? 'badge-info' ?>">
                  <?= ucfirst(str_replace('_', ' ', $row['due_type'])) ?>
                </span>
              </td>
              <td><strong><?= htmlspecialchars($row['party_name']) ?></strong></td>
              <td style="color:var(--text-muted); font-size:.82rem;">
                <?= htmlspecialchars($row['description'] ?? '—') ?>
              </td>
              <td><?= CURRENCY_SYMBOL ?><?= number_format($row['total_amount'], 2) ?></td>
              <td style="color:var(--success)">
                <?= CURRENCY_SYMBOL ?><?= number_format($row['amount_paid'], 2) ?>
              </td>
              <td>
                <strong style="color:<?= $row['amount_left'] > 0 ? 'var(--error)' : 'var(--success)' ?>">
                  <?= CURRENCY_SYMBOL ?><?= number_format($row['amount_left'], 2) ?>
                </strong>
                <!-- Progress bar -->
                <div style="background:#eee; border-radius:4px; height:5px; margin-top:4px;">
                  <div style="width:<?= min(100, round($progress)) ?>%;
                              background:var(--success); height:5px; border-radius:4px;"></div>
                </div>
              </td>
              <td>
                <?php if ($row['due_date']): ?>
                  <span style="color:<?= $isOverdue ? 'var(--error)' : 'var(--text)' ?>; font-weight:<?= $isOverdue ? '600' : '400' ?>">
                    <?= date(DATE_FORMAT, strtotime($row['due_date'])) ?>
                    <?= $isOverdue ? '⚠️' : '' ?>
                  </span>
                <?php else: ?>
                  <span style="color:var(--text-muted)">—</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($row['is_cleared']): ?>
                  <span class="badge badge-success">✅ Cleared</span>
                <?php else: ?>
                  <span class="badge badge-error">⏳ Pending</span>
                <?php endif; ?>
              </td>
              <td>
                <div style="display:flex; gap:.4rem; flex-wrap:wrap;">
                  <!-- Record payment -->
                  <?php if (!$row['is_cleared']): ?>
                    <a href="<?= BASE_URL ?>/dues/pay.php?id=<?= $row['id'] ?>"
                       class="btn btn-success btn-sm">💳 Pay</a>
                  <?php endif; ?>
                  <!-- View payments -->
                  <a href="<?= BASE_URL ?>/dues/payments.php?due_id=<?= $row['id'] ?>"
                     class="btn btn-outline btn-sm">📜 Log</a>
                  <!-- Delete -->
                  <a href="<?= BASE_URL ?>/dues/delete.php?id=<?= $row['id'] ?>&token=<?= $_SESSION['csrf_token'] ?>"
                     class="btn btn-danger btn-sm"
                     data-confirm="Delete this due record and all its payments?">🗑</a>
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