<?php
// php/pages/payroll.php
require_once __DIR__ . '/../includes/header.php';

$year  = (int)($_GET['year']  ?? date('Y'));
$month = (int)($_GET['month'] ?? date('n'));

$table_map = [
  1 => 'Jan26',
  2 => 'Feb26',
];

$table = $table_map[$month] ?? null;
$columns = [];
$rows = [];
$totals = [];
$total_start_idx = -1;
$total_end_idx = -1;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_payroll') {
  require_role('accountant');
  $table = $_POST['table'] ?? null;
  $month = (int)($_POST['month'] ?? $month);
  $year = (int)($_POST['year'] ?? $year);
  if ($table) {
    $columns = DB::query(
      "SELECT col_name, display_name
       FROM payroll_columns
       WHERE table_name=?
       ORDER BY col_index", [$table]
    );
    $col_names = array_map(function($c){ return $c['col_name']; }, $columns);
    $post_rows = $_POST['rows'] ?? [];

    $db = DB::get();
    $db->beginTransaction();
    $db->exec("TRUNCATE TABLE `$table`");

    if (!empty($col_names)) {
      $placeholders = '(' . implode(',', array_fill(0, count($col_names), '?')) . ')';
      $sql = "INSERT INTO `$table` (`" . implode('`,`', $col_names) . "`) VALUES $placeholders";
      $stmt = $db->prepare($sql);
      foreach ($post_rows as $r) {
        $row_vals = [];
        $has_value = false;
        foreach ($col_names as $c) {
          $val = $r[$c] ?? '';
          if ($val !== '' && $val !== null) {
            $has_value = true;
          }
          $row_vals[] = $val;
        }
        if ($has_value) {
          $stmt->execute($row_vals);
        }
      }
    }
    $db->commit();
  }
  redirect('/pages/payroll.php?month=' . $month . '&year=' . $year);
}

