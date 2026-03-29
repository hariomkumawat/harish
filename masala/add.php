<?php
// ============================================================
//  masala/add.php — Add OR Edit a purchase entry
//  Add mode:  masala/add.php
//  Edit mode: masala/add.php?edit=ID
// ============================================================
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';

$editId   = (int)($_GET['edit'] ?? $_POST['edit_id'] ?? 0);
$isEdit   = $editId > 0;
$purchase = null;

if ($isEdit) {
    $purchase = db_fetch_one(
        "SELECT p.*, v.name AS vendor_name FROM purchases p
           JOIN vendors v ON v.id = p.vendor_id
          WHERE p.id = ?",
        [$editId]
    );
    if (!$purchase) {
        $_SESSION['flash_error'] = 'Purchase entry not found.';
        header('Location: ' . BASE_URL . '/masala/index.php');
        exit;
    }
}

$pageTitle    = $isEdit ? 'Edit Purchase' : 'New Purchase Entry';
$pageSubtitle = $isEdit
    ? 'Update entry: ' . htmlspecialchars($purchase['item_name'])
    : 'Record a raw material purchase from vendor';

$vendors     = db_fetch_all("SELECT id, name FROM vendors ORDER BY name");
$stockItems  = db_fetch_all("SELECT id, name, unit FROM stock_items ORDER BY name");
$errors      = [];

// ── Default values ────────────────────────────────────────────
$old = $isEdit ? [
    'vendor_id'     => $purchase['vendor_id'],
    'item_name'     => $purchase['item_name'],
    'qty'           => $purchase['qty'],
    'unit'          => $purchase['unit'],
    'rate'          => $purchase['rate'],
    'purchase_date' => $purchase['purchase_date'],
    'payment_mode'  => $purchase['payment_mode'],
    'is_paid'       => $purchase['is_paid'],
    'note'          => $purchase['note'] ?? '',
] : [
    'vendor_id'     => count($vendors) === 1 ? $vendors[0]['id'] : '',
    'item_name'     => '',
    'qty'           => '',
    'unit'          => 'kg',
    'rate'          => '',
    'purchase_date' => date('Y-m-d'),
    'payment_mode'  => 'cash',
    'is_paid'       => 1,
    'note'          => '',
];

