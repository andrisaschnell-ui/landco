<?php
// php/pages/payroll.php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/payroll_sheet.php';

$year  = (int)($_GET['year']  ?? date('Y'));
$month = (int)($_GET['month'] ?? date('n'));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_payroll') {
    require_role('accountant');
    $month = (int)($_POST['month'] ?? $month);
    $year = (int)($_POST['year'] ?? $year);
    $post_rows = $_POST['rows'] ?? [];
    payroll_save_rows($month, $year, $post_rows);
    flash('Payroll saved and recalculated.', 'success');
    redirect('/pages/payroll.php?month=' . $month . '&year=' . $year);
}

$bdo_dir = '/data/speadsheets';
$bdo_files = payroll_list_bdo_files($bdo_dir);
$preview_changes = [];
$preview_file = '';
$preview_month = $month;
$preview_year = $year;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'import_admin') {
    require_role('accountant');
    $month = (int)($_POST['month'] ?? $month);
    $year = (int)($_POST['year'] ?? $year);
    $file_key = $_POST['bdo_file'] ?? '';
    $file_path = '';
    foreach ($bdo_files as $f) {
        if ($f['label'] === $file_key) {
            $file_path = $f['path'];
            if (!empty($f['month'])) { $month = (int)$f['month']; }
            if (!empty($f['year'])) { $year = (int)$f['year']; }
            break;
        }
    }
    if ($file_path) {
        try {
            payroll_import_from_xlsx($file_path, $month, $year);
            flash('Admin data imported from spreadsheet.', 'success');
        } catch (Exception $e) {
            flash('Import failed: ' . $e->getMessage(), 'danger');
        }
    } else {
        flash('Please select a valid spreadsheet.', 'warning');
    }
    redirect('/pages/payroll.php?month=' . $month . '&year=' . $year);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'preview_admin') {
    require_role('accountant');
    $preview_month = (int)($_POST['month'] ?? $month);
    $preview_year = (int)($_POST['year'] ?? $year);
    $file_key = $_POST['bdo_file'] ?? '';
    $file_path = '';
    foreach ($bdo_files as $f) {
        if ($f['label'] === $file_key) {
            $file_path = $f['path'];
            $preview_file = $f['label'];
            if (!empty($f['month'])) { $preview_month = (int)$f['month']; }
            if (!empty($f['year'])) { $preview_year = (int)$f['year']; }
            break;
        }
    }
    if ($file_path) {
        try {
            $incoming = payroll_parse_xlsx_admin_rows($file_path);
            $current_rows = payroll_load_rows($preview_month, $preview_year);
            $admin_keys = payroll_admin_keys();
            foreach (payroll_data_rows() as $row_no) {
                foreach ($admin_keys as $key) {
                    $old = $current_rows[$row_no][$key] ?? '';
                    $new = $incoming[$row_no][$key] ?? '';
                    if (in_array($key, ['house', 'name', 'role'], true)) {
                        $old_cmp = trim((string)$old);
                        $new_cmp = trim((string)$new);
                    } else {
                        $old_cmp = round((float)payroll_parse_number($old), 4);
                        $new_cmp = round((float)payroll_parse_number($new), 4);
                    }
                    if ($old_cmp !== $new_cmp) {
                        $preview_changes[] = [
                            'row' => $row_no,
                            'field' => $key,
                            'old' => $old,
                            'new' => $new,
                        ];
                    }
                }
            }
        } catch (Exception $e) {
            flash('Preview failed: ' . $e->getMessage(), 'danger');
        }
    } else {
        flash('Please select a valid spreadsheet.', 'warning');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'template_month') {
    require_role('accountant');
    $month = (int)($_POST['month'] ?? $month);
    $year = (int)($_POST['year'] ?? $year);
    payroll_clear_admin_inputs($month, $year);
    flash('New month template created (admin inputs cleared).', 'success');
    redirect('/pages/payroll.php?month=' . $month . '&year=' . $year);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_month') {
    require_role('accountant');
    $month = (int)($_POST['month'] ?? $month);
    $year = (int)($_POST['year'] ?? $year);
    payroll_delete_month($month, $year);
    flash('Payroll data deleted for selected month/year.', 'success');
    redirect('/pages/payroll.php?month=' . $month . '&year=' . $year);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'clear_row') {
    require_role('accountant');
    $month = (int)($_POST['month'] ?? $month);
    $year = (int)($_POST['year'] ?? $year);
    $row_no = (int)($_POST['row_no'] ?? 0);
    payroll_clear_row($month, $year, $row_no);
    flash('Row cleared (NO kept).', 'success');
    redirect('/pages/payroll.php?month=' . $month . '&year=' . $year);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_row_shift') {
    require_role('accountant');
    $month = (int)($_POST['month'] ?? $month);
    $year = (int)($_POST['year'] ?? $year);
    $row_no = (int)($_POST['row_no'] ?? 0);
    payroll_delete_row_shift($month, $year, $row_no);
    flash('Row deleted and rows shifted up.', 'success');
    redirect('/pages/payroll.php?month=' . $month . '&year=' . $year);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_row') {
    require_role('accountant');
    $month = (int)($_POST['month'] ?? $month);
    $year = (int)($_POST['year'] ?? $year);
    $before = payroll_load_rows($month, $year);
    $after = payroll_add_row($month, $year);
    if ($before === $after) {
        flash('No empty row available. Clear or delete a row first.', 'warning');
    } else {
        flash('New row added at the bottom.', 'success');
    }
    redirect('/pages/payroll.php?month=' . $month . '&year=' . $year);
}

$columns = payroll_columns();
$rows = payroll_load_rows($month, $year);
$data_row_nos = payroll_data_rows();
$summary_row_nos = array_filter(array_keys($rows), function($r) use ($data_row_nos) {
    return !in_array((int)$r, $data_row_nos, true);
});
$data_entries = [];
foreach ($data_row_nos as $rno) {
    $r = $rows[$rno];
    $no_val = is_numeric($r['no']) ? (float)$r['no'] : PHP_FLOAT_MAX;
    $data_entries[] = ['row_no' => $rno, 'no' => $no_val];
}
usort($data_entries, function($a, $b) {
    if ($a['no'] === $b['no']) {
        return $a['row_no'] <=> $b['row_no'];
    }
    return $a['no'] <=> $b['no'];
});
$display_row_nos = array_map(function($e) { return $e['row_no']; }, $data_entries);
sort($summary_row_nos);
$display_row_nos = array_merge($display_row_nos, $summary_row_nos);
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
      <?php for($y=2024;$y<=2027;$y++): ?>
        <option value="<?=$y?>" <?=$y==$year?'selected':''?>><?=$y?></option>
      <?php endfor; ?>
    </select>
    <button class="btn btn-sm btn-outline-primary">Go</button>
  </form>
</div>

<div class="d-flex justify-content-between align-items-center mb-2">
  <div class="small text-muted">
    Editable cells: light green. Calculated cells: light blue.
  </div>
  <div class="d-flex gap-2">
    <a class="btn btn-sm btn-outline-secondary"
       href="/pages/payroll_export_excel.php?month=<?=$month?>&year=<?=$year?>">Download Excel</a>
    <?php if($user['role']!=='viewer'): ?>
      <form method="POST" class="d-inline">
        <input type="hidden" name="action" value="add_row">
        <input type="hidden" name="month" value="<?=$month?>">
        <input type="hidden" name="year" value="<?=$year?>">
        <button class="btn btn-sm btn-outline-primary">Add Row</button>
      </form>
      <button form="payroll-form" class="btn btn-sm btn-success">Save Changes</button>
    <?php endif; ?>
  </div>
</div>

<?php if($user['role']!=='viewer'): ?>
<div class="card border-0 shadow-sm mb-3">
  <div class="card-header bg-white fw-semibold">Admin Data Import</div>
  <div class="card-body">
    <form class="row g-2 align-items-end" method="POST">
      <input type="hidden" name="action" value="preview_admin">
      <input type="hidden" name="month" value="<?=$month?>">
      <input type="hidden" name="year" value="<?=$year?>">
      <div class="col-md-6">
        <label class="form-label">BDO Spreadsheet</label>
        <select name="bdo_file" class="form-select" required>
          <option value="">Select a BDO spreadsheet...</option>
          <?php foreach($bdo_files as $f): ?>
            <option value="<?=htmlspecialchars($f['label'])?>"
                    data-month="<?=htmlspecialchars((string)($f['month'] ?? ''))?>"
                    data-year="<?=htmlspecialchars((string)($f['year'] ?? ''))?>">
              <?=htmlspecialchars($f['label'])?>
            </option>
          <?php endforeach; ?>
        </select>
        <div class="form-text">Only files containing “BDO” are listed from <?=$bdo_dir?>.</div>
      </div>
      <div class="col-md-3">
        <button class="btn btn-primary w-100">Preview Import</button>
      </div>
    </form>
    <hr>
    <form class="row g-2 align-items-end" method="POST" data-confirm="This will clear all admin inputs for the selected month/year. Continue?">
      <input type="hidden" name="action" value="template_month">
      <input type="hidden" name="month" value="<?=$month?>">
      <input type="hidden" name="year" value="<?=$year?>">
      <div class="col-md-6">
        <label class="form-label">Start New Month From Template</label>
        <div class="form-text">Clears all admin-input cells for the selected month/year so you can start fresh.</div>
      </div>
      <div class="col-md-3">
        <button class="btn btn-outline-secondary w-100">Create Template Month</button>
      </div>
    </form>
    <hr>
    <form class="row g-2 align-items-end" method="POST" data-confirm="Delete all payroll data for this month/year? This cannot be undone.">
      <input type="hidden" name="action" value="delete_month">
      <input type="hidden" name="month" value="<?=$month?>">
      <input type="hidden" name="year" value="<?=$year?>">
      <div class="col-md-6">
        <label class="form-label">Delete Month Data</label>
        <div class="form-text">Removes all payroll rows for the selected month/year.</div>
      </div>
      <div class="col-md-3">
        <button class="btn btn-outline-danger w-100">Delete Month Data</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php if (!empty($preview_file)): ?>
<div class="card border-0 shadow-sm mb-3">
  <div class="card-header bg-white fw-semibold">Import Preview</div>
  <div class="card-body">
    <div class="small text-muted mb-2">
      File: <?=htmlspecialchars($preview_file)?> • Target period: <?=month_name($preview_month)?> <?=$preview_year?>
    </div>
    <?php if (empty($preview_changes)): ?>
      <div class="alert alert-success mb-0">No changes detected.</div>
    <?php else: ?>
      <div class="table-responsive mb-3" style="max-height: 280px; overflow-y: auto;">
        <table class="table table-sm table-hover mb-0">
          <thead class="table-light sticky-top">
            <tr><th>Row</th><th>Field</th><th>Current</th><th>New</th></tr>
          </thead>
          <tbody>
            <?php foreach($preview_changes as $ch): ?>
              <tr>
                <td><?=$ch['row']?></td>
                <td><?=htmlspecialchars($ch['field'])?></td>
                <td><?=htmlspecialchars((string)$ch['old'])?></td>
                <td><?=htmlspecialchars((string)$ch['new'])?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <form method="POST" data-confirm="Apply this import and overwrite admin inputs for the target period?">
        <input type="hidden" name="action" value="import_admin">
        <input type="hidden" name="bdo_file" value="<?=htmlspecialchars($preview_file)?>">
        <input type="hidden" name="month" value="<?=$preview_month?>">
        <input type="hidden" name="year" value="<?=$preview_year?>">
        <button class="btn btn-success">Apply Import</button>
      </form>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<div class="card border-0 shadow-sm mb-3">
  <div class="card-header bg-white fw-semibold">Payroll (<?=month_name($month)?> <?=$year?>)</div>
  <div class="table-responsive">
    <form id="payroll-form" method="POST">
      <input type="hidden" name="action" value="save_payroll">
      <input type="hidden" name="month" value="<?=$month?>">
      <input type="hidden" name="year" value="<?=$year?>">
      <table class="table table-hover table-sm mb-0 payroll-grid">
        <thead class="table-light">
          <tr>
            <?php foreach($columns as $c): ?>
              <th><?=htmlspecialchars($c['label'])?></th>
            <?php endforeach; ?>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($display_row_nos as $row_no): ?>
            <?php $r = $rows[$row_no]; ?>
            <?php $is_summary = payroll_is_summary_row((int)$row_no); ?>
            <tr class="<?=$is_summary ? 'table-secondary' : ''?>">
              <?php foreach($columns as $c): ?>
                <?php
                  $key = $c['key'];
                  $val = $r[$key] ?? '';
                  $is_editable = $c['editable'] && !$is_summary && $user['role']!=='viewer';
                  $display = $c['type'] === 'number'
                    ? (is_numeric($val) ? number_format((float)$val, 2, '.', '') : $val)
                    : (string)$val;
                  $is_name_col = $key === 'name';
                ?>
                <td class="<?=$is_editable ? 'cell-editable' : 'cell-calculated'?>">
                  <?php if ($is_editable): ?>
                    <?php if ($is_name_col): ?>
                      <button type="button"
                              class="btn btn-link p-0 text-start name-cell"
                              data-row="<?=$row_no?>"
                              data-name="<?=htmlspecialchars((string)$val)?>">
                        <?=htmlspecialchars($display ?: '—')?>
                      </button>
                      <input class="form-control form-control-sm d-none"
                             name="rows[<?=$row_no?>][<?=$key?>]"
                             value="<?=htmlspecialchars((string)$val)?>">
                    <?php else: ?>
                      <input class="form-control form-control-sm"
                             name="rows[<?=$row_no?>][<?=$key?>]"
                             value="<?=htmlspecialchars((string)$val)?>">
                    <?php endif; ?>
                  <?php else: ?>
                    <span><?=htmlspecialchars($display)?></span>
                  <?php endif; ?>
                </td>
              <?php endforeach; ?>
              <td>
                <?php if (!$is_summary && !empty(trim((string)($r['name'] ?? '')))): ?>
                  <a class="btn btn-sm btn-outline-primary"
                     href="/pages/payroll_payslip.php?month=<?=$month?>&year=<?=$year?>&row=<?=$row_no?>">Payslip</a>
                  <form method="POST" class="d-inline" data-confirm="Clear this row (keep NO)?">
                    <input type="hidden" name="action" value="clear_row">
                    <input type="hidden" name="month" value="<?=$month?>">
                    <input type="hidden" name="year" value="<?=$year?>">
                    <input type="hidden" name="row_no" value="<?=$row_no?>">
                    <button class="btn btn-sm btn-outline-warning">Clear Row</button>
                  </form>
                  <form method="POST" class="d-inline" data-confirm="Delete this row and shift rows up?">
                    <input type="hidden" name="action" value="delete_row_shift">
                    <input type="hidden" name="month" value="<?=$month?>">
                    <input type="hidden" name="year" value="<?=$year?>">
                    <input type="hidden" name="row_no" value="<?=$row_no?>">
                    <button class="btn btn-sm btn-outline-danger">Delete & Shift</button>
                  </form>
                <?php else: ?>
                  <span class="text-muted small">—</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<div class="modal fade" id="nameModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Edit Name</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <label class="form-label">Name</label>
        <input type="text" class="form-control" id="nameModalInput" />
        <input type="hidden" id="nameModalRow" />
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="nameModalSave">Save</button>
      </div>
    </div>
  </div>
</div>

<div class="zoom-menu shadow-sm">
  <div class="fw-semibold small mb-1">Zoom</div>
  <div class="d-flex gap-1">
    <button type="button" class="btn btn-sm btn-outline-secondary" id="zoomOut">-</button>
    <button type="button" class="btn btn-sm btn-outline-secondary" id="zoomReset">100%</button>
    <button type="button" class="btn btn-sm btn-outline-secondary" id="zoomIn">+</button>
  </div>
</div>

<script>
  document.querySelectorAll('form[data-confirm]').forEach(form => {
    form.addEventListener('submit', (e) => {
      const msg = form.getAttribute('data-confirm');
      if (msg && !confirm(msg)) {
        e.preventDefault();
      }
    });
  });

  const bdoSelect = document.querySelector('select[name="bdo_file"]');
  const monthSelect = document.querySelector('select[name="month"]');
  const yearSelect = document.querySelector('select[name="year"]');
  if (bdoSelect && monthSelect && yearSelect) {
    bdoSelect.addEventListener('change', () => {
      const opt = bdoSelect.options[bdoSelect.selectedIndex];
      const m = opt?.dataset?.month;
      const y = opt?.dataset?.year;
      if (m) monthSelect.value = m;
      if (y) yearSelect.value = y;
    });
  }

  const nameModalEl = document.getElementById('nameModal');
  const nameModal = nameModalEl ? new bootstrap.Modal(nameModalEl) : null;
  const nameInput = document.getElementById('nameModalInput');
  const nameRow = document.getElementById('nameModalRow');
  document.querySelectorAll('.name-cell').forEach(btn => {
    btn.addEventListener('click', () => {
      if (!nameModal) return;
      nameRow.value = btn.dataset.row || '';
      nameInput.value = btn.dataset.name || '';
      nameModal.show();
    });
  });
  document.getElementById('nameModalSave')?.addEventListener('click', () => {
    const row = nameRow.value;
    const val = nameInput.value;
    if (!row) return;
    const input = document.querySelector(`input[name="rows[${row}][name]"]`);
    const btn = document.querySelector(`.name-cell[data-row="${row}"]`);
    if (input) input.value = val;
    if (btn) {
      btn.dataset.name = val;
      btn.textContent = val || '—';
    }
    nameModal?.hide();
  });

  let zoomLevel = 1.0;
  const zoomStep = 0.1;
  const applyZoom = () => {
    document.body.style.zoom = zoomLevel.toFixed(2);
    const btn = document.getElementById('zoomReset');
    if (btn) btn.textContent = Math.round(zoomLevel * 100) + '%';
  };
  document.getElementById('zoomIn')?.addEventListener('click', () => {
    zoomLevel = Math.min(1.6, zoomLevel + zoomStep);
    applyZoom();
  });
  document.getElementById('zoomOut')?.addEventListener('click', () => {
    zoomLevel = Math.max(0.6, zoomLevel - zoomStep);
    applyZoom();
  });
  document.getElementById('zoomReset')?.addEventListener('click', () => {
    zoomLevel = 1.0;
    applyZoom();
  });
  applyZoom();
</script>
