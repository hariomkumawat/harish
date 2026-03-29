<?php
// ============================================================
//  dues/payments.php — Payment history for a due
// ============================================================
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';

$dueId = (int)($_GET['due_id'] ?? 0);
if (!$dueId) {
    header('Location: ' . BASE_URL . '/dues/index.php');
    exit;
}

$due = db_fetch_one("SELECT * FROM dues WHERE id = ?", [$dueId]);
if (!$due) {
    $_SESSION['flash_error'] = 'Due record not found.';
    header('Location: ' . BASE_URL . '/dues/index.php');
    exit;
}

$pageTitle    = 'Payment Log';
$pageSubtitle = $due['party_name'] . ' — ' . ucfirst(str_replace('_', ' ', $due['due_type']));

$payments = db_fetch_all(
    "SELECT * FROM due_payments WHERE due_id = ? ORDER BY payment_date DESC",
    [$dueId]
);

$totalPaid = array_sum(array_column($payments, 'amount'));

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Due summary -->
<div class="card" style="margin-bottom:1.25rem;">
  <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:1rem;">
    <div>
      <div style="font-size:.78rem; color:var(--text-muted); text-transform:uppercase;">Party</div>
      <div style="font-weight:600;"><?= htmlspecialchars($due['party_name']) ?></div>
    </div>
    <div>
      <div style="font-size:.78rem; color:var(--text-muted); text-transform:uppercase;">Total Amount</div>
      <div style="font-weight:600;"><?= CURRENCY_SYMBOL ?><?= number_format($due['total_amount'], 2) ?></div>
    </div>
    <div>
      <div style="font-size:.78rem; color:var(--text-muted); text-transform:uppercase;">Total Paid</div>
      <div style="font-weight:600; color:var(--success);"><?= CURRENCY_SYMBOL ?><?= number_format($due['amount_paid'], 2) ?></div>
    </div>
    <div>
      <div style="font-size:.78rem; color:var(--text-muted); text-transform:uppercase;">Remaining</div>
      <div style="font-weight:600; color:var(--error);"><?= CURRENCY_SYMBOL ?><?= number_format($due['amount_left'], 2) ?></div>
    </div>
  </div>

  <?php $pct = $due['total_amount'] > 0 ? ($due['amount_paid'] / $due['total_amount'] * 100) : 0; ?>
  <div style="margin-top:1rem; background:#eee; border-radius:6px; height:10px;">
    <div style="width:<?= min(100, round($pct)) ?>%;
                background:var(--success); height:10px; border-radius:6px;"></div>
  </div>
  <div style="display:flex; justify-content:space-between; font-size:.78rem;
              color:var(--text-muted); margin-top:.3rem;">
    <span><?= number_format($pct, 1) ?>% paid</span>
    <span>
      <?php if ($due['is_cleared']): ?>
        <span class="badge badge-success">✅ Fully Cleared</span>
      <?php else: ?>
        <span class="badge badge-error">⏳ Pending</span>
        <?php if (!$due['is_cleared']): ?>
          <a href="<?= BASE_URL ?>/dues/pay.php?id=<?= $dueId ?>"
             class="btn btn-success btn-sm" style="margin-left:.5rem;">💳 Pay Now</a>
        <?php endif; ?>
      <?php endif; ?>
    </span>
  </div>
</div>

<!-- Payments table -->
<div class="card">
  <div class="card-title">
    Payment History
    <span style="font-weight:400; font-size:.85rem; color:var(--text-muted);">
      — <?= count($payments) ?> payment(s)
    </span>
  </div>

  <?php if (empty($payments)): ?>
    <p style="color:var(--text-muted);">No payments recorded yet.</p>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>Date</th>
            <th>Amount</th>
            <th>Mode</th>
            <th>Note</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($payments as $i => $p): ?>
            <tr>
              <td><?= $i + 1 ?></td>
              <td><?= date(DATE_FORMAT, strtotime($p['payment_date'])) ?></td>
              <td><strong style="color:var(--success)">
                <?= CURRENCY_SYMBOL ?><?= number_format($p['amount'], 2) ?>
              </strong></td>
              <td>
                <span class="badge badge-info"><?= ucfirst($p['payment_mode']) ?></span>
              </td>
              <td style="color:var(--text-muted); font-size:.82rem;">
                <?= htmlspecialchars($p['note'] ?? '—') ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr style="background:#f9f9f9; font-weight:600;">
            <td colspan="2" style="text-align:right;">Total Paid</td>
            <td style="color:var(--success)"><?= CURRENCY_SYMBOL ?><?= number_format($totalPaid, 2) ?></td>
            <td colspan="2"></td>
          </tr>
        </tfoot>
      </table>
    </div>
  <?php endif; ?>
</div>

<div style="margin-top:1rem;">
  <a href="<?= BASE_URL ?>/dues/index.php" class="btn btn-outline">← Back to Dues</a>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>