<?php
// ============================================================
//  expenses/add.php — Add a new expense
// ============================================================
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';

$pageTitle    = 'Add Expense';
$pageSubtitle = 'Record a new kharch entry';

$locations  = db_fetch_all("SELECT id, name FROM locations ORDER BY id");
$categories = db_fetch_all("SELECT id, name FROM expense_categories ORDER BY name");

$errors = [];

// ── Always initialize with defaults ───────────────────────────
$old = [
    'location_id'  => DEFAULT_LOCATION_ID,
    'category_id'  => '',
    'expense_date' => date('Y-m-d'),
    'amount'       => '',
    'description'  => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request token. Please try again.';
    } else {

        $old = [
            'location_id'  => (int)   ($_POST['location_id']  ?? DEFAULT_LOCATION_ID),
            'category_id'  => (int)   ($_POST['category_id']  ?? 0),
            'expense_date' =>          $_POST['expense_date']  ?? date('Y-m-d'),
            'amount'       => (float) ($_POST['amount']        ?? 0),
            'description'  => trim(    $_POST['description']   ?? ''),
        ];

        // Validation
        if (!$old['location_id'])      $errors[] = 'Please select a location.';
        if (!$old['category_id'])      $errors[] = 'Please select a category.';
        if (empty($old['expense_date'])) $errors[] = 'Date is required.';
        if ($old['amount'] <= 0)       $errors[] = 'Amount must be greater than 0.';

        if (empty($errors)) {
            db_insert(
                "INSERT INTO expenses (location_id, category_id, expense_date, amount, description)
                 VALUES (?, ?, ?, ?, ?)",
                [
                    $old['location_id'],
                    $old['category_id'],
                    $old['expense_date'],
                    $old['amount'],
                    $old['description'] ?: null,
                ]
            );

            $_SESSION['flash_success'] = 'Expense added successfully.';
            header('Location: ' . BASE_URL . '/expenses/index.php');
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
        <label for="expense_date">Date <span style="color:red">*</span></label>
        <input type="date" id="expense_date" name="expense_date"
               value="<?= htmlspecialchars($old['expense_date']) ?>" required>
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
        <label for="category_id">Category <span style="color:red">*</span></label>
        <select id="category_id" name="category_id" required>
          <option value="">— Select —</option>
          <?php foreach ($categories as $cat): ?>
            <option value="<?= $cat['id'] ?>"
              <?= $old['category_id'] == $cat['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($cat['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label for="amount">Amount (<?= CURRENCY_SYMBOL ?>) <span style="color:red">*</span></label>
        <input type="number" id="amount" name="amount"
               value="<?= htmlspecialchars((string)$old['amount']) ?>"
               step="0.01" min="0.01" placeholder="0.00" required>
      </div>

      <div class="form-group" style="grid-column: span 2;">
        <label for="description">Description (optional)</label>
        <input type="text" id="description" name="description"
               value="<?= htmlspecialchars($old['description']) ?>"
               placeholder="e.g. Gas cylinder refill, vegetable purchase…">
      </div>

    </div>

    <!-- Quick add: same form, save & add another -->
    <div class="form-actions">
      <button type="submit" name="action" value="save" class="btn btn-primary">💾 Save Expense</button>
      <button type="submit" name="action" value="save_add" class="btn btn-outline">💾 Save & Add Another</button>
      <a href="<?= BASE_URL ?>/expenses/index.php" class="btn btn-outline">Cancel</a>
    </div>

  </form>
</div>

<?php
// Handle "Save & Add Another" redirect back to add page
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)
    && ($_POST['action'] ?? '') === 'save_add') {
    $_SESSION['flash_success'] = 'Expense saved. Add another.';
    header('Location: ' . BASE_URL . '/expenses/add.php');
    exit;
}
?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>