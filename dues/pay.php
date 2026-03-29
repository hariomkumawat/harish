<?php
// ============================================================
//  dues/pay.php — Record a payment against a due/EMI
// ============================================================
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';

$dueId = (int)($_GET['id'] ?? 0);
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

$pageTitle    = 'Record Payment';
$pageSubtitle = 'Against: ' . $due['party_name'];

$errors = [];

$old = [
    'amount'       => '',
    'payment_date' => date('Y-m-d'),
    'payment_mode' => 'cash',
    'note'         => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request token.';
    } else {

        $old = [
            'amount'       => (float) ($_POST['amount']       ?? 0),
            'payment_date' =>          $_POST['payment_date'] ?? date('Y-m-d'),
            'payment_mode' =>          $_POST['payment_mode'] ?? 'cash',
            'note'         => trim(    $_POST['note']         ?? ''),
        ];

        if ($old['amount'] <= 0)
            $errors[] = 'Payment amount must be greater than 0.';
        if ($old['amount'] > $due['amount_left'])
            $errors[] = 'Payment cannot exceed remaining amount ('
                        . CURRENCY_SYMBOL . number_format($due['amount_left'], 2) . ').';

        if (empty($errors)) {
            // Insert payment log
            db_insert(
                "INSERT INTO due_payments (due_id, amount, payment_date, payment_mode, note)
                 VALUES (?, ?, ?, ?, ?)",
                [
                    $dueId,
                    $old['amount'],
                    $old['payment_date'],
                    $old['payment_mode'],
                    $old['note'] ?: null,
                ]
            );

            // Update due: add to amount_paid, mark cleared if fully paid
            $newPaid    = $due['amount_paid'] + $old['amount'];
            $isCleared  = ($newPaid >= $due['total_amount']) ? 1 : 0;

            db_run(
                "UPDATE dues SET amount_paid = ?, is_cleared = ? WHERE id = ?",
                [$newPaid, $isCleared, $dueId]
            );

            $msg = $isCleared
                   ? '✅ Payment recorded. Due is now fully cleared!'
                   : '✅ Payment recorded successfully.';

            $_SESSION['flash_success'] = $msg;
            header('Location: ' . BASE_URL . '/dues/index.php');
            exit;
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<?php if (!empty($errors)): ?>
  <div class="alert alert-error">
    <?php foreach ($errors as $e): ?>
      <div>❌ <?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<!-- Due summary card -->
<div class="card" style="margin-bottom:1.25rem;">
  <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:1rem;">
    <div>
      <div style="font-size:.78rem; color:var(--text-muted); text-transform:uppercase;">Party</div>
      <div style="font-weight:600;"><?= htmlspecialchars($due['party_name']) ?></div>
    </div>
    <div>
      <div style="font-size:.78rem; color:var(--text-muted); text-transform:uppercase;">Total</div>
      <div style="font-weight:600;"><?= CURRENCY_SYMBOL ?><?= number_format($due['total_amount'], 2) ?></div>
    </div>
    <div>
      <div style="font-size:.78rem; color:var(--text-muted); text-transform:uppercase;">Paid So Far</div>
      <div style="font-weight:600; color:var(--success);"><?= CURRENCY_SYMBOL ?><?= number_format($due['amount_paid'], 2) ?></div>
    </div>
    <div>
      <div style="font-size:.78rem; color:var(--text-muted); text-transform:uppercase;">Remaining</div>
      <div style="font-weight:600; color:var(--error);"><?= CURRENCY_SYMBOL ?><?= number_format($due['amount_left'], 2) ?></div>
    </div>
  </div>
  <!-- Progress bar -->
  <?php $pct = $due['total_amount'] > 0 ? ($due['amount_paid'] / $due['total_amount'] * 100) : 0; ?>
  <div style="margin-top:1rem; background:#eee; border-radius:6px; height:10px;">
    <div style="width:<?= min(100, round($pct)) ?>%; background:var(--success);
                height:10px; border-radius:6px; transition:width .3s;"></div>
  </div>
  <div style="text-align:right; font-size:.78rem; color:var(--text-muted); margin-top:.25rem;">
    <?= number_format($pct, 1) ?>% paid
  </div>
</div>

<!-- Payment form -->
<div class="card">
  <div class="card-title">Record New Payment</div>
  <form method="POST" action="">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="form-grid">

      <div class="form-group">
        <label for="amount">Payment Amount (<?= CURRENCY_SYMBOL ?>) <span style="color:red">*</span></label>
        <input type="number" id="amount" name="amount"
               value="<?= htmlspecialchars((string)$old['amount']) ?>"
               step="0.01" min="0.01"
               max="<?= $due['amount_left'] ?>"
               placeholder="0.00" required>
        <small style="color:var(--text-muted);">
          Max payable: <?= CURRENCY_SYMBOL ?><?= number_format($due['amount_left'], 2) ?>
          &nbsp;
          <a href="#" onclick="document.getElementById('amount').value='<?= $due['amount_left'] ?>'; return false;">
            Pay full
          </a>
        </small>
      </div>

      <div class="form-group">
        <label for="payment_date">Payment Date <span style="color:red">*</span></label>
        <input type="date" id="payment_date" name="payment_date"
               value="<?= htmlspecialchars($old['payment_date']) ?>" required>
      </div>

      <div class="form-group">
        <label for="payment_mode">Payment Mode</label>
        <select id="payment_mode" name="payment_mode">
          <option value="cash"   <?= $old['payment_mode'] === 'cash'   ? 'selected' : '' ?>>Cash</option>
          <option value="upi"    <?= $old['payment_mode'] === 'upi'    ? 'selected' : '' ?>>UPI</option>
          <option value="bank"   <?= $old['payment_mode'] === 'bank'   ? 'selected' : '' ?>>Bank Transfer</option>
          <option value="cheque" <?= $old['payment_mode'] === 'cheque' ? 'selected' : '' ?>>Cheque</option>
        </select>
      </div>

      <div class="form-group">
        <label for="note">Note (optional)</label>
        <input type="text" id="note" name="note"
               value="<?= htmlspecialchars($old['note']) ?>"
               placeholder="Reference number, remark…">
      </div>

    </div>

    <div class="form-actions">
      <button type="submit" class="btn btn-success">💳 Record Payment</button>
      <a href="<?= BASE_URL ?>/dues/index.php" class="btn btn-outline">Cancel</a>
    </div>

  </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>