if ($table) {
  $columns = DB::query(
    "SELECT col_name, display_name
     FROM payroll_columns
     WHERE table_name=?
     ORDER BY col_index", [$table]
  );

  if (!empty($columns)) {
    $order_col = $columns[0]['col_name'];
    foreach ($columns as $c) {
      if (strtolower(trim($c['display_name'])) === 'no') {
        $order_col = $c['col_name'];
        break;
      }
    }
    $select_cols = implode(', ', array_map(function($c) {
      return '`' . $c['col_name'] . '`';
    }, $columns));
    $rows = DB::query("SELECT $select_cols FROM `$table` ORDER BY CAST(`$order_col` AS UNSIGNED), `$order_col`");

    // Totals from "ALIMENTACAO" to "SALARIO LIQUIDO"
    foreach ($columns as $idx => $c) {
      $label = strtolower(trim($c['display_name'] ?: $c['col_name']));
      if ($label === 'alimentacao') {
        $total_start_idx = $idx;
      }
      if ($label === 'salario liquido') {
        $total_end_idx = $idx;
      }
    }
    if ($total_start_idx >= 0 && $total_end_idx >= $total_start_idx) {
      $totals = array_fill(0, count($columns), 0.0);
      foreach ($rows as $r) {
        for ($i = $total_start_idx; $i <= $total_end_idx; $i++) {
          $col = $columns[$i]['col_name'];
          $raw = $r[$col] ?? '';
          if (is_numeric($raw)) {
            $val = (float)$raw;
          } else {
            $val = (float)str_replace(
              [',', ' '],
              ['.', ''],
              preg_replace('/[^0-9,\\.\\-]/', '', (string)$raw)
            );
          }
          $totals[$i] += $val;
        }
      }
    }
  }
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><i class="bi bi-receipt me-2 text-primary"></i>Payroll</h4>
  <form class="d-flex gap-2">
    <select name="month" class="form-select form-select-sm" style="width:auto">
      <?php for($m=1;$m<=12;$m++): ?>
        <option value="<?=$m?>" <?=$m==$month?'selected':''?>><?=month_name($m)?></option>
      <?php endfor; ?>
    </select>
    <select name="year" class="form-select form-select-sm" style="width:auto">
      <?php for($y=2025;$y<=2027;$y++): ?>
        <option value="<?=$y?>" <?=$y==$year?'selected':''?>><?=$y?></option>
      <?php endfor; ?>
    </select>
    <button class="btn btn-sm btn-outline-primary">Go</button>
  </form>
</div>

<?php if ($table && !empty($columns)): ?>
<div class="d-flex justify-content-end gap-2 mb-2">
  <?php if($user['role']!=='viewer'): ?>
    <button id="toggle-edit" class="btn btn-sm btn-warning">Update and Edit Page</button>
    <button id="add-row" class="btn btn-sm btn-outline-secondary d-none">Add Row</button>
    <button id="save-payroll" class="btn btn-sm btn-success d-none">Save Changes</button>
  <?php endif; ?>
</div>
<div class="card border-0 shadow-sm mb-3">
  <div class="card-header bg-white fw-semibold">Payroll (<?=month_name($month)?> <?=$year?>)</div>
  <div class="table-responsive">
    <form id="payroll-form" method="POST">
      <input type="hidden" name="action" value="save_payroll">
      <input type="hidden" name="table" value="<?=$table?>">
      <input type="hidden" name="month" value="<?=$month?>">
      <input type="hidden" name="year" value="<?=$year?>">
      <table class="table table-hover table-sm mb-0" id="payroll-table">
      <thead class="table-light">
        <tr>
          <?php foreach($columns as $idx => $c): ?>
            <?php
              $label = $c['display_name'] ?: $c['col_name'];
              if ($idx === 7 && strtolower(trim($label)) === 'salario base 2024') {
                $label = 'Retribuicao';
              }
            ?>
            <th><?=htmlspecialchars($label)?></th>
          <?php endforeach; ?>
          <?php if($user['role']!=='viewer'): ?>
            <th class="d-none edit-only">Actions</th>
          <?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach($rows as $ri => $r): ?>
          <tr>
            <?php foreach($columns as $ci => $c): ?>
              <td>
                <span class="cell-text"><?=htmlspecialchars((string)($r[$c['col_name']] ?? ''))?></span>
                <input class="form-control form-control-sm d-none cell-input"
                       name="rows[<?=$ri?>][<?=$c['col_name']?>]"
                       value="<?=htmlspecialchars((string)($r[$c['col_name']] ?? ''))?>">
              </td>
            <?php endforeach; ?>
            <?php if($user['role']!=='viewer'): ?>
              <td class="d-none edit-only">
                <button type="button" class="btn btn-sm btn-outline-danger delete-row">Delete</button>
              </td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
      <?php if (!empty($totals) && $total_start_idx >= 0 && $total_end_idx >= $total_start_idx): ?>
      <tfoot class="table-secondary fw-bold">
        <tr>
          <?php foreach($columns as $idx => $c): ?>
            <?php if ($idx < $total_start_idx || $idx > $total_end_idx): ?>
              <td></td>
            <?php else: ?>
              <td><?=number_format($totals[$idx], 0, '.', ' ')?></td>
            <?php endif; ?>
          <?php endforeach; ?>
          <?php if($user['role']!=='viewer'): ?>
            <td class="d-none edit-only"></td>
          <?php endif; ?>
        </tr>
      </tfoot>
      <?php endif; ?>
    </table>
    </form>
  </div>
</div>
<?php else: ?>
<div class="card border-0 shadow-sm">
  <div class="card-body text-center py-5 text-muted">
    <i class="bi bi-receipt fs-1 d-block mb-2"></i>
    No payroll data found for <?=month_name($month)?> <?=$year?>.
    <br>Run the new payroll importer to load the Chris payroll sheets.
  </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<?php if($table && $user['role']!=='viewer'): ?>
<script>
  const toggleBtn = document.getElementById('toggle-edit');
  const addBtn = document.getElementById('add-row');
  const saveBtn = document.getElementById('save-payroll');
  const table = document.getElementById('payroll-table');
  const form = document.getElementById('payroll-form');

  function setEditMode(on) {
    table.querySelectorAll('.cell-text').forEach(el => el.classList.toggle('d-none', on));
    table.querySelectorAll('.cell-input').forEach(el => el.classList.toggle('d-none', !on));
    table.querySelectorAll('.edit-only').forEach(el => el.classList.toggle('d-none', !on));
    addBtn.classList.toggle('d-none', !on);
    saveBtn.classList.toggle('d-none', !on);
  }

  toggleBtn?.addEventListener('click', (e) => {
    e.preventDefault();
    const isEditing = !addBtn.classList.contains('d-none');
    setEditMode(!isEditing);
  });

  addBtn?.addEventListener('click', (e) => {
    e.preventDefault();
    const tbody = table.querySelector('tbody');
    const rowCount = tbody.querySelectorAll('tr').length;
    const newRow = document.createElement('tr');
    newRow.innerHTML = `
      <?php foreach($columns as $c): ?>
        <td>
          <span class="cell-text d-none"></span>
          <input class="form-control form-control-sm cell-input"
                 name="rows[__IDX__][<?=$c['col_name']?>]" value="">
        </td>
      <?php endforeach; ?>
      <td class="edit-only">
        <button type="button" class="btn btn-sm btn-outline-danger delete-row">Delete</button>
      </td>
    `.replaceAll('__IDX__', rowCount);
    tbody.appendChild(newRow);
  });

  table?.addEventListener('click', (e) => {
    if (e.target && e.target.classList.contains('delete-row')) {
      e.preventDefault();
      e.target.closest('tr')?.remove();
    }
  });

  saveBtn?.addEventListener('click', (e) => {
    e.preventDefault();
    form.submit();
  });
</script>
<?php endif; ?>
