<?php
// ============================================================
//  inventory/add.php — Add a new stock item
// ============================================================
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';

$pageTitle    = 'Add Stock Item';
$pageSubtitle = 'Register a new inventory item';

$errors = [];

$old = [
    'name'         => '',
    'category'     => 'other',
    'unit'         => 'kg',
    'qty_in_hand'  => '',
    'low_stock_at' => '1.000',
];

// ── Handle POST ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request token. Please try again.';
    } else {

        $old = [
            'name'         => trim($_POST['name']         ?? ''),
            'category'     => trim($_POST['category']     ?? 'other'),
            'unit'         => trim($_POST['unit']         ?? 'kg'),
            'qty_in_hand'  => $_POST['qty_in_hand']       ?? '',
            'low_stock_at' => $_POST['low_stock_at']      ?? '1',
        ];

        // Validation
        if ($old['name'] === '')             $errors[] = 'Item name is required.';
        if ($old['unit'] === '')             $errors[] = 'Unit is required.';
        if (!is_numeric($old['qty_in_hand'])) $errors[] = 'Opening stock must be a valid number.';
        if ((float)$old['qty_in_hand'] < 0)  $errors[] = 'Opening stock cannot be negative.';
        if (!is_numeric($old['low_stock_at']) || (float)$old['low_stock_at'] < 0)
                                             $errors[] = 'Low-stock alert value must be 0 or more.';

        // Duplicate name check
        if (empty($errors)) {
            $exists = db_value(
                "SELECT COUNT(*) FROM stock_items WHERE name = ?",
                [$old['name']]
            );
            if ($exists > 0) $errors[] = "An item named '{$old['name']}' already exists.";
        }

        if (empty($errors)) {
            $itemId = db_insert(
                "INSERT INTO stock_items (name, category, unit, qty_in_hand, low_stock_at)
                 VALUES (?, ?, ?, ?, ?)",
                [
                    $old['name'],
                    $old['category'],
                    $old['unit'],
                    (float)$old['qty_in_hand'],
                    (float)$old['low_stock_at'],
                ]
            );

            // Record opening stock as a transaction (if qty > 0)
            if ((float)$old['qty_in_hand'] > 0) {
                db_insert(
                    "INSERT INTO stock_transactions (item_id, txn_type, qty, note, txn_date)
                     VALUES (?, 'in', ?, 'Opening stock', ?)",
                    [$itemId, (float)$old['qty_in_hand'], date('Y-m-d')]
                );
            }

            $_SESSION['flash_success'] = "'{$old['name']}' added to inventory.";

            // Save & Add Another
            if (($_POST['action'] ?? '') === 'save_add') {
                header('Location: ' . BASE_URL . '/inventory/add.php');
            } else {
                header('Location: ' . BASE_URL . '/inventory/index.php');
            }
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

      <!-- Item Name -->
      <div class="form-group" style="grid-column: span 2;">
        <label for="name">Item Name <span style="color:red">*</span></label>
        <input type="text" id="name" name="name"
               value="<?= htmlspecialchars($old['name']) ?>"
               placeholder="e.g. Pav Bhaji Masala, Chatni Powder, Sev…"
               required autofocus>
      </div>

      <!-- Category -->
      <div class="form-group">
        <label for="category">Category <span style="color:red">*</span></label>
        <select id="category" name="category" required>
          <option value="masala"    <?= $old['category'] === 'masala'    ? 'selected' : '' ?>>🌶 Masala</option>
          <option value="chatni"    <?= $old['category'] === 'chatni'    ? 'selected' : '' ?>>🥫 Chatni</option>
          <option value="sev"       <?= $old['category'] === 'sev'       ? 'selected' : '' ?>>🍟 Sev</option>
          <option value="flour"     <?= $old['category'] === 'flour'     ? 'selected' : '' ?>>🌾 Flour</option>
          <option value="oil"       <?= $old['category'] === 'oil'       ? 'selected' : '' ?>>🫙 Oil</option>
          <option value="vegetable" <?= $old['category'] === 'vegetable' ? 'selected' : '' ?>>🥦 Vegetable</option>
          <option value="packaging" <?= $old['category'] === 'packaging' ? 'selected' : '' ?>>📦 Packaging</option>
          <option value="other"     <?= $old['category'] === 'other'     ? 'selected' : '' ?>>🗂 Other</option>
        </select>
      </div>

      <!-- Unit -->
      <div class="form-group">
        <label for="unit">Unit <span style="color:red">*</span></label>
        <select id="unit" name="unit" required>
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

      <!-- Opening Stock -->
      <div class="form-group">
        <label for="qty_in_hand">Opening Stock <span style="color:red">*</span></label>
        <input type="number" id="qty_in_hand" name="qty_in_hand"
               value="<?= htmlspecialchars((string)$old['qty_in_hand']) ?>"
               step="0.001" min="0" placeholder="0.000" required>
        <small style="color:var(--text-muted); font-size:.78rem; margin-top:.25rem; display:block;">
          Current quantity in hand (can be 0 for new items)
        </small>
      </div>

      <!-- Low Stock Alert Threshold -->
      <div class="form-group">
        <label for="low_stock_at">Low Stock Alert At <span style="color:red">*</span></label>
        <input type="number" id="low_stock_at" name="low_stock_at"
               value="<?= htmlspecialchars((string)$old['low_stock_at']) ?>"
               step="0.001" min="0" placeholder="1.000" required>
        <small style="color:var(--text-muted); font-size:.78rem; margin-top:.25rem; display:block;">
          Alert shows when stock falls to or below this value
        </small>
      </div>

    </div>

    <!-- Info box -->
    <div style="background:#f0f7ff; border:1px solid #b8d9f8; border-radius:8px;
                padding:.9rem 1rem; margin:1rem 0; font-size:.85rem; color:#1a4f8a;">
      💡 <strong>Tip:</strong> Opening stock is recorded as an <em>incoming transaction</em> automatically.
      Later, use the <strong>Edit / Adjust Stock</strong> option to add or reduce quantities.
    </div>

    <div class="form-actions">
      <button type="submit" name="action" value="save" class="btn btn-primary">💾 Save Item</button>
      <button type="submit" name="action" value="save_add" class="btn btn-outline">💾 Save & Add Another</button>
      <a href="<?= BASE_URL ?>/inventory/index.php" class="btn btn-outline">Cancel</a>
    </div>

  </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>