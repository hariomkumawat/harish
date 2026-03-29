<?php
// ============================================================
//  employees/salary.php — Salary payment + full history
// ============================================================
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    $_SESSION['flash_error'] = 'Invalid employee.';
    header('Location: ' . BASE_URL . '/employees/index.php');
    exit;
}

$employee = db_fetch_one("SELECT * FROM employees WHERE id = ?", [$id]);
if (!$employee) {
    $_SESSION['flash_error'] = 'Employee not found.';
    header('Location: ' . BASE_URL . '/employees/index.php');
    exit;
}

$pageTitle    = 'Salary — ' . $employee['name'];
$pageSubtitle = 'Pay salary, record advance, view payment history';

$errors     = [];
$activeTab  = $_GET['tab'] ?? 'pay';
$thisMonth  = date('Y-m');

// ── Handle salary payment POST ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request token.';
    } else {
        $pay_month    = trim($_POST['pay_month']    ?? $thisMonth);
        $amount_paid  = $_POST['amount_paid']       ?? '';
        $payment_date = trim($_POST['payment_date'] ?? date('Y-m-d'));
        $payment_mode = trim($_POST['payment_mode'] ?? 'cash');
        $is_advance   = isset($_POST['is_advance']) ? 1 : 0;
        $note         = trim($_POST['note']         ?? '');

        // Validation
        if (empty($pay_month) || !preg_match('/^\d{4}-\d{2}$/', $pay_month))
                                                    $errors[] = 'Invalid pay month.';
        if (!is_numeric($amount_paid) || (float)$amount_paid <= 0)
                                                    $errors[] = 'Amount must be greater than 0.';
        if (!in_array($payment_mode, ['cash','upi','bank']))
                                                    $errors[] = 'Invalid payment mode.';

        // Overpayment check (only for non-advance, non-partial)
        if (empty($errors) && !$is_advance) {
            $alreadyPaid = (float) db_value(
                "SELECT COALESCE(SUM(amount_paid),0) FROM salary_payments
                  WHERE employee_id = ? AND pay_month = ? AND is_advance = 0",
                [$id, $pay_month]
            );
            $remaining = (float)$employee['monthly_salary'] - $alreadyPaid;
            if ($remaining <= 0) {
                $errors[] = "Salary for {$pay_month} is already fully paid.";
            } elseif ((float)$amount_paid > $remaining + 0.01) {
                $errors[] = "Cannot pay more than remaining balance (" . CURRENCY_SYMBOL . number_format($remaining, 2) . "). Use advance for extra payments.";
            }
        }

        if (empty($errors)) {
            db_insert(
                "INSERT INTO salary_payments
                   (employee_id, pay_month, amount_paid, payment_date, payment_mode, is_advance, note)
                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                [
                    $id,
                    $pay_month,
                    (float)$amount_paid,
                    $payment_date,
                    $payment_mode,
                    $is_advance,
                    $note ?: null,
                ]
            );

            $label = $is_advance ? 'Advance payment' : 'Salary payment';
            $_SESSION['flash_success'] = "{$label} of " . CURRENCY_SYMBOL . number_format((float)$amount_paid, 2) . " recorded for {$pay_month}.";
            header('Location: ' . BASE_URL . '/employees/salary.php?id=' . $id . '&tab=history');
            exit;
        }
    }

    $activeTab = 'pay';
}

// ── Current month summary ─────────────────────────────────────
$thisMonthPaid = (float) db_value(
    "SELECT COALESCE(SUM(amount_paid),0) FROM salary_payments
      WHERE employee_id = ? AND pay_month = ?",
    [$id, $thisMonth]
);
$thisMonthBalance = max(0, (float)$employee['monthly_salary'] - $thisMonthPaid);

