<?php
// ============================================================
//  inventory/edit.php — Edit stock item + log adjustments
// ============================================================
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    $_SESSION['flash_error'] = 'Invalid item.';
    header('Location: ' . BASE_URL . '/inventory/index.php');
    exit;
}

$item = db_fetch_one("SELECT * FROM stock_items WHERE id = ?", [$id]);
if (!$item) {
    $_SESSION['flash_error'] = 'Item not found.';
    header('Location: ' . BASE_URL . '/inventory/index.php');
    exit;
}

$pageTitle    = 'Edit Stock Item';
$pageSubtitle = 'Update details or adjust stock for: ' . htmlspecialchars($item['name']);

$errors      = [];
$adjErrors   = [];
$activeTab   = 'details'; // 'details' or 'adjust'

// ── TAB 1: Update item details ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_type']) && $_POST['form_type'] === 'details') {

    $activeTab = 'details';

    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request token.';
    } else {

        $name        = trim($_POST['name']         ?? '');
        $category    = trim($_POST['category']     ?? 'other');
        $unit        = trim($_POST['unit']         ?? 'kg');
        $low_stock   = $_POST['low_stock_at']      ?? '1';

        if ($name === '')  $errors[] = 'Item name is required.';
        if ($unit === '')  $errors[] = 'Unit is required.';
        if (!is_numeric($low_stock) || (float)$low_stock < 0)
                           $errors[] = 'Low-stock alert value must be 0 or more.';

        // Duplicate name check (excluding self)
        if (empty($errors)) {
            $exists = db_value(
                "SELECT COUNT(*) FROM stock_items WHERE name = ? AND id != ?",
                [$name, $id]
            );
            if ($exists > 0) $errors[] = "Another item named '{$name}' already exists.";
        }

        if (empty($errors)) {
            db_run(
                "UPDATE stock_items SET name = ?, category = ?, unit = ?, low_stock_at = ?
                  WHERE id = ?",
                [$name, $category, $unit, (float)$low_stock, $id]
            );
            $_SESSION['flash_success'] = "'{$name}' updated successfully.";
            header('Location: ' . BASE_URL . '/inventory/edit.php?id=' . $id);
            exit;
        }
    }
}

// ── TAB 2: Stock adjustment ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_type']) && $_POST['form_type'] === 'adjust') {

    $activeTab = 'adjust';

    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        $adjErrors[] = 'Invalid request token.';
    } else {

        $txn_type = trim($_POST['txn_type'] ?? '');
        $qty      = $_POST['adj_qty']       ?? '';
        $note     = trim($_POST['adj_note'] ?? '');
        $txn_date = trim($_POST['txn_date'] ?? date('Y-m-d'));

        if (!in_array($txn_type, ['in', 'out', 'adjustment'])) $adjErrors[] = 'Invalid transaction type.';
        if (!is_numeric($qty) || (float)$qty <= 0)              $adjErrors[] = 'Quantity must be greater than 0.';

        // For 'out', ensure enough stock
        if (empty($adjErrors) && $txn_type === 'out') {
            $current = (float)db_value("SELECT qty_in_hand FROM stock_items WHERE id = ?", [$id]);
            if ((float)$qty > $current) {
                $adjErrors[] = "Cannot remove more than available stock ({$current} {$item['unit']}).";
            }
        }

        if (empty($adjErrors)) {
            // Update qty_in_hand
            if ($txn_type === 'in') {
                db_run(
                    "UPDATE stock_items SET qty_in_hand = qty_in_hand + ? WHERE id = ?",
                    [(float)$qty, $id]
                );
            } elseif ($txn_type === 'out') {
                db_run(
                    "UPDATE stock_items SET qty_in_hand = qty_in_hand - ? WHERE id = ?",
                    [(float)$qty, $id]
                );
            } else {
                // Absolute adjustment
                db_run(
                    "UPDATE stock_items SET qty_in_hand = ? WHERE id = ?",
                    [(float)$qty, $id]
                );
            }

            // Log the transaction
            db_insert(
                "INSERT INTO stock_transactions (item_id, txn_type, qty, note, txn_date)
                 VALUES (?, ?, ?, ?, ?)",
                [$id, $txn_type, (float)$qty, $note ?: null, $txn_date]
            );

            $_SESSION['flash_success'] = 'Stock adjusted successfully.';
            header('Location: ' . BASE_URL . '/inventory/edit.php?id=' . $id);
            exit;
        }
    }
}

