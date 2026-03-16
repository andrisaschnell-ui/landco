<?php
// php/pages/employees.php
require_once __DIR__ . '/../includes/header.php';

// ── Handle POST (add / edit / delete) ─────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_role('accountant');
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id      = (int)($_POST['id'] ?? 0);
        $fields  = [
            $_POST['name'],       $_POST['initials'] ?: null,
            $_POST['nib']  ?: null, $_POST['nuit'] ?: null,
            $_POST['role'] ?: null, $_POST['category'] ?: null,
            $_POST['engagement_date'] ?: null,
            (float)($_POST['base_salary_mzn'] ?? 0),
            (float)($_POST['food_allowance_mzn'] ?? 0),
            (int)($_POST['active'] ?? 1),
        ];
        if ($id) {
            DB::run("UPDATE employees SET name=?,initials=?,nib=?,nuit=?,role=?,category=?,
                     engagement_date=?,base_salary_mzn=?,food_allowance_mzn=?,active=?
                     WHERE id=?", [...$fields, $id]);
            flash("Employee updated.");
        } else {
            DB::execute("INSERT INTO employees
                (name,initials,nib,nuit,role,category,engagement_date,
                 base_salary_mzn,food_allowance_mzn,active) VALUES
                (?,?,?,?,?,?,?,?,?,?)", $fields);
            flash("Employee added.");
        }
    } elseif ($action === 'delete') {
        require_role('admin');
        DB::run("UPDATE employees SET active=0 WHERE id=?", [(int)$_POST['id']]);
        flash("Employee deactivated.", 'warning');
    }
    redirect('/pages/employees.php');
}

$employees = DB::query(
    "SELECT * FROM employees ORDER BY active DESC, name ASC"
);
$edit = isset($_GET['edit'])
    ? DB::queryOne("SELECT * FROM employees WHERE id=?", [(int)$_GET['edit']])
    : null;
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><i class="bi bi-person-workspace me-2 text-primary"></i>Employees</h4>
  <a href="?add=1" class="btn btn-primary btn-sm">
    <i class="bi bi-plus-lg me-1"></i>Add Employee
  </a>
</div>

<?php if (isset($_GET['add']) || $edit): ?>
<!-- ── Add / Edit Form ── -->
<div class="card border-0 shadow-sm mb-4">
  <div class="card-header bg-white fw-semibold">
    <?= $edit ? 'Edit Employee: '.$edit['name'] : 'Add New Employee' ?>
  </div>
  <div class="card-body">
    <form method="POST" class="row g-3">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?= $edit['id'] ?? 0 ?>">

      <div class="col-md-4">
        <label class="form-label">Full Name *</label>
        <input type="text" name="name" class="form-control" required
               value="<?= htmlspecialchars($edit['name'] ?? '') ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label">Initials</label>
        <input type="text" name="initials" class="form-control" maxlength="5"
               value="<?= htmlspecialchars($edit['initials'] ?? '') ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label">NIB (BIM account)</label>
        <input type="text" name="nib" class="form-control"
               value="<?= htmlspecialchars($edit['nib'] ?? '') ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label">NUIT (Tax no.)</label>
        <input type="text" name="nuit" class="form-control"
               value="<?= htmlspecialchars($edit['nuit'] ?? '') ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">Role</label>
        <input type="text" name="role" class="form-control"
               value="<?= htmlspecialchars($edit['role'] ?? '') ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">Category</label>
        <input type="text" name="category" class="form-control"
               value="<?= htmlspecialchars($edit['category'] ?? '') ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">Engagement Date</label>
        <input type="date" name="engagement_date" class="form-control"
               value="<?= $edit['engagement_date'] ?? '' ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label">Base Salary (MZN)</label>
        <input type="number" name="base_salary_mzn" class="form-control"
               step="0.01" min="0"
               value="<?= $edit['base_salary_mzn'] ?? '0' ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label">Food Allowance (MZN)</label>
        <input type="number" name="food_allowance_mzn" class="form-control"
               step="0.01" min="0"
               value="<?= $edit['food_allowance_mzn'] ?? '0' ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label">Status</label>
        <select name="active" class="form-select">
          <option value="1" <?= ($edit['active']??1)==1?'selected':''?>>Active</option>
          <option value="0" <?= ($edit['active']??1)==0?'selected':''?>>Inactive</option>
        </select>
      </div>
      <div class="col-12 d-flex gap-2">
        <button type="submit" class="btn btn-primary">
          <i class="bi bi-save me-1"></i>Save
        </button>
        <a href="/pages/employees.php" class="btn btn-secondary">Cancel</a>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- ── Employee Table ── -->
<div class="card border-0 shadow-sm">
  <div class="card-header bg-white d-flex justify-content-between">
    <span class="fw-semibold">All Employees</span>
    <span class="badge bg-secondary"><?= count($employees) ?> total</span>
  </div>
  <div class="table-responsive">
    <table class="table table-hover table-sm align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th>#</th><th>Name</th><th>Role</th><th>NIB</th>
          <th class="text-end">Base Salary</th>
          <th class="text-end">Food Allow</th>
          <th>Status</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($employees as $e): ?>
        <tr class="<?= $e['active'] ? '' : 'table-secondary text-muted' ?>">
          <td><?= $e['id'] ?></td>
          <td><strong><?= htmlspecialchars($e['name']) ?></strong></td>
          <td><?= htmlspecialchars($e['role'] ?? '—') ?></td>
          <td><code><?= htmlspecialchars($e['nib'] ?? '—') ?></code></td>
          <td class="text-end"><?= fmt_mzn((float)$e['base_salary_mzn']) ?></td>
          <td class="text-end"><?= fmt_mzn((float)$e['food_allowance_mzn']) ?></td>
          <td>
            <span class="badge bg-<?= $e['active']?'success':'secondary' ?>">
              <?= $e['active'] ? 'Active' : 'Inactive' ?>
            </span>
          </td>
          <td>
            <a href="?edit=<?= $e['id'] ?>" class="btn btn-xs btn-outline-primary btn-sm py-0">
              <i class="bi bi-pencil"></i>
            </a>
            <?php if($user['role']==='admin' && $e['active']): ?>
            <form method="POST" class="d-inline"
                  onsubmit="return confirm('Deactivate <?=addslashes($e['name'])?>?')">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= $e['id'] ?>">
              <button class="btn btn-xs btn-outline-danger btn-sm py-0" type="submit">
                <i class="bi bi-person-dash"></i>
              </button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