// ── Delete payment ────────────────────────────────────────────
if (isset($_GET['del_payment']) && isset($_GET['token'])) {
    $delId = (int)$_GET['del_payment'];
    if (hash_equals($_SESSION['csrf_token'], $_GET['token'])) {
        db_run("DELETE FROM salary_payments WHERE id = ? AND employee_id = ?", [$delId, $id]);
        $_SESSION['flash_success'] = 'Payment entry deleted.';
    }
    header('Location: ' . BASE_URL . '/employees/salary.php?id=' . $id . '&tab=history');
    exit;
}

// ── Payment history ───────────────────────────────────────────
$filterHistMonth = $_GET['hist_month'] ?? '';
$histParams = [$id];
$histWhere  = 'sp.employee_id = ?';
if ($filterHistMonth) {
    $histWhere   .= ' AND sp.pay_month = ?';
    $histParams[] = $filterHistMonth;
}

$payments = db_fetch_all(
    "SELECT sp.*
       FROM salary_payments sp
      WHERE {$histWhere}
      ORDER BY sp.payment_date DESC, sp.id DESC",
    $histParams
);

// ── Monthly summary rollup ────────────────────────────────────
$monthlySummary = db_fetch_all(
    "SELECT pay_month,
            SUM(amount_paid)              AS total_paid,
            SUM(CASE WHEN is_advance=0 THEN amount_paid ELSE 0 END) AS salary_paid,
            SUM(CASE WHEN is_advance=1 THEN amount_paid ELSE 0 END) AS advance_paid,
            COUNT(*)                       AS num_entries
       FROM salary_payments
      WHERE employee_id = ?
      GROUP BY pay_month
      ORDER BY pay_month DESC
      LIMIT 12",
    [$id]
);

$totalEverPaid = (float) db_value(
    "SELECT COALESCE(SUM(amount_paid),0) FROM salary_payments WHERE employee_id = ?", [$id]
);

require_once __DIR__ . '/../includes/header.php';
?>

<!-- ── Employee info strip ───────────────────────────────────-->
<div style="background:#f8f9fb; border:1px solid var(--border); border-radius:10px;
            padding:1rem 1.25rem; margin-bottom:1.25rem;
            display:flex; gap:2rem; align-items:center; flex-wrap:wrap;">
  <div>
    <div style="font-size:.75rem; color:var(--text-muted); text-transform:uppercase; letter-spacing:.05em;">Employee</div>
    <div style="font-size:1.15rem; font-weight:700;"><?= htmlspecialchars($employee['name']) ?></div>
    <?php if ($employee['role']): ?>
      <div style="font-size:.82rem; color:var(--text-muted);"><?= htmlspecialchars($employee['role']) ?></div>
    <?php endif; ?>
  </div>
  <div>
    <div style="font-size:.75rem; color:var(--text-muted); text-transform:uppercase; letter-spacing:.05em;">Monthly Salary</div>
    <div style="font-size:1.1rem; font-weight:700; color:var(--brand);">
      <?= CURRENCY_SYMBOL ?><?= number_format((float)$employee['monthly_salary'], 2) ?>
    </div>
  </div>
  <div>
    <div style="font-size:.75rem; color:var(--text-muted); text-transform:uppercase; letter-spacing:.05em;">Paid — <?= date('M Y') ?></div>
    <div style="font-size:1.1rem; font-weight:700; color:var(--success);">
      <?= CURRENCY_SYMBOL ?><?= number_format($thisMonthPaid, 2) ?>
    </div>
  </div>
  <div>
    <div style="font-size:.75rem; color:var(--text-muted); text-transform:uppercase; letter-spacing:.05em;">Balance — <?= date('M Y') ?></div>
    <div style="font-size:1.1rem; font-weight:700; color:<?= $thisMonthBalance > 0 ? 'var(--error)' : 'var(--success)' ?>;">
      <?= $thisMonthBalance > 0
          ? CURRENCY_SYMBOL . number_format($thisMonthBalance, 2) . ' pending'
          : '✅ Fully Paid' ?>
    </div>
  </div>
  <?php if ($employee['phone']): ?>
  <div>
    <div style="font-size:.75rem; color:var(--text-muted); text-transform:uppercase; letter-spacing:.05em;">Phone</div>
    <div style="font-size:.92rem;"><?= htmlspecialchars($employee['phone']) ?></div>
  </div>
  <?php endif; ?>
  <div style="margin-left:auto;">
    <a href="<?= BASE_URL ?>/employees/add.php?edit=<?= $id ?>" class="btn btn-outline btn-sm">✏️ Edit Profile</a>
  </div>
