<?php
// ============================================================
//  employees/index.php — Employee List
// ============================================================
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';

$pageTitle    = 'Employees';
$pageSubtitle = 'Staff management & salary overview';

$thisMonth = date('Y-m');
$filterStatus = $_GET['status'] ?? '';

// ── All employees with this month's paid amount ───────────────
$params = [];
$where  = ['1=1'];
if ($filterStatus === 'active')   { $where[] = 'e.is_active = 1'; }
if ($filterStatus === 'inactive') { $where[] = 'e.is_active = 0'; }
$whereSQL = implode(' AND ', $where);

$employees = db_fetch_all(
    "SELECT e.*,
            COALESCE(SUM(sp.amount_paid), 0)                        AS paid_this_month,
            COALESCE(SUM(sp.is_advance), 0)                          AS has_advance,
            e.monthly_salary - COALESCE(SUM(sp.amount_paid), 0)     AS balance_due
       FROM employees e
       LEFT JOIN salary_payments sp
              ON sp.employee_id = e.id AND sp.pay_month = ?
      WHERE {$whereSQL}
      GROUP BY e.id
      ORDER BY e.is_active DESC, e.name",
    array_merge([$thisMonth], $params)
);

// ── Summary stats ─────────────────────────────────────────────
$totalActive      = (int) db_value("SELECT COUNT(*) FROM employees WHERE is_active = 1");
$totalSalaryBill  = (float) db_value("SELECT COALESCE(SUM(monthly_salary),0) FROM employees WHERE is_active = 1");
$paidThisMonth    = (float) db_value(
    "SELECT COALESCE(SUM(amount_paid),0) FROM salary_payments WHERE pay_month = ?", [$thisMonth]
);
$pendingThisMonth = max(0, $totalSalaryBill - $paidThisMonth);

require_once __DIR__ . '/../includes/header.php';
?>

<!-- ── Stat cards ─────────────────────────────────────────────-->
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr); margin-bottom:1.25rem;">
  <div class="stat-card green">
    <span class="stat-icon">👥</span>
    <span class="stat-label">Active Staff</span>
    <span class="stat-value"><?= $totalActive ?></span>
    <span class="stat-sub">Employees</span>
  </div>
  <div class="stat-card orange">
    <span class="stat-icon">💼</span>
    <span class="stat-label">Monthly Bill</span>
    <span class="stat-value"><?= CURRENCY_SYMBOL ?><?= number_format($totalSalaryBill, 0) ?></span>
    <span class="stat-sub">Total payroll</span>
  </div>
  <div class="stat-card green">
    <span class="stat-icon">✅</span>
    <span class="stat-label">Paid — <?= date('M Y') ?></span>
    <span class="stat-value"><?= CURRENCY_SYMBOL ?><?= number_format($paidThisMonth, 0) ?></span>
    <span class="stat-sub">This month</span>
  </div>
  <div class="stat-card <?= $pendingThisMonth > 0 ? 'red' : 'green' ?>">
    <span class="stat-icon">🔔</span>
    <span class="stat-label">Pending — <?= date('M Y') ?></span>
    <span class="stat-value"><?= CURRENCY_SYMBOL ?><?= number_format($pendingThisMonth, 0) ?></span>
    <span class="stat-sub">Still to pay</span>
  </div>
</div>

<!-- ── Filter + Actions ───────────────────────────────────────-->
<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; flex-wrap:wrap; gap:.75rem;">
  <div style="display:flex; gap:.5rem;">
    <a href="?" class="btn <?= $filterStatus === '' ? 'btn-primary' : 'btn-outline' ?> btn-sm">All</a>
    <a href="?status=active"   class="btn <?= $filterStatus === 'active'   ? 'btn-primary' : 'btn-outline' ?> btn-sm">Active</a>
    <a href="?status=inactive" class="btn <?= $filterStatus === 'inactive' ? 'btn-primary' : 'btn-outline' ?> btn-sm">Inactive</a>
  </div>
  <a href="<?= BASE_URL ?>/employees/add.php" class="btn btn-primary">➕ Add Employee</a>
</div>

<!-- ── Employee table ─────────────────────────────────────────-->
<div class="card">
  <div class="table-wrap">
    <?php if (empty($employees)): ?>
      <p style="color:var(--text-muted); padding:.5rem 0;">No employees found. <a href="<?= BASE_URL ?>/employees/add.php">Add first employee →</a></p>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>Name</th>
            <th>Role</th>
            <th>Phone</th>
            <th>Monthly Salary</th>
            <th>Paid (<?= date('M Y') ?>)</th>
            <th>Balance Due</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($employees as $i => $emp): ?>
            <?php
              $balanceDue  = (float)$emp['balance_due'];
              $paidAmt     = (float)$emp['paid_this_month'];
              $monthlySal  = (float)$emp['monthly_salary'];
              $fullyPaid   = $emp['is_active'] && $paidAmt >= $monthlySal;
              $rowStyle    = !$emp['is_active'] ? 'opacity:.6;' : '';
            ?>
            <tr style="<?= $rowStyle ?>">
              <td><?= $i + 1 ?></td>
              <td>
                <strong><?= htmlspecialchars($emp['name']) ?></strong>
                <?php if ($emp['has_advance'] > 0): ?>
                  <span class="badge badge-warning" style="margin-left:.4rem; font-size:.72rem;">Advance</span>
                <?php endif; ?>
              </td>
              <td style="color:var(--text-muted);"><?= htmlspecialchars($emp['role'] ?? '—') ?></td>
              <td style="color:var(--text-muted);"><?= htmlspecialchars($emp['phone'] ?? '—') ?></td>
              <td><strong><?= CURRENCY_SYMBOL ?><?= number_format($monthlySal, 2) ?></strong></td>
              <td>
                <?php if ($paidAmt > 0): ?>
                  <span style="color:var(--success); font-weight:600;">
                    <?= CURRENCY_SYMBOL ?><?= number_format($paidAmt, 2) ?>
                  </span>
                <?php else: ?>
                  <span style="color:var(--text-muted);">—</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if (!$emp['is_active']): ?>
                  <span style="color:var(--text-muted);">—</span>
                <?php elseif ($fullyPaid): ?>
                  <span class="badge badge-success">✅ Paid</span>
                <?php else: ?>
                  <span style="color:var(--error); font-weight:600;">
                    <?= CURRENCY_SYMBOL ?><?= number_format($balanceDue, 2) ?>
                  </span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($emp['is_active']): ?>
                  <span class="badge badge-success">Active</span>
                <?php else: ?>
                  <span class="badge" style="background:#eee; color:#888;">Inactive</span>
                <?php endif; ?>
              </td>
              <td>
                <div style="display:flex; gap:.35rem; flex-wrap:wrap;">
                  <a href="<?= BASE_URL ?>/employees/salary.php?id=<?= $emp['id'] ?>"
                     class="btn btn-primary btn-sm" title="Pay Salary / History">💰 Salary</a>
                  <a href="<?= BASE_URL ?>/employees/add.php?edit=<?= $emp['id'] ?>"
                     class="btn btn-outline btn-sm" title="Edit Employee">✏️</a>
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