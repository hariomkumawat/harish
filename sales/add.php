<?php
// ============================================================
//  sales/add.php — Record a new sale
// ============================================================
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';

$pageTitle    = 'Add Sale';
$pageSubtitle = 'Record a new sale entry';

$locations = db_fetch_all("SELECT id, name FROM locations ORDER BY id");
$products  = db_fetch_all("SELECT id, name, sale_price FROM products WHERE is_active = 1 ORDER BY name");

$errors = [];

// ── Always initialize $old with defaults first ────────────────
$old = [
    'location_id'  => DEFAULT_LOCATION_ID,
    'product_id'   => '',
    'sale_date'    => date('Y-m-d'),
    'qty_sold'     => '',
    'unit_price'   => '',
    'payment_mode' => 'cash',
    'note'         => '',
];

// ── Handle POST ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request token. Please try again.';
    } else {

        $old = [
            'location_id'  => (int)   ($_POST['location_id']  ?? DEFAULT_LOCATION_ID),
            'product_id'   => (int)   ($_POST['product_id']   ?? 0),
            'sale_date'    =>          $_POST['sale_date']    ?? date('Y-m-d'),
            'qty_sold'     => (float) ($_POST['qty_sold']     ?? 0),
            'unit_price'   => (float) ($_POST['unit_price']   ?? 0),
            'payment_mode' =>          $_POST['payment_mode'] ?? 'cash',
            'note'         => trim(    $_POST['note']         ?? ''),
        ];

        if (!$old['location_id'])     $errors[] = 'Please select a location.';
        if (!$old['product_id'])      $errors[] = 'Please select a product.';
        if (empty($old['sale_date'])) $errors[] = 'Sale date is required.';
        if ($old['qty_sold']  <= 0)   $errors[] = 'Quantity must be greater than 0.';
        if ($old['unit_price'] <= 0)  $errors[] = 'Unit price must be greater than 0.';

        if (empty($errors)) {
            db_insert(
                "INSERT INTO sales
                   (location_id, product_id, sale_date, qty_sold, unit_price, payment_mode, note)
                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                [
                    $old['location_id'],
                    $old['product_id'],
                    $old['sale_date'],
                    $old['qty_sold'],
                    $old['unit_price'],
                    $old['payment_mode'],
                    $old['note'] ?: null,
                ]
            );

            $_SESSION['flash_success'] = 'Sale entry added successfully.';
            header('Location: ' . BASE_URL . '/sales/index.php');
            exit;
        }
    }
}

// ── Safe currency symbol for HTML + JS output ─────────────────
$cur = 'Rs.';   // change to '&#8377;' if you want the rupee sign

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
  <form method="POST" action="" id="saleForm">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="form-grid">

      <div class="form-group">
        <label for="sale_date">Date <span style="color:red">*</span></label>
        <input type="date" id="sale_date" name="sale_date"
               value="<?= htmlspecialchars($old['sale_date']) ?>" required>
      </div>

      <div class="form-group">
        <label for="location_id">Location <span style="color:red">*</span></label>
        <select id="location_id" name="location_id" required>
          <option value="">— Select —</option>
          <?php foreach ($locations as $loc): ?>
            <option value="<?= $loc['id'] ?>"
              <?= $old['location_id'] == $loc['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($loc['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label for="product_id">Product <span style="color:red">*</span></label>
        <select id="product_id" name="product_id" required>
          <option value="">— Select —</option>
          <?php foreach ($products as $prod): ?>
            <option value="<?= $prod['id'] ?>"
                    data-price="<?= number_format((float)$prod['sale_price'], 2, '.', '') ?>"
              <?= $old['product_id'] == $prod['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($prod['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <!-- ✅ FIX: use $cur instead of CURRENCY_SYMBOL in label -->
        <label for="unit_price">Unit Price (<?= $cur ?>) <span style="color:red">*</span></label>
        <input type="number" id="unit_price" name="unit_price"
               value="<?= htmlspecialchars((string)$old['unit_price']) ?>"
               step="0.01" min="0.01" placeholder="0.00" required>
      </div>

      <div class="form-group">
        <label for="qty_sold">Quantity <span style="color:red">*</span></label>
        <input type="number" id="qty_sold" name="qty_sold"
               value="<?= htmlspecialchars((string)$old['qty_sold']) ?>"
               step="0.5" min="0.5" placeholder="0" required>
      </div>

      <div class="form-group">
        <label>Total Amount</label>
        <input type="text" id="total_display" readonly
               placeholder="Auto calculated"
               style="background:#f9f9f9; font-weight:600; color:var(--success);">
      </div>

      <div class="form-group">
        <label for="payment_mode">Payment Mode</label>
        <select id="payment_mode" name="payment_mode">
          <option value="cash"  <?= $old['payment_mode'] === 'cash'  ? 'selected' : '' ?>>Cash</option>
          <option value="upi"   <?= $old['payment_mode'] === 'upi'   ? 'selected' : '' ?>>UPI</option>
          <option value="other" <?= $old['payment_mode'] === 'other' ? 'selected' : '' ?>>Other</option>
        </select>
      </div>

      <div class="form-group">
        <label for="note">Note (optional)</label>
        <input type="text" id="note" name="note"
               value="<?= htmlspecialchars($old['note']) ?>"
               placeholder="Any remark...">
      </div>

    </div>

    <div class="form-actions">
      <button type="submit" class="btn btn-primary">Save Sale</button>
      <a href="<?= BASE_URL ?>/sales/index.php" class="btn btn-outline">Cancel</a>
    </div>

  </form>
</div>

<script>
// ✅ FIX: currency symbol passed safely from PHP to JS as a plain string
const currencySymbol = <?= json_encode($cur) ?>;

document.getElementById('product_id').addEventListener('change', function () {
  const price = this.options[this.selectedIndex]?.dataset?.price ?? '';
  if (price) document.getElementById('unit_price').value = parseFloat(price).toFixed(2);
  calcTotal();
});

function calcTotal() {
  const qty   = parseFloat(document.getElementById('qty_sold').value)   || 0;
  const price = parseFloat(document.getElementById('unit_price').value) || 0;
  const total = qty * price;
  document.getElementById('total_display').value =
    total > 0 ? currencySymbol + total.toFixed(2) : '';
}

document.getElementById('qty_sold').addEventListener('input',   calcTotal);
document.getElementById('unit_price').addEventListener('input', calcTotal);
calcTotal();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>