</div>

<!-- ── Tab switcher ───────────────────────────────────────────-->
<div style="display:flex; gap:0; margin-bottom:1.25rem; border-bottom:2px solid var(--border);">
  <?php foreach (['pay' => '💰 Pay Salary', 'history' => '📜 Payment History', 'summary' => '📊 Monthly Summary'] as $tab => $label): ?>
    <a href="?id=<?= $id ?>&tab=<?= $tab ?>"
       style="padding:.6rem 1.25rem; text-decoration:none; font-weight:600; font-size:.9rem;
              border-bottom:3px solid <?= $activeTab === $tab ? 'var(--brand)' : 'transparent' ?>;
              color:<?= $activeTab === $tab ? 'var(--brand)' : 'var(--text-muted)' ?>;
              margin-bottom:-2px;">
      <?= $label ?>
    </a>
  <?php endforeach; ?>
</div>


<!-- ════════════════════════════════════════════════════════════
     TAB: PAY SALARY
════════════════════════════════════════════════════════════════-->
<?php if ($activeTab === 'pay'): ?>

<?php if (!empty($errors)): ?>
  <div class="alert alert-error">
    <?php foreach ($errors as $e): ?>
      <div>❌ <?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<div style="display:grid; grid-template-columns:1fr 1fr; gap:1.25rem; align-items:start;">

  <!-- Payment form -->
  <div class="card">
    <div class="card-title">Record Payment</div>
    <form method="POST" action="" id="salaryForm">
      <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

      <div class="form-group">
        <label for="pay_month">Pay Month <span style="color:red">*</span></label>
        <input type="month" id="pay_month" name="pay_month"
               value="<?= $thisMonth ?>" required
               oninput="updateBalancePreview()">
        <small id="month_balance_hint" style="color:var(--text-muted); font-size:.78rem; margin-top:.3rem; display:block;"></small>
      </div>

      <div class="form-group">
        <label for="amount_paid">Amount (<?= CURRENCY_SYMBOL ?>) <span style="color:red">*</span></label>
        <input type="number" id="amount_paid" name="amount_paid"
               step="0.01" min="0.01" placeholder="0.00" required
               oninput="updateBalancePreview()">
      </div>

      <div class="form-group">
        <label for="payment_date">Payment Date <span style="color:red">*</span></label>
        <input type="date" id="payment_date" name="payment_date"
               value="<?= date('Y-m-d') ?>" required>
      </div>

      <div class="form-group">
        <label for="payment_mode">Payment Mode</label>
        <select id="payment_mode" name="payment_mode">
          <option value="cash">💵 Cash</option>
          <option value="upi">📱 UPI</option>
          <option value="bank">🏦 Bank Transfer</option>
        </select>
      </div>

      <div class="form-group">
        <label style="display:flex; align-items:center; gap:.6rem; cursor:pointer; font-weight:600;">
          <input type="checkbox" name="is_advance" id="is_advance" value="1"
                 style="width:16px; height:16px; cursor:pointer;"
                 onchange="updateBalancePreview()">
          This is an Advance Payment
        </label>
        <small style="color:var(--text-muted); font-size:.78rem; margin-top:.3rem; display:block;">
          Advances are tracked separately and don't count toward salary dues.
        </small>
      </div>

      <div class="form-group">
        <label for="note">Note (optional)</label>
        <input type="text" id="note" name="note"
               placeholder="e.g. Festival bonus, partial payment…">
      </div>

      <!-- Live preview -->
      <div id="payment_preview" style="background:#f0faf3; border:1px solid #a8dbb8; border-radius:8px;
                                       padding:.85rem 1rem; margin:.5rem 0; font-size:.87rem; color:#1a5c35; display:none;">
        <span id="preview_text"></span>
      </div>

      <div class="form-actions">
        <button type="submit" class="btn btn-primary">💾 Record Payment</button>
        <a href="<?= BASE_URL ?>/employees/index.php" class="btn btn-outline">← Back</a>
      </div>
    </form>
  </div>

  <!-- Quick month status -->
  <div class="card">
    <div class="card-title">Recent Month Status</div>
    <?php if (empty($monthlySummary)): ?>
      <p style="color:var(--text-muted); font-size:.88rem;">No payments recorded yet.</p>
    <?php else: ?>
      <?php foreach (array_slice($monthlySummary, 0, 6) as $ms): ?>
        <?php
          $pct = (float)$employee['monthly_salary'] > 0
                 ? min(100, ((float)$ms['salary_paid'] / (float)$employee['monthly_salary']) * 100)
                 : 0;
          $monthLabel = date('M Y', strtotime($ms['pay_month'] . '-01'));
          $isFullyPaid = (float)$ms['salary_paid'] >= (float)$employee['monthly_salary'];
        ?>
        <div style="margin-bottom:1rem;">
          <div style="display:flex; justify-content:space-between; align-items:center; font-size:.85rem; margin-bottom:.3rem;">
            <span style="font-weight:600;"><?= $monthLabel ?></span>
            <div style="display:flex; gap:.5rem; align-items:center;">
              <?php if ((float)$ms['advance_paid'] > 0): ?>
                <span class="badge badge-warning" style="font-size:.72rem;">
                  Adv: <?= CURRENCY_SYMBOL ?><?= number_format((float)$ms['advance_paid'], 0) ?>
                </span>
              <?php endif; ?>
              <?php if ($isFullyPaid): ?>
                <span class="badge badge-success" style="font-size:.72rem;">✅ Paid</span>
              <?php else: ?>
                <span style="color:var(--error); font-size:.8rem;">
                  <?= CURRENCY_SYMBOL ?><?= number_format((float)$ms['salary_paid'], 0) ?> /
                  <?= CURRENCY_SYMBOL ?><?= number_format((float)$employee['monthly_salary'], 0) ?>
                </span>
              <?php endif; ?>
            </div>
          </div>
          <div style="background:#eee; border-radius:4px; height:8px;">
            <div style="width:<?= round($pct) ?>%; background:<?= $isFullyPaid ? 'var(--success)' : 'var(--brand)' ?>; height:8px; border-radius:4px;"></div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>

    <div style="border-top:1px solid var(--border); margin-top:.5rem; padding-top:.75rem;
                font-size:.85rem; display:flex; justify-content:space-between;">
      <span style="color:var(--text-muted);">Total paid (all time)</span>
      <strong><?= CURRENCY_SYMBOL ?><?= number_format($totalEverPaid, 2) ?></strong>
    </div>
  </div>

