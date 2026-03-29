<?php
// ============================================================
//  employees/add.php — Add OR Edit an employee
//  Add mode:  employees/add.php
//  Edit mode: employees/add.php?edit=ID
// ============================================================
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';

$editId   = (int)($_GET['edit'] ?? $_POST['edit_id'] ?? 0);
$isEdit   = $editId > 0;
$employee = null;

if ($isEdit) {
    $employee = db_fetch_one("SELECT * FROM employees WHERE id = ?", [$editId]);
    if (!$employee) {
        $_SESSION['flash_error'] = 'Employee not found.';
        header('Location: ' . BASE_URL . '/employees/index.php');
        exit;
    }
}

$pageTitle    = $isEdit ? 'Edit Employee' : 'Add Employee';
$pageSubtitle = $isEdit
    ? 'Update details for ' . htmlspecialchars($employee['name'])
    : 'Register a new staff member';

$errors = [];

// ── Default field values ──────────────────────────────────────
$old = $isEdit ? [
    'name'           => $employee['name'],
    'phone'          => $employee['phone'] ?? '',
    'role'           => $employee['role']  ?? '',
    'monthly_salary' => $employee['monthly_salary'],
    'join_date'      => $employee['join_date'] ?? '',
    'is_active'      => $employee['is_active'],
] : [
    'name'           => '',
    'phone'          => '',
    'role'           => '',
    'monthly_salary' => '',
    'join_date'      => date('Y-m-d'),
    'is_active'      => 1,
];

// ── Handle POST ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request token. Please try again.';
    } else {

        $old = [
            'name'           => trim($_POST['name']           ?? ''),
            'phone'          => trim($_POST['phone']          ?? ''),
            'role'           => trim($_POST['role']           ?? ''),
            'monthly_salary' => $_POST['monthly_salary']      ?? '',
            'join_date'      => trim($_POST['join_date']      ?? ''),
            'is_active'      => isset($_POST['is_active']) ? 1 : 0,
        ];

        // Validation
        if ($old['name'] === '')                                $errors[] = 'Employee name is required.';
        if ($old['phone'] !== '' && !preg_match('/^[0-9+\-\s]{7,15}$/', $old['phone']))
                                                               $errors[] = 'Enter a valid phone number.';
        if (!is_numeric($old['monthly_salary']) || (float)$old['monthly_salary'] < 0)
                                                               $errors[] = 'Monthly salary must be 0 or more.';

        if (empty($errors)) {
            if ($isEdit) {
                db_run(
                    "UPDATE employees
                        SET name = ?, phone = ?, role = ?, monthly_salary = ?,
                            join_date = ?, is_active = ?
                      WHERE id = ?",
                    [
                        $old['name'],
                        $old['phone']    ?: null,
                        $old['role']     ?: null,
                        (float)$old['monthly_salary'],
                        $old['join_date'] ?: null,
                        $old['is_active'],
                        $editId,
                    ]
                );
                $_SESSION['flash_success'] = "'{$old['name']}' updated successfully.";
            } else {
                db_insert(
                    "INSERT INTO employees (name, phone, role, monthly_salary, join_date, is_active)
                     VALUES (?, ?, ?, ?, ?, ?)",
                    [
                        $old['name'],
                        $old['phone']    ?: null,
                        $old['role']     ?: null,
                        (float)$old['monthly_salary'],
                        $old['join_date'] ?: null,
                        1,
                    ]
                );
                $_SESSION['flash_success'] = "'{$old['name']}' added successfully.";
            }
            header('Location: ' . BASE_URL . '/employees/index.php');
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
    <?php if ($isEdit): ?>
      <input type="hidden" name="edit_id" value="<?= $editId ?>">
    <?php endif; ?>

    <div class="form-grid">

      <!-- Full Name -->
      <div class="form-group" style="grid-column: span 2;">
        <label for="name">Full Name <span style="color:red">*</span></label>
        <input type="text" id="name" name="name"
               value="<?= htmlspecialchars($old['name']) ?>"
               placeholder="e.g. Ramesh Kumar"
               required autofocus>
      </div>

      <!-- Phone -->
      <div class="form-group">
        <label for="phone">Phone Number</label>
        <input type="text" id="phone" name="phone"
               value="<?= htmlspecialchars($old['phone']) ?>"
               placeholder="e.g. 9876543210"
               maxlength="15">
      </div>

      <!-- Role -->
      <div class="form-group">
        <label for="role">Role / Designation</label>
        <input type="text" id="role" name="role"
               value="<?= htmlspecialchars($old['role']) ?>"
               placeholder="e.g. Cook, Helper, Cashier…"
               list="role_suggestions">
        <datalist id="role_suggestions">
          <option value="Cook">
          <option value="Helper">
          <option value="Cashier">
          <option value="Cleaner">
          <option value="Delivery">
          <option value="Manager">
        </datalist>
      </div>

      <!-- Monthly Salary -->
      <div class="form-group">
        <label for="monthly_salary">Monthly Salary (<?= CURRENCY_SYMBOL ?>) <span style="color:red">*</span></label>
        <input type="number" id="monthly_salary" name="monthly_salary"
               value="<?= htmlspecialchars((string)$old['monthly_salary']) ?>"
               step="0.01" min="0" placeholder="0.00" required>
      </div>

      <!-- Join Date -->
      <div class="form-group">
        <label for="join_date">Joining Date</label>
        <input type="date" id="join_date" name="join_date"
               value="<?= htmlspecialchars($old['join_date']) ?>">
      </div>

      <!-- Active status (edit only) -->
      <?php if ($isEdit): ?>
      <div class="form-group" style="grid-column: span 2;">
        <label style="display:flex; align-items:center; gap:.6rem; cursor:pointer; font-weight:600;">
          <input type="checkbox" name="is_active" value="1"
                 <?= $old['is_active'] ? 'checked' : '' ?>
                 style="width:18px; height:18px; cursor:pointer;">
          Employee is currently active
        </label>
        <small style="color:var(--text-muted); font-size:.78rem; margin-top:.35rem; display:block;">
          Uncheck to mark as inactive (resigned / terminated). Inactive employees won't appear in payroll.
        </small>
      </div>
      <?php endif; ?>

    </div>

    <div class="form-actions">
      <button type="submit" class="btn btn-primary">
        <?= $isEdit ? '💾 Save Changes' : '➕ Add Employee' ?>
      </button>
      <a href="<?= BASE_URL ?>/employees/index.php" class="btn btn-outline">Cancel</a>
    </div>

  </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>