// ── Reload item (in case it was just updated) ─────────────────
$item = db_fetch_one("SELECT * FROM stock_items WHERE id = ?", [$id]);

// ── Transaction history (last 20) ────────────────────────────
$transactions = db_fetch_all(
    "SELECT * FROM stock_transactions
      WHERE item_id = ?
      ORDER BY txn_date DESC, id DESC
      LIMIT 20",
    [$id]
);

$categoryLabels = [
    'masala' => '🌶 Masala', 'chatni' => '🥫 Chatni', 'sev' => '🍟 Sev',
    'flour'  => '🌾 Flour',  'oil'    => '🫙 Oil',     'vegetable' => '🥦 Vegetable',
    'packaging' => '📦 Packaging', 'other' => '🗂 Other',
];

require_once __DIR__ . '/../includes/header.php';
?>

<!-- ── Item info strip ────────────────────────────────────────-->
<div style="background:#f8f9fb; border:1px solid var(--border); border-radius:10px;
            padding:1rem 1.25rem; margin-bottom:1.25rem;
            display:flex; gap:2rem; align-items:center; flex-wrap:wrap;">
  <div>
    <div style="font-size:.75rem; color:var(--text-muted); text-transform:uppercase; letter-spacing:.05em;">Item</div>
    <div style="font-size:1.1rem; font-weight:700;"><?= htmlspecialchars($item['name']) ?></div>
  </div>
  <div>
    <div style="font-size:.75rem; color:var(--text-muted); text-transform:uppercase; letter-spacing:.05em;">Category</div>
    <span class="badge badge-info"><?= $categoryLabels[$item['category']] ?? ucfirst($item['category']) ?></span>
  </div>
  <div>
    <div style="font-size:.75rem; color:var(--text-muted); text-transform:uppercase; letter-spacing:.05em;">Current Stock</div>
    <?php $isLow = (float)$item['qty_in_hand'] <= (float)$item['low_stock_at']; ?>
    <div style="font-size:1.2rem; font-weight:700;
                color:<?= (float)$item['qty_in_hand'] == 0 ? 'var(--error)' : ($isLow ? '#e67e00' : 'var(--success)') ?>;">
      <?= number_format((float)$item['qty_in_hand'], 3) ?> <?= htmlspecialchars($item['unit']) ?>
    </div>
  </div>
  <div>
    <div style="font-size:.75rem; color:var(--text-muted); text-transform:uppercase; letter-spacing:.05em;">Alert At</div>
    <div style="font-size:.95rem;"><?= number_format((float)$item['low_stock_at'], 3) ?> <?= htmlspecialchars($item['unit']) ?></div>
  </div>
  <?php if ((float)$item['qty_in_hand'] == 0): ?>
    <span class="badge badge-danger" style="font-size:.85rem; padding:.35rem .75rem;">🚫 OUT OF STOCK</span>
  <?php elseif ($isLow): ?>
    <span class="badge badge-warning" style="font-size:.85rem; padding:.35rem .75rem;">⚠️ LOW STOCK</span>
  <?php else: ?>
    <span class="badge badge-success" style="font-size:.85rem; padding:.35rem .75rem;">✅ Stock OK</span>
  <?php endif; ?>
</div>

