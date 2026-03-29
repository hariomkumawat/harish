<?php
// ============================================================
//  dues/add.php — Add a new due / EMI
// ============================================================
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';

$pageTitle    = 'Add Due / EMI';
$pageSubtitle = 'Record a new pending payment or loan';

$errors = [];

$old = [
    'due_type'     => 'emi',
    'party_name'   => '',
    'description'  => '',
    'total_amount' => '',
    'amount_paid'  => '0',
    'due_date'     => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request token.';
    } else {

        $old = [
            'due_type'     =>          $_POST['due_type']     ?? 'emi',
            'party_name'   => trim(    $_POST['party_name']   ?? ''),
            'description'  => trim(    $_POST['description']  ?? ''),
            'total_amount' => (float) ($_POST['total_amount'] ?? 0),
            'amount_paid'  => (float) ($_POST['amount_paid']  ?? 0),
            'due_date'     =>          $_POST['due_date']     ?? '',
        ];

        // Validation
        if (empty($old['due_type']))            $errors[] = 'Please select a due type.';
        if (empty($old['party_name']))          $errors[] = 'Party name is required.';
        if ($old['total_amount'] <= 0)          $errors[] = 'Total amount must be greater than 0.';
        if ($old['amount_paid'] < 0)            $errors[] = 'Amount paid cannot be negative.';
        if ($old['amount_paid'] > $old['total_amount'])
                                                $errors[] = 'Amount paid cannot exceed total amount.';

        $validTypes = ['emi', 'vendor_due', 'loan', 'other'];
        if (!in_array($old['due_type'], $validTypes)) $errors[] = 'Invalid due type.';

        if (empty($errors)) {
            $isCleared = ($old['amount_paid'] >= $old['total_amount']) ? 1 : 0;

            db_insert(
                "INSERT INTO dues
                   (due_type, party_name, description, total_amount, amount_paid, due_date, is_cleared)
                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                [
                    $old['due_type'],
                    $old['party_name'],
                    $old['description'] ?: null,
                    $old['total_amount'],
                    $old['amount_paid'],
                    $old['due_date'] ?: null,
                    $isCleared,
                ]
            );

            $_SESSION['flash_success'] = 'Due / EMI record added successfully.';
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

<div class="card">
  <form method="POST" action="">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="form-grid">

      <div class="form-group">
        <label for="due_type">Type <span style="color:red">*</span></label>
        <select id="due_type" name="due_type" required>
          <option value="emi"        <?= $old['due_type'] === 'emi'        ? 'selected' : '' ?>>EMI</option>
          <option value="vendor_due" <?= $old['due_type'] === 'vendor_due' ? 'selected' : '' ?>>Vendor Due</option>
          <option value="loan"       <?= $old['due_type'] === 'loan'       ? 'selected' : '' ?>>Loan</option>
          <option value="other"      <?= $old['due_type'] === 'other'      ? 'selected' : '' ?>>Other</option>
        </select>
      </div>

      <div class="form-group">
        <label for="party_name">Party Name <span style="color:red">*</span></label>
        <input type="text" id="party_name" name="party_name"
               value="<?= htmlspecialchars($old['party_name']) ?>"
               placeholder="Bank name / vendor / person" required>
      </div>

      <div class="form-group">
        <label for="total_amount">Total Amount (<?= CURRENCY_SYMBOL ?>) <span style="color:red">*</span></label>
        <input type="number" id="total_amount" name="total_amount"
               value="<?= htmlspecialchars((string)$old['total_amount']) ?>"
               step="0.01" min="0.01" placeholder="0.00" required>
      </div>

      <div class="form-group">
        <label for="amount_paid">Already Paid (<?= CURRENCY_SYMBOL ?>)</label>
        <input type="number" id="amount_paid" name="amount_paid"
               value="<?= htmlspecialchars((string)$old['amount_paid']) ?>"
               step="0.01" min="0" placeholder="0.00">
        <small style="color:var(--text-muted);">Leave 0 if nothing paid yet</small>
      </div>

      <div class="form-group">
        <label>Remaining</label>
        <input type="text" id="remaining_display" readonly
               placeholder="Auto calculated"
               style="background:#f9f9f9; font-weight:600; color:var(--error);">
      </div>

      <div class="form-group">
        <label for="due_date">Next Due Date</label>
        <input type="date" id="due_date" name="due_date"
               value="<?= htmlspecialchars($old['due_date']) ?>">
        <small style="color:var(--text-muted);">Leave blank if no fixed date</small>
      </div>

      <div class="form-group" style="grid-column:span 2;">
        <label for="description">Description (optional)</label>
        <input type="text" id="description" name="description"
               value="<?= htmlspecialchars($old['description']) ?>"
               placeholder="e.g. Bike EMI — SBI Bank, Mandalor masala pending…">
      </div>

    </div>

    <div class="form-actions">
      <button type="submit" class="btn btn-primary">💾 Save Due / EMI</button>
      <a href="<?= BASE_URL ?>/dues/index.php" class="btn btn-outline">Cancel</a>
    </div>

  </form>
</div>

<script>
function calcRemaining() {
  const total = parseFloat(document.getElementById('total_amount').value) || 0;
  const paid  = parseFloat(document.getElementById('amount_paid').value)  || 0;
  const left  = total - paid;
  document.getElementById('remaining_display').value =
    total > 0 ? '<?= CURRENCY_SYMBOL ?>' + Math.max(0, left).toFixed(2) : '';
}
document.getElementById('total_amount').addEventListener('input', calcRemaining);
document.getElementById('amount_paid').addEventListener('input',  calcRemaining);
calcRemaining();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>