</div>

<!-- ════════════════════════════════════════════════════════════
     TAB: PAYMENT HISTORY
════════════════════════════════════════════════════════════════-->
<?php elseif ($activeTab === 'history'): ?>

<div class="card" style="margin-bottom:1rem;">
  <form method="GET" action="">
    <input type="hidden" name="id"  value="<?= $id ?>">
    <input type="hidden" name="tab" value="history">
    <div style="display:flex; gap:.75rem; align-items:flex-end; flex-wrap:wrap;">
      <div class="form-group" style="margin:0;">
        <label>Filter by Month</label>
        <input type="month" name="hist_month"
               value="<?= htmlspecialchars($filterHistMonth) ?>">
      </div>
      <button type="submit" class="btn btn-primary btn-sm">🔍 Filter</button>
      <?php if ($filterHistMonth): ?>
        <a href="?id=<?= $id ?>&tab=history" class="btn btn-outline btn-sm">Reset</a>
      <?php endif; ?>
    </div>
  </form>
</div>

<div class="card">
  <div class="table-wrap">
    <?php if (empty($payments)): ?>
      <p style="color:var(--text-muted);">No payment records found.</p>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>Pay Month</th>
            <th>Payment Date</th>
            <th>Amount</th>
            <th>Mode</th>
            <th>Type</th>
            <th>Note</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php $grandTotal = 0; ?>
          <?php foreach ($payments as $i => $p): ?>
            <?php $grandTotal += (float)$p['amount_paid']; ?>
            <tr>
              <td><?= $i + 1 ?></td>
              <td><?= date('M Y', strtotime($p['pay_month'] . '-01')) ?></td>
              <td><?= date(DATE_FORMAT, strtotime($p['payment_date'])) ?></td>
              <td>
                <strong style="color:var(--success);">
                  <?= CURRENCY_SYMBOL ?><?= number_format((float)$p['amount_paid'], 2) ?>
                </strong>
              </td>
              <td>
                <span class="badge <?= $p['payment_mode'] === 'cash' ? 'badge-success' : 'badge-info' ?>">
                  <?= ucfirst($p['payment_mode']) ?>
                </span>
              </td>
              <td>
                <?php if ($p['is_advance']): ?>
                  <span class="badge badge-warning">Advance</span>
                <?php else: ?>
                  <span class="badge badge-success">Salary</span>
                <?php endif; ?>
              </td>
              <td style="color:var(--text-muted); font-size:.82rem;">
                <?= htmlspecialchars($p['note'] ?? '—') ?>
              </td>
              <td>
                <a href="?id=<?= $id ?>&tab=history&del_payment=<?= $p['id'] ?>&token=<?= $_SESSION['csrf_token'] ?>"
                   class="btn btn-danger btn-sm"
                   data-confirm="Delete this payment entry?">🗑</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr style="background:#f9f9f9; font-weight:600;">
            <td colspan="3" style="text-align:right;">Total</td>
            <td style="color:var(--success);"><?= CURRENCY_SYMBOL ?><?= number_format($grandTotal, 2) ?></td>
            <td colspan="4"></td>
          </tr>
        </tfoot>
      </table>
    <?php endif; ?>
  </div>
