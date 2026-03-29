<?php
// ============================================================
//  masala/vendors.php — Vendor management
//  View, add, delete vendors (Munim Ji / Mandalor etc.)
// ============================================================
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';

$pageTitle    = 'Vendors';
$pageSubtitle = 'Manage raw material suppliers';

$errors = [];

// ── Handle add vendor ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_type']) && $_POST['form_type'] === 'add_vendor') {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request token.';
    } else {
        $name    = trim($_POST['vname']    ?? '');
        $contact = trim($_POST['vcontact'] ?? '');
        $note    = trim($_POST['vnote']    ?? '');

        if ($name === '') {
            $errors[] = 'Vendor name is required.';
        } else {
            $exists = db_value("SELECT COUNT(*) FROM vendors WHERE name = ?", [$name]);
            if ($exists > 0) {
                $errors[] = "Vendor '{$name}' already exists.";
            } else {
                db_insert(
                    "INSERT INTO vendors (name, contact, note) VALUES (?, ?, ?)",
                    [$name, $contact ?: null, $note ?: null]
                );
                $_SESSION['flash_success'] = "Vendor '{$name}' added.";
                header('Location: ' . BASE_URL . '/masala/vendors.php');
                exit;
            }
        }
    }
}

// ── Handle delete vendor ──────────────────────────────────────
if (isset($_GET['del']) && isset($_GET['token'])) {
    $delId = (int)$_GET['del'];
    if ($delId && hash_equals($_SESSION['csrf_token'], $_GET['token'])) {
        // Check for existing purchases
        $hasPurchases = (int) db_value("SELECT COUNT(*) FROM purchases WHERE vendor_id = ?", [$delId]);
        if ($hasPurchases > 0) {
            $_SESSION['flash_error'] = "Cannot delete — this vendor has {$hasPurchases} purchase record(s).";
        } else {
            $v = db_fetch_one("SELECT name FROM vendors WHERE id = ?", [$delId]);
            db_run("DELETE FROM vendors WHERE id = ?", [$delId]);
            $_SESSION['flash_success'] = "Vendor '{$v['name']}' deleted.";
        }
    }
    header('Location: ' . BASE_URL . '/masala/vendors.php');
    exit;
}

// ── All vendors with purchase count ──────────────────────────
$vendors = db_fetch_all(
    "SELECT v.*,
            COUNT(p.id)             AS purchase_count,
            COALESCE(SUM(p.total_amount), 0) AS total_spent,
            COALESCE(SUM(CASE WHEN p.is_paid=0 THEN p.total_amount ELSE 0 END), 0) AS unpaid_amount
       FROM vendors v
       LEFT JOIN purchases p ON p.vendor_id = v.id
      GROUP BY v.id
      ORDER BY total_spent DESC"
);

require_once __DIR__ . '/../includes/header.php';
?>

<?php if (!empty($errors)): ?>
  <div class="alert alert-error">
    <?php foreach ($errors as $e): ?>
      <div>❌ <?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<div style="display:grid; grid-template-columns:1fr 320px; gap:1.25rem; align-items:start;">

  <!-- Vendors table -->
  <div class="card">
    <div class="table-wrap">
      <?php if (empty($vendors)): ?>
        <p style="color:var(--text-muted);">No vendors yet. Add your first vendor →</p>
      <?php else: ?>
        <table>
          <thead>
            <tr>
              <th>#</th>
              <th>Vendor Name</th>
              <th>Contact</th>
              <th>Purchases</th>
              <th>Total Spent</th>
              <th>Unpaid</th>
              <th>Note</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($vendors as $i => $v): ?>
              <tr>
                <td><?= $i + 1 ?></td>
                <td><strong><?= htmlspecialchars($v['name']) ?></strong></td>
                <td style="color:var(--text-muted);"><?= htmlspecialchars($v['contact'] ?? '—') ?></td>
                <td><?= $v['purchase_count'] ?> entries</td>
                <td><strong><?= CURRENCY_SYMBOL ?><?= number_format((float)$v['total_spent'], 2) ?></strong></td>
                <td>
                  <?php if ((float)$v['unpaid_amount'] > 0): ?>
                    <span style="color:var(--error); font-weight:600;">
                      <?= CURRENCY_SYMBOL ?><?= number_format((float)$v['unpaid_amount'], 2) ?>
                    </span>
                  <?php else: ?>
                    <span style="color:var(--success);">✅ Clear</span>
                  <?php endif; ?>
                </td>
                <td style="color:var(--text-muted); font-size:.82rem;">
                  <?= htmlspecialchars($v['note'] ?? '—') ?>
                </td>
                <td>
                  <?php if ((int)$v['purchase_count'] === 0): ?>
                    <a href="?del=<?= $v['id'] ?>&token=<?= $_SESSION['csrf_token'] ?>"
                       class="btn btn-danger btn-sm"
                       onclick="return confirm('Delete vendor \'<?= addslashes($v['name']) ?>\'?')">🗑</a>
                  <?php else: ?>
                    <span style="color:var(--text-muted); font-size:.78rem;">Has records</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>

  <!-- Add vendor form -->
  <div class="card">
    <div class="card-title">➕ Add New Vendor</div>
    <form method="POST" action="">
      <input type="hidden" name="csrf_token"  value="<?= $_SESSION['csrf_token'] ?>">
      <input type="hidden" name="form_type"   value="add_vendor">

      <div class="form-group">
        <label for="vname">Vendor Name <span style="color:red">*</span></label>
        <input type="text" id="vname" name="vname"
               placeholder="e.g. Ramesh Masala Wale"
               required autofocus>
      </div>

      <div class="form-group">
        <label for="vcontact">Phone / Contact</label>
        <input type="text" id="vcontact" name="vcontact"
               placeholder="e.g. 9876543210" maxlength="15">
      </div>

      <div class="form-group">
        <label for="vnote">Note</label>
        <input type="text" id="vnote" name="vnote"
               placeholder="What do they supply?">
      </div>

      <button type="submit" class="btn btn-primary" style="width:100%;">➕ Add Vendor</button>
    </form>
  </div>

</div>

<div style="margin-top:1rem;">
  <a href="<?= BASE_URL ?>/masala/index.php" class="btn btn-outline">← Back to Purchases</a>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>