<!-- ── Tab switcher ───────────────────────────────────────────-->
<div style="display:flex; gap:0; margin-bottom:1.25rem; border-bottom:2px solid var(--border);">
  <a href="?id=<?= $id ?>&tab=details"
     style="padding:.6rem 1.25rem; text-decoration:none; font-weight:600; font-size:.9rem;
            border-bottom:3px solid <?= $activeTab === 'details' ? 'var(--brand)' : 'transparent' ?>;
            color:<?= $activeTab === 'details' ? 'var(--brand)' : 'var(--text-muted)' ?>;
            margin-bottom:-2px;">
    ✏️ Edit Details
  </a>
  <a href="?id=<?= $id ?>&tab=adjust"
     style="padding:.6rem 1.25rem; text-decoration:none; font-weight:600; font-size:.9rem;
            border-bottom:3px solid <?= $activeTab === 'adjust' ? 'var(--brand)' : 'transparent' ?>;
            color:<?= $activeTab === 'adjust' ? 'var(--brand)' : 'var(--text-muted)' ?>;
            margin-bottom:-2px;">
    📊 Adjust Stock
  </a>
</div>

<!-- ════════════════════════════════════════════════════════════
     TAB: EDIT DETAILS
════════════════════════════════════════════════════════════════-->
<?php if ($activeTab === 'details'): ?>

<?php if (!empty($errors)): ?>
  <div class="alert alert-error">
    <?php foreach ($errors as $e): ?>
      <div>❌ <?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<div class="card">
  <form method="POST" action="">
    <input type="hidden" name="csrf_token"  value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="form_type"   value="details">

    <div class="form-grid">

      <div class="form-group" style="grid-column: span 2;">
        <label for="name">Item Name <span style="color:red">*</span></label>
        <input type="text" id="name" name="name"
               value="<?= htmlspecialchars($item['name']) ?>" required>
      </div>

      <div class="form-group">
        <label for="category">Category <span style="color:red">*</span></label>
        <select id="category" name="category">
          <option value="masala"    <?= $item['category'] === 'masala'    ? 'selected' : '' ?>>🌶 Masala</option>
          <option value="chatni"    <?= $item['category'] === 'chatni'    ? 'selected' : '' ?>>🥫 Chatni</option>
          <option value="sev"       <?= $item['category'] === 'sev'       ? 'selected' : '' ?>>🍟 Sev</option>
          <option value="flour"     <?= $item['category'] === 'flour'     ? 'selected' : '' ?>>🌾 Flour</option>
          <option value="oil"       <?= $item['category'] === 'oil'       ? 'selected' : '' ?>>🫙 Oil</option>
          <option value="vegetable" <?= $item['category'] === 'vegetable' ? 'selected' : '' ?>>🥦 Vegetable</option>
          <option value="packaging" <?= $item['category'] === 'packaging' ? 'selected' : '' ?>>📦 Packaging</option>
          <option value="other"     <?= $item['category'] === 'other'     ? 'selected' : '' ?>>🗂 Other</option>
        </select>
      </div>

      <div class="form-group">
        <label for="unit">Unit <span style="color:red">*</span></label>
        <select id="unit" name="unit">
          <option value="kg"     <?= $item['unit'] === 'kg'     ? 'selected' : '' ?>>kg</option>
          <option value="g"      <?= $item['unit'] === 'g'      ? 'selected' : '' ?>>grams (g)</option>
          <option value="litre"  <?= $item['unit'] === 'litre'  ? 'selected' : '' ?>>Litre</option>
          <option value="ml"     <?= $item['unit'] === 'ml'     ? 'selected' : '' ?>>ml</option>
          <option value="piece"  <?= $item['unit'] === 'piece'  ? 'selected' : '' ?>>Piece</option>
          <option value="packet" <?= $item['unit'] === 'packet' ? 'selected' : '' ?>>Packet</option>
          <option value="bag"    <?= $item['unit'] === 'bag'    ? 'selected' : '' ?>>Bag</option>
          <option value="dozen"  <?= $item['unit'] === 'dozen'  ? 'selected' : '' ?>>Dozen</option>
        </select>
      </div>

      <div class="form-group">
        <label for="low_stock_at">Low Stock Alert At <span style="color:red">*</span></label>
        <input type="number" id="low_stock_at" name="low_stock_at"
               value="<?= htmlspecialchars((string)$item['low_stock_at']) ?>"
               step="0.001" min="0" required>
        <small style="color:var(--text-muted); font-size:.78rem; margin-top:.25rem; display:block;">
          Alert triggers when stock ≤ this value
        </small>
      </div>

      <div class="form-group" style="background:#f9f9f9; border-radius:8px; padding:.85rem; border:1px solid var(--border);">
        <label style="color:var(--text-muted);">Current Quantity (read-only)</label>
        <div style="font-size:1.3rem; font-weight:700; margin-top:.35rem;
                    color:<?= (float)$item['qty_in_hand'] == 0 ? 'var(--error)' : 'var(--success)' ?>;">
          <?= number_format((float)$item['qty_in_hand'], 3) ?> <?= htmlspecialchars($item['unit']) ?>
        </div>
        <small style="color:var(--text-muted); font-size:.78rem;">
          Use "Adjust Stock" tab to change quantity
        </small>
      </div>

    </div>

    <div class="form-actions">
      <button type="submit" class="btn btn-primary">💾 Save Changes</button>
      <a href="<?= BASE_URL ?>/inventory/index.php" class="btn btn-outline">← Back to Inventory</a>
    </div>
  </form>