</div>

<!-- ════════════════════════════════════════════════════════════
     TAB: MONTHLY SUMMARY
════════════════════════════════════════════════════════════════-->
<?php elseif ($activeTab === 'summary'): ?>

<div class="card">
  <div class="card-title">📊 Month-wise Salary Summary — <?= htmlspecialchars($employee['name']) ?></div>
  <?php if (empty($monthlySummary)): ?>
    <p style="color:var(--text-muted);">No payment history yet.</p>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Month</th>
            <th>Salary Due</th>
            <th>Salary Paid</th>
            <th>Advance Paid</th>
            <th>Total Paid</th>
            <th>Balance</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($monthlySummary as $ms): ?>
            <?php
              $due     = (float)$employee['monthly_salary'];
              $salPaid = (float)$ms['salary_paid'];
              $advPaid = (float)$ms['advance_paid'];
              $total   = (float)$ms['total_paid'];
              $bal     = max(0, $due - $salPaid);
              $paid    = $salPaid >= $due;
            ?>
            <tr>
              <td><strong><?= date('M Y', strtotime($ms['pay_month'] . '-01')) ?></strong></td>
              <td><?= CURRENCY_SYMBOL ?><?= number_format($due, 2) ?></td>
              <td style="color:var(--success);"><?= CURRENCY_SYMBOL ?><?= number_format($salPaid, 2) ?></td>
              <td style="color:#e67e00;">
                <?= $advPaid > 0 ? CURRENCY_SYMBOL . number_format($advPaid, 2) : '—' ?>
              </td>
              <td><strong><?= CURRENCY_SYMBOL ?><?= number_format($total, 2) ?></strong></td>
              <td style="color:<?= $bal > 0 ? 'var(--error)' : 'var(--success)' ?>; font-weight:600;">
                <?= $bal > 0 ? CURRENCY_SYMBOL . number_format($bal, 2) : '—' ?>
              </td>
              <td>
                <?php if ($paid): ?>
                  <span class="badge badge-success">✅ Paid</span>
                <?php elseif ($salPaid > 0): ?>
                  <span class="badge badge-warning">Partial</span>
                <?php else: ?>
                  <span class="badge badge-danger">Unpaid</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr style="background:#f9f9f9; font-weight:600;">
            <td colspan="4" style="text-align:right;">Total Paid (all time)</td>
            <td style="color:var(--success);"><?= CURRENCY_SYMBOL ?><?= number_format($totalEverPaid, 2) ?></td>
            <td colspan="2"></td>
          </tr>
        </tfoot>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php endif; // end tab check ?>