// ── Handle POST ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request token. Please try again.';
    } else {

        $old = [
            'vendor_id'     => (int)  ($_POST['vendor_id']     ?? 0),
            'item_name'     => trim(   $_POST['item_name']      ?? ''),
            'qty'           =>         $_POST['qty']            ?? '',
            'unit'          => trim(   $_POST['unit']           ?? 'kg'),
            'rate'          =>         $_POST['rate']           ?? '',
            'purchase_date' => trim(   $_POST['purchase_date']  ?? date('Y-m-d')),
            'payment_mode'  => trim(   $_POST['payment_mode']   ?? 'cash'),
            'is_paid'       => isset($_POST['is_paid']) ? 1 : 0,
            'note'          => trim(   $_POST['note']           ?? ''),
        ];

        $updateStock  = isset($_POST['update_stock']);
        $stockItemId  = (int)($_POST['stock_item_id'] ?? 0);

        // Validation
        if (!$old['vendor_id'])                                      $errors[] = 'Please select a vendor.';
        if ($old['item_name'] === '')                                $errors[] = 'Item name is required.';
        if (!is_numeric($old['qty']) || (float)$old['qty'] <= 0)    $errors[] = 'Quantity must be greater than 0.';
        if (!is_numeric($old['rate']) || (float)$old['rate'] <= 0)  $errors[] = 'Rate must be greater than 0.';
        if (empty($old['purchase_date']))                            $errors[] = 'Purchase date is required.';
        if ($old['unit'] === '')                                     $errors[] = 'Unit is required.';
        if ($updateStock && !$stockItemId)                           $errors[] = 'Please select a stock item to update, or uncheck the option.';

        if (empty($errors)) {
            if ($isEdit) {
                db_run(
                    "UPDATE purchases
                        SET vendor_id = ?, item_name = ?, qty = ?, unit = ?,
                            rate = ?, purchase_date = ?, payment_mode = ?,
                            is_paid = ?, note = ?
                      WHERE id = ?",
                    [
                        $old['vendor_id'],
                        $old['item_name'],
                        (float)$old['qty'],
                        $old['unit'],
                        (float)$old['rate'],
                        $old['purchase_date'],
                        $old['payment_mode'],
                        $old['is_paid'],
                        $old['note'] ?: null,
                        $editId,
                    ]
                );
                $_SESSION['flash_success'] = 'Purchase entry updated.';

            } else {
                // Insert purchase
                db_insert(
                    "INSERT INTO purchases
                       (vendor_id, item_name, qty, unit, rate, purchase_date, payment_mode, is_paid, note)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [
                        $old['vendor_id'],
                        $old['item_name'],
                        (float)$old['qty'],
                        $old['unit'],
                        (float)$old['rate'],
                        $old['purchase_date'],
                        $old['payment_mode'],
                        $old['is_paid'],
                        $old['note'] ?: null,
                    ]
                );

                // Optionally bump stock
                if ($updateStock && $stockItemId) {
                    db_run(
                        "UPDATE stock_items SET qty_in_hand = qty_in_hand + ? WHERE id = ?",
                        [(float)$old['qty'], $stockItemId]
                    );
                    db_insert(
                        "INSERT INTO stock_transactions (item_id, txn_type, qty, note, txn_date)
                         VALUES (?, 'in', ?, ?, ?)",
                        [
                            $stockItemId,
                            (float)$old['qty'],
                            'Purchase from vendor: ' . $old['item_name'],
                            $old['purchase_date'],
                        ]
                    );
                    $_SESSION['flash_success'] = 'Purchase saved & stock updated.';
                } else {
                    $_SESSION['flash_success'] = 'Purchase entry saved.';
                }

                // Save & Add Another
                if (($_POST['action'] ?? '') === 'save_add') {
                    header('Location: ' . BASE_URL . '/masala/add.php');
                    exit;
                }
            }

            header('Location: ' . BASE_URL . '/masala/index.php');
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

<div style="display:grid; grid-template-columns:1fr <?= !$isEdit ? '280px' : '' ?>; gap:1.25rem; align-items:start;">

  <!-- Main form -->
  <div class="card">
    <form method="POST" action="" id="purchaseForm">
      <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
      <?php if ($isEdit): ?>
        <input type="hidden" name="edit_id" value="<?= $editId ?>">
      <?php endif; ?>

      <div class="form-grid">

        <!-- Purchase Date -->
        <div class="form-group">
          <label for="purchase_date">Date <span style="color:red">*</span></label>
          <input type="date" id="purchase_date" name="purchase_date"
                 value="<?= htmlspecialchars($old['purchase_date']) ?>" required>
        </div>

        <!-- Vendor -->
        <div class="form-group">
          <label for="vendor_id">Vendor <span style="color:red">*</span></label>
          <select id="vendor_id" name="vendor_id" required>
            <option value="">— Select Vendor —</option>
            <?php foreach ($vendors as $v): ?>
              <option value="<?= $v['id'] ?>" <?= $old['vendor_id'] == $v['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($v['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <small style="font-size:.78rem; color:var(--text-muted); margin-top:.2rem; display:block;">
            <a href="<?= BASE_URL ?>/masala/vendors.php" style="color:var(--brand);">+ Add new vendor</a>
          </small>
        </div>

        <!-- Item Name (free text + suggestions) -->
        <div class="form-group" style="grid-column: span 2;">
          <label for="item_name">Item Name <span style="color:red">*</span></label>
          <input type="text" id="item_name" name="item_name"
                 value="<?= htmlspecialchars($old['item_name']) ?>"
                 placeholder="e.g. Pav Bhaji Masala, Chatni Powder, Sev…"
                 list="item_suggestions"
                 required autocomplete="off">
          <datalist id="item_suggestions">
            <option value="Pav Bhaji Masala">
            <option value="Chatni Powder">
            <option value="Sev">
            <option value="Green Chatni">
            <option value="Red Chatni">
            <option value="Besan (Gram Flour)">
            <option value="Oil">
            <option value="Potato">
            <option value="Pav (Bread)">
          </datalist>
        </div>

        <!-- Quantity -->
        <div class="form-group">
          <label for="qty">Quantity <span style="color:red">*</span></label>
          <input type="number" id="qty" name="qty"
                 value="<?= htmlspecialchars((string)$old['qty']) ?>"
                 step="0.001" min="0.001" placeholder="0.000"
                 required oninput="calcTotal()">
        </div>

        <!-- Unit -->
        <div class="form-group">
          <label for="unit">Unit <span style="color:red">*</span></label>
          <select id="unit" name="unit">
            <option value="kg"     <?= $old['unit'] === 'kg'     ? 'selected' : '' ?>>kg</option>
            <option value="g"      <?= $old['unit'] === 'g'      ? 'selected' : '' ?>>grams (g)</option>
            <option value="litre"  <?= $old['unit'] === 'litre'  ? 'selected' : '' ?>>Litre</option>
            <option value="ml"     <?= $old['unit'] === 'ml'     ? 'selected' : '' ?>>ml</option>
            <option value="piece"  <?= $old['unit'] === 'piece'  ? 'selected' : '' ?>>Piece</option>
            <option value="packet" <?= $old['unit'] === 'packet' ? 'selected' : '' ?>>Packet</option>
            <option value="bag"    <?= $old['unit'] === 'bag'    ? 'selected' : '' ?>>Bag</option>
            <option value="dozen"  <?= $old['unit'] === 'dozen'  ? 'selected' : '' ?>>Dozen</option>
          </select>
        </div>

        <!-- Rate per unit -->
        <div class="form-group">
          <label for="rate">Rate per Unit (<?= CURRENCY_SYMBOL ?>) <span style="color:red">*</span></label>
          <input type="number" id="rate" name="rate"
                 value="<?= htmlspecialchars((string)$old['rate']) ?>"
                 step="0.01" min="0.01" placeholder="0.00"
                 required oninput="calcTotal()">
        </div>

        <!-- Total (auto-calculated display) -->
        <div class="form-group">
          <label>Total Amount</label>
          <input type="text" id="total_display" readonly
                 placeholder="Auto calculated"
                 style="background:#f9f9f9; font-weight:700; color:var(--brand); font-size:1.05rem;">
        </div>

        <!-- Payment Mode -->
        <div class="form-group">
          <label for="payment_mode">Payment Mode</label>
          <select id="payment_mode" name="payment_mode" onchange="handlePaymentMode(this.value)">
            <option value="cash"   <?= $old['payment_mode'] === 'cash'   ? 'selected' : '' ?>>💵 Cash</option>
            <option value="upi"    <?= $old['payment_mode'] === 'upi'    ? 'selected' : '' ?>>📱 UPI</option>
            <option value="credit" <?= $old['payment_mode'] === 'credit' ? 'selected' : '' ?>>🔖 Credit (Udhar)</option>
          </select>
        </div>

        <!-- Paid status -->
        <div class="form-group" style="display:flex; align-items:flex-end; padding-bottom:.3rem;">
          <label style="display:flex; align-items:center; gap:.6rem; cursor:pointer; font-weight:600; margin-bottom:0;">
            <input type="checkbox" name="is_paid" id="is_paid" value="1"
                   <?= $old['is_paid'] ? 'checked' : '' ?>
                   style="width:17px; height:17px; cursor:pointer;">
            Mark as Paid
          </label>
        </div>

        <!-- Note -->
        <div class="form-group" style="grid-column: span 2;">
          <label for="note">Note (optional)</label>
          <input type="text" id="note" name="note"
                 value="<?= htmlspecialchars($old['note']) ?>"
                 placeholder="Any remark, batch number, quality note…">
        </div>

      </div>

      <?php if (!$isEdit): ?>
      <!-- ── Stock update option ─────────────────────────────-->
      <div id="stock_update_box" style="background:#f0f7ff; border:1px solid #b8d9f8; border-radius:8px;
                                         padding:1rem; margin:1rem 0;">
        <label style="display:flex; align-items:center; gap:.7rem; font-weight:600; cursor:pointer; margin-bottom:.6rem;">
          <input type="checkbox" name="update_stock" id="update_stock" value="1"
                 style="width:17px; height:17px; cursor:pointer;"
                 onchange="toggleStockSelect(this.checked)">
          Also update Inventory stock
        </label>
        <small style="color:#1a4f8a; font-size:.8rem; display:block; margin-bottom:.75rem;">
          If this purchase was physically received, link it to an inventory item to update the stock count automatically.
        </small>
        <div id="stock_select_wrap" style="display:none;">
          <label for="stock_item_id" style="font-size:.85rem; font-weight:600;">Select Inventory Item</label>
          <select id="stock_item_id" name="stock_item_id" style="margin-top:.3rem;">
            <option value="">— Select item —</option>
            <?php foreach ($stockItems as $si): ?>
              <option value="<?= $si['id'] ?>">
                <?= htmlspecialchars($si['name']) ?> (<?= htmlspecialchars($si['unit']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <?php endif; ?>

      <div class="form-actions">
        <button type="submit" name="action" value="save" class="btn btn-primary">
          <?= $isEdit ? '💾 Save Changes' : '💾 Save Entry' ?>
        </button>
        <?php if (!$isEdit): ?>
          <button type="submit" name="action" value="save_add" class="btn btn-outline">💾 Save & Add Another</button>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/masala/index.php" class="btn btn-outline">Cancel</a>
      </div>

    </form>
  </div>

  <?php if (!$isEdit): ?>
  <!-- Sidebar: recent purchases quick glance -->
  <div class="card">
    <div class="card-title">Recent Purchases</div>
    <?php
    $recent = db_fetch_all(
        "SELECT p.purchase_date, p.item_name, p.total_amount, p.is_paid, v.name AS vendor_name
           FROM purchases p JOIN vendors v ON v.id = p.vendor_id
          ORDER BY p.id DESC LIMIT 8"
    );
    ?>
    <?php if (empty($recent)): ?>
      <p style="color:var(--text-muted); font-size:.85rem;">No entries yet.</p>
    <?php else: ?>
      <?php foreach ($recent as $r): ?>
        <div style="display:flex; justify-content:space-between; align-items:flex-start;
                    padding:.6rem 0; border-bottom:1px solid var(--border); font-size:.83rem;">
          <div>
            <div style="font-weight:600;"><?= htmlspecialchars($r['item_name']) ?></div>
            <div style="color:var(--text-muted);">
              <?= htmlspecialchars($r['vendor_name']) ?> &middot;
              <?= date('d M', strtotime($r['purchase_date'])) ?>
            </div>
          </div>
          <div style="text-align:right;">
            <div style="font-weight:700; color:var(--brand);">
              <?= CURRENCY_SYMBOL ?><?= number_format((float)$r['total_amount'], 2) ?>
            </div>
            <?php if (!$r['is_paid']): ?>
              <span class="badge badge-warning" style="font-size:.7rem;">Unpaid</span>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
  <?php endif; ?>

</div>

<script>
function calcTotal() {
  const qty   = parseFloat(document.getElementById('qty').value)  || 0;
  const rate  = parseFloat(document.getElementById('rate').value) || 0;
  const total = qty * rate;
  const disp  = document.getElementById('total_display');
  disp.value  = total > 0 ? '<?= CURRENCY_SYMBOL ?>' + total.toFixed(2) : '';
}

function handlePaymentMode(mode) {
  const isPaidCheck = document.getElementById('is_paid');
  if (!isPaidCheck) return;
  if (mode === 'credit') {
    isPaidCheck.checked = false;   // credit → unpaid by default
  } else {
    isPaidCheck.checked = true;    // cash/upi → paid by default
  }
}

function toggleStockSelect(checked) {
  document.getElementById('stock_select_wrap').style.display = checked ? 'block' : 'none';
  document.getElementById('stock_item_id').required = checked;
}

// Init
calcTotal();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>