</div>

<!-- ════════════════════════════════════════════════════════════
     TAB: ADJUST STOCK
════════════════════════════════════════════════════════════════-->
<?php else: ?>

<?php if (!empty($adjErrors)): ?>
  <div class="alert alert-error">
    <?php foreach ($adjErrors as $e): ?>
      <div>❌ <?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<div style="display:grid; grid-template-columns:1fr 1fr; gap:1.25rem; align-items:start;">

  <!-- Adjustment form -->
  <div class="card">
    <div class="card-title">📊 Stock Adjustment</div>
    <form method="POST" action="?id=<?= $id ?>&tab=adjust">
      <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
      <input type="hidden" name="form_type"  value="adjust">

      <div class="form-group">
        <label for="txn_type">Transaction Type <span style="color:red">*</span></label>
        <select id="txn_type" name="txn_type" required onchange="updateAdjLabel(this.value)">
          <option value="in">➕ Stock In — Add quantity</option>
          <option value="out">➖ Stock Out — Reduce quantity</option>
          <option value="adjustment">🔄 Manual Adjustment — Set exact quantity</option>
        </select>
      </div>

      <div class="form-group">
        <label for="adj_qty" id="adj_qty_label">Quantity to Add <span style="color:red">*</span></label>
        <input type="number" id="adj_qty" name="adj_qty"
               step="0.001" min="0.001" placeholder="0.000" required>
        <small id="adj_qty_hint" style="color:var(--text-muted); font-size:.78rem; margin-top:.25rem; display:block;">
          Enter quantity to add to current stock
        </small>
      </div>

      <div class="form-group">
        <label for="txn_date">Date <span style="color:red">*</span></label>
        <input type="date" id="txn_date" name="txn_date"
               value="<?= date('Y-m-d') ?>" required>
      </div>

      <div class="form-group">
        <label for="adj_note">Note (optional)</label>
        <input type="text" id="adj_note" name="adj_note"
               placeholder="e.g. Purchased from Munim Ji, Used for cooking…">
      </div>

      <!-- Preview -->
      <div id="adj_preview" style="background:#f0f7ff; border:1px solid #b8d9f8; border-radius:8px;
                                   padding:.85rem 1rem; margin:.75rem 0; font-size:.88rem; color:#1a4f8a; display:none;">
        <strong>Preview:</strong>
        <span id="preview_text"></span>
      </div>

      <div class="form-actions">
        <button type="submit" class="btn btn-primary">✅ Apply Adjustment</button>
        <a href="<?= BASE_URL ?>/inventory/index.php" class="btn btn-outline">← Back</a>
      </div>
    </form>
  </div>

  <!-- Transaction history -->
  <div class="card">
    <div class="card-title">📜 Recent Transactions</div>
    <?php if (empty($transactions)): ?>
      <p style="color:var(--text-muted); font-size:.88rem;">No transactions recorded yet.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Date</th>
              <th>Type</th>
              <th>Qty</th>
              <th>Note</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($transactions as $txn): ?>
              <tr>
                <td><?= date(DATE_FORMAT, strtotime($txn['txn_date'])) ?></td>
                <td>
                  <?php if ($txn['txn_type'] === 'in'): ?>
                    <span class="badge badge-success">➕ In</span>
                  <?php elseif ($txn['txn_type'] === 'out'): ?>
                    <span class="badge badge-danger">➖ Out</span>
                  <?php else: ?>
                    <span class="badge badge-info">🔄 Adjust</span>
                  <?php endif; ?>
                </td>
                <td>
                  <strong style="color:<?= $txn['txn_type'] === 'out' ? 'var(--error)' : 'var(--success)' ?>;">
                    <?= $txn['txn_type'] === 'out' ? '-' : '+' ?>
                    <?= number_format((float)$txn['qty'], 3) ?>
                  </strong>
                </td>
                <td style="font-size:.82rem; color:var(--text-muted);">
                  <?= htmlspecialchars($txn['note'] ?? '—') ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