<script>
// ── Live balance preview on Pay tab ──────────────────────────
const monthlySalary = <?= (float)$employee['monthly_salary'] ?>;

async function updateBalancePreview() {
  const month     = document.getElementById('pay_month')?.value;
  const amount    = parseFloat(document.getElementById('amount_paid')?.value) || 0;
  const isAdvance = document.getElementById('is_advance')?.checked;
  const preview   = document.getElementById('payment_preview');
  const text      = document.getElementById('preview_text');
  const hint      = document.getElementById('month_balance_hint');

  if (!month || !preview) return;

  // Fetch already-paid for this month via hidden form value isn't available here,
  // so we just give guidance based on current-month data if same month
  const thisMonth = '<?= $thisMonth ?>';
  let alreadyPaid = 0;

  if (month === thisMonth) {
    alreadyPaid = <?= $thisMonthPaid ?>;
  }

  const remaining = Math.max(0, monthlySalary - alreadyPaid);

  if (hint) {
    if (!isAdvance) {
      hint.textContent = month === thisMonth
        ? `Already paid: ₹${alreadyPaid.toFixed(2)} | Remaining: ₹${remaining.toFixed(2)}`
        : `Monthly salary: ₹${monthlySalary.toFixed(2)}`;
    } else {
      hint.textContent = 'Advance — recorded separately from salary dues.';
    }
  }

  if (amount <= 0) { preview.style.display = 'none'; return; }

  let msg;
  if (isAdvance) {
    msg = `Recording advance of ₹${amount.toFixed(2)} for ${month}. This won't reduce salary balance.`;
    preview.style.background = '#fffbf0';
    preview.style.border = '1px solid #f5d76e';
    preview.style.color = '#7a5c00';
  } else {
    const newBalance = Math.max(0, remaining - amount);
    const overpay = amount > remaining + 0.01;
    if (overpay) {
      msg = `⚠️ Amount exceeds remaining balance (₹${remaining.toFixed(2)}). Use advance checkbox for extra payments.`;
      preview.style.background = '#fff0f0';
      preview.style.border = '1px solid #f5a6a6';
      preview.style.color = '#8b1c1c';
    } else {
      msg = `Paying ₹${amount.toFixed(2)} salary for ${month}. New balance: ₹${newBalance.toFixed(2)}.`;
      preview.style.background = '#f0faf3';
      preview.style.border = '1px solid #a8dbb8';
      preview.style.color = '#1a5c35';
    }
  }

  text.textContent = msg;
  preview.style.display = 'block';
}

// Confirm delete
document.querySelectorAll('[data-confirm]').forEach(el => {
  el.addEventListener('click', e => {
    if (!confirm(el.dataset.confirm)) e.preventDefault();
  });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>