</div>

<script>
const currentQty  = <?= (float)$item['qty_in_hand'] ?>;
const currentUnit = '<?= addslashes($item['unit']) ?>';

function updateAdjLabel(type) {
  const label    = document.getElementById('adj_qty_label');
  const hint     = document.getElementById('adj_qty_hint');
  const qtyInput = document.getElementById('adj_qty');

  if (type === 'in') {
    label.innerHTML = 'Quantity to Add <span style="color:red">*</span>';
    hint.textContent = 'Quantity added to current stock (' + currentQty.toFixed(3) + ' ' + currentUnit + ')';
  } else if (type === 'out') {
    label.innerHTML = 'Quantity to Remove <span style="color:red">*</span>';
    hint.textContent = 'Quantity removed from current stock (' + currentQty.toFixed(3) + ' ' + currentUnit + ')';
  } else {
    label.innerHTML = 'Set Exact Quantity <span style="color:red">*</span>';
    hint.textContent = 'Stock will be set to exactly this value (current: ' + currentQty.toFixed(3) + ' ' + currentUnit + ')';
  }
  updatePreview();
}

function updatePreview() {
  const type    = document.getElementById('txn_type').value;
  const qty     = parseFloat(document.getElementById('adj_qty').value) || 0;
  const preview = document.getElementById('adj_preview');
  const text    = document.getElementById('preview_text');

  if (qty <= 0) { preview.style.display = 'none'; return; }

  let newQty, msg;
  if (type === 'in') {
    newQty = currentQty + qty;
    msg = `Adding ${qty.toFixed(3)} ${currentUnit} → New stock: ${newQty.toFixed(3)} ${currentUnit}`;
  } else if (type === 'out') {
    newQty = currentQty - qty;
    msg = `Removing ${qty.toFixed(3)} ${currentUnit} → New stock: ${newQty.toFixed(3)} ${currentUnit}`;
    if (newQty < 0) msg += ' ⚠️ (will go negative — not allowed)';
  } else {
    msg = `Stock will be set to exactly ${qty.toFixed(3)} ${currentUnit}`;
  }

  text.textContent = msg;
  preview.style.display = 'block';
}

document.getElementById('txn_type').addEventListener('change', e => updateAdjLabel(e.target.value));
document.getElementById('adj_qty').addEventListener('input', updatePreview);
updateAdjLabel('in');
</script>

<?php endif; // end tab check ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>