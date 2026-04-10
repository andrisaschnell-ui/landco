<?php
// php/pages/payroll.php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/payroll_sheet.php';
payroll_ensure_table_exists();
$houses_list = DB::query("SELECT house_code, name FROM properties ORDER BY house_code");

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

$settings = payroll_get_settings();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_salary_settings') {
    require_role('accountant');
    $eff_year = (int)($_POST['effective_year'] ?? $settings['effective_year']);
    $eff_month = (int)($_POST['effective_month'] ?? $settings['effective_month']);
    $pct = (float)($_POST['increase_pct'] ?? $settings['increase_pct']);
    $enabled = isset($_POST['increase_enabled']) && $_POST['increase_enabled'] === '1';
    payroll_save_settings($eff_year, $eff_month, $pct, $enabled);
    flash('Salary increase settings updated.', 'success');
    redirect('/pages/payroll.php?month=' . $month . '&year=' . $year);
}

// ── Management Actions ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && strpos($_POST['action'] ?? '', 'mgmt_') === 0) {
    require_role('admin');
    $act = $_POST['action'];
    if ($act === 'mgmt_add_prop') {
        DB::run("INSERT IGNORE INTO properties (house_code, name, shareholder_id) VALUES (?, ?, 5)", [$_POST['code'], $_POST['name']]);
        flash('Property added.', 'success');
    }
    if ($act === 'mgmt_delete_prop') {
        DB::run("DELETE FROM properties WHERE id=?", [$_POST['id']]);
        flash('Property deleted.', 'info');
    }
    if ($act === 'mgmt_add_role') {
        DB::run("INSERT IGNORE INTO payroll_roles (role_name) VALUES (?)", [$_POST['role_name']]);
        flash('Role added.', 'success');
    }
    if ($act === 'mgmt_delete_role') {
        DB::run("DELETE FROM payroll_roles WHERE id=?", [$_POST['id']]);
        flash('Role deleted.', 'info');
    }
    if ($act === 'mgmt_add_emp') {
        DB::run("INSERT IGNORE INTO employees (name, active) VALUES (?, 1)", [$_POST['emp_name']]);
        flash('Employee added.', 'success');
    }
    if ($act === 'mgmt_delete_emp') {
        // Soft delete or just mark inactive? Let's just mark inactive to preserve historical records
        DB::run("UPDATE employees SET active=0 WHERE id=?", [$_POST['id']]);
        flash('Employee marked inactive.', 'info');
    }
    redirect('/pages/payroll.php?month=' . $month . '&year=' . $year);
}

$columns = payroll_columns();
$rows = payroll_load_rows($month, $year);
$template_preview = payroll_template_rows($month, $year);
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
    <a class="btn btn-sm btn-outline-secondary btn-compact"
       href="/pages/payroll_template_excel.php?month=<?=$month?>&year=<?=$year?>">Download Template</a>
    <button type="button" class="btn btn-sm btn-outline-secondary btn-compact" id="toggleTemplatePreview">Template Preview</button>
    <?php if($user['role']!=='viewer'): ?>
      <form method="POST" class="d-inline">
        <input type="hidden" name="action" value="add_row">
        <input type="hidden" name="month" value="<?=$month?>">
        <input type="hidden" name="year" value="<?=$year?>">
        <button class="btn btn-sm btn-outline-primary btn-compact">Add Row</button>
      </form>
      <button form="payroll-form" class="btn btn-sm btn-success">Save Changes</button>
    <?php endif; ?>
  </div>
</div>

<div class="card border-0 shadow-sm mb-3" id="columnDisplayCard">
  <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center clickable" id="columnDisplayHeader">
    <span>Column Display</span>
    <div class="d-flex gap-2 align-items-center">
      <button type="button" class="btn btn-sm btn-outline-secondary btn-compact" id="toggleColumnDisplay">Hide</button>
      <button type="button" class="btn btn-sm btn-outline-secondary btn-compact" id="showAllColumns">Show All Columns</button>
      <button type="button" class="btn btn-sm btn-outline-secondary btn-compact" id="presetAdmin">Show Admin Only</button>
      <button type="button" class="btn btn-sm btn-outline-secondary btn-compact" id="presetCalc">Show Calculated Only</button>
    </div>
  </div>
  <div class="card-body" id="columnDisplayBody">
    <div class="small text-muted mb-2">Toggle columns after NAME to reduce clutter.</div>
    <div class="d-flex flex-wrap gap-2" id="columnToggles">
      <?php $after_name = false; ?>
      <?php foreach($columns as $ci => $c): ?>
        <?php if ($c['key'] === 'name') { $after_name = true; continue; } ?>
        <?php if ($after_name): ?>
          <div class="form-check form-check-inline">
            <input class="form-check-input column-toggle" type="checkbox" checked data-col="<?=$ci?>" data-type="<?=$c['editable'] ? 'admin' : 'calc'?>">
            <label class="form-check-label"><?=htmlspecialchars($c['label'])?></label>
          </div>
        <?php endif; ?>
      <?php endforeach; ?>
      <div class="form-check form-check-inline">
        <input class="form-check-input column-toggle" type="checkbox" checked data-col="actions" data-type="actions">
        <label class="form-check-label">ACTIONS</label>
      </div>
    </div>
    <div class="mt-3">
      <label class="form-label small text-muted">View Size</label>
      <div class="btn-group btn-group-sm" role="group" aria-label="View size">
        <button type="button" class="btn btn-outline-secondary" id="viewCompact">Compact</button>
        <button type="button" class="btn btn-outline-secondary" id="viewLarge">Large</button>
      </div>
    </div>
  </div>
</div>

<div class="card border-0 shadow-sm mb-3" id="houseFilterCard">
  <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center clickable" id="houseFilterHeader">
    <span>House / Owner Filter</span>
    <button type="button" class="btn btn-sm btn-outline-secondary btn-compact" id="toggleHouseFilter">Hide</button>
  </div>
  <div class="card-body" id="houseFilterBody">
    <div class="small text-muted mb-2">Show only workers belonging to a specific house or communal account.</div>
    <div class="d-flex flex-wrap gap-2">
      <button type="button" class="btn btn-sm btn-outline-primary house-filter-btn active" data-house="all">Show All</button>
      <button type="button" class="btn btn-sm btn-outline-primary house-filter-btn" data-house="H1">H1 - Tafy</button>
      <button type="button" class="btn btn-sm btn-outline-primary house-filter-btn" data-house="H2">H2 - Stead</button>
      <button type="button" class="btn btn-sm btn-outline-primary house-filter-btn" data-house="H3">H3 - Kevin</button>
      <button type="button" class="btn btn-sm btn-outline-primary house-filter-btn" data-house="H4">H4 - Cohen</button>
      <button type="button" class="btn btn-sm btn-outline-primary house-filter-btn" data-house="LC">LC - Communal</button>
    </div>
  </div>
</div>

<div class="card border-0 shadow-sm mb-3" id="dropdownMgmtCard">
  <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center clickable" id="dropdownMgmtHeader">
    <span>Dropdown Menus Management</span>
    <button type="button" class="btn btn-sm btn-outline-secondary btn-compact" id="toggleDropdownMgmt">Show</button>
  </div>
  <div class="card-body d-none" id="dropdownMgmtBody">
    <div class="row g-3">
      <!-- House / Property Mgmt -->
      <div class="col-md-4">
        <h6 class="fw-bold small border-bottom pb-1">Houses (H1-H4, LC)</h6>
        <div class="list-group list-group-flush border mb-2" style="max-height: 200px; overflow-y:auto;">
          <?php foreach($houses_list as $h): ?>
            <div class="list-group-item d-flex justify-content-between align-items-center py-1 px-2 small">
              <span><strong><?=$h['house_code']?></strong>: <?=htmlspecialchars($h['name'])?></span>
              <form method="POST" class="d-inline" data-confirm="Delete property <?=$h['house_code']?>?">
                <input type="hidden" name="action" value="mgmt_delete_prop">
                <input type="hidden" name="id" value="<?=$h['id']?>">
                <button class="btn btn-link p-0 text-danger" title="Delete"><i class="bi bi-trash"></i></button>
              </form>
            </div>
          <?php endforeach; ?>
        </div>
        <form method="POST" class="input-group input-group-sm">
          <input type="hidden" name="action" value="mgmt_add_prop">
          <input type="text" name="code" class="form-control" placeholder="Code (e.g. H5)" required style="max-width:80px;">
          <input type="text" name="name" class="form-control" placeholder="Name" required>
          <button class="btn btn-primary">Add</button>
        </form>
      </div>

      <!-- Roles Mgmt -->
      <div class="col-md-4">
        <h6 class="fw-bold small border-bottom pb-1">Worker Roles</h6>
        <div class="list-group list-group-flush border mb-2" style="max-height: 200px; overflow-y:auto;">
          <?php 
          $db_roles = DB::query("SELECT * FROM payroll_roles ORDER BY role_name");
          foreach($db_roles as $r_node): ?>
            <div class="list-group-item d-flex justify-content-between align-items-center py-1 px-2 small">
              <span><?=htmlspecialchars($r_node['role_name'])?></span>
              <form method="POST" class="d-inline">
                <input type="hidden" name="action" value="mgmt_delete_role">
                <input type="hidden" name="id" value="<?=$r_node['id']?>">
                <button class="btn btn-link p-0 text-danger" title="Delete"><i class="bi bi-trash"></i></button>
              </form>
            </div>
          <?php endforeach; ?>
        </div>
        <form method="POST" class="input-group input-group-sm">
          <input type="hidden" name="action" value="mgmt_add_role">
          <input type="text" name="role_name" class="form-control" placeholder="New Role Name" required>
          <button class="btn btn-primary">Add</button>
        </form>
      </div>

      <!-- Employee Names Mgmt -->
      <div class="col-md-4">
        <h6 class="fw-bold small border-bottom pb-1">Worker Names (Database)</h6>
        <div class="list-group list-group-flush border mb-2" style="max-height: 200px; overflow-y:auto;">
          <?php 
          $db_emps = DB::query("SELECT id, name FROM employees WHERE active=1 ORDER BY name");
          foreach($db_emps as $e_node): ?>
            <div class="list-group-item d-flex justify-content-between align-items-center py-1 px-2 small">
              <span class="text-truncate" style="max-width:180px;"><?=htmlspecialchars($e_node['name'])?></span>
              <form method="POST" class="d-inline">
                <input type="hidden" name="action" value="mgmt_delete_emp">
                <input type="hidden" name="id" value="<?=$e_node['id']?>">
                <button class="btn btn-link p-0 text-danger" title="Delete"><i class="bi bi-trash"></i></button>
              </form>
            </div>
          <?php endforeach; ?>
        </div>
        <form method="POST" class="input-group input-group-sm">
          <input type="hidden" name="action" value="mgmt_add_emp">
          <input type="text" name="emp_name" class="form-control" placeholder="New Worker Name" required>
          <button class="btn btn-primary">Add</button>
        </form>
      </div>
    </div>
  </div>
</div>
  </div>
</div>

<?php if($user['role']!=='viewer'): ?>
<div class="card border-0 shadow-sm mb-3" id="salarySettingsCard">
  <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center clickable" id="salarySettingsHeader">
    <span>Salary Increase Settings</span>
    <button type="button" class="btn btn-sm btn-outline-secondary btn-compact" id="toggleSalarySettings">Hide</button>
  </div>
  <div class="card-body" id="salarySettingsBody">
    <form class="row g-3 align-items-end" method="POST">
      <input type="hidden" name="action" value="save_salary_settings">
      <div class="col-md-3">
        <label class="form-label">Increase %</label>
        <input type="number" step="0.01" min="0" class="form-control"
               name="increase_pct" value="<?=htmlspecialchars((string)$settings['increase_pct'])?>">
      </div>
      <div class="col-md-3">
        <label class="form-label">Start Month</label>
        <select name="effective_month" class="form-select">
          <?php for($m=1;$m<=12;$m++): ?>
            <option value="<?=$m?>" <?=$m==$settings['effective_month']?'selected':''?>><?=month_name($m)?></option>
          <?php endfor; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">Start Year</label>
        <input type="number" class="form-control" name="effective_year"
               value="<?=htmlspecialchars((string)$settings['effective_year'])?>">
      </div>
      <div class="col-md-2">
        <div class="form-check mt-4">
          <input class="form-check-input" type="checkbox" name="increase_enabled" value="1" id="increaseEnabled"
            <?=$settings['enabled'] ? 'checked' : ''?>>
          <label class="form-check-label" for="increaseEnabled">Enable</label>
        </div>
      </div>
      <div class="col-md-1">
        <button class="btn btn-outline-primary btn-compact w-100">Apply</button>
      </div>
    </form>
    <div class="form-text mt-2">
      When enabled and the payroll month/year is on or after the start date, the next-year base salary is calculated
      from the previous year using this percentage.
    </div>
  </div>
</div>
<?php endif; ?>

<?php if($user['role']!=='viewer'): ?>
<div class="card border-0 shadow-sm mb-3" id="adminImportCard">
  <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
    <span>Admin Data Import</span>
    <button type="button" class="btn btn-sm btn-outline-secondary btn-compact" id="toggleAdminImport">Hide</button>
  </div>
  <div class="card-body" id="adminImportBody">
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
        <button class="btn btn-primary w-100 btn-compact">Preview Import</button>
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
        <button class="btn btn-outline-secondary w-100 btn-compact">Create Template Month</button>
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
        <button class="btn btn-outline-danger w-100 btn-compact">Delete Month Data</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<div class="card border-0 shadow-sm mb-3 d-none" id="templatePreviewCard">
  <div class="card-header bg-white fw-semibold">Template Preview</div>
  <div class="card-body">
    <div class="table-responsive" style="max-height: 320px; overflow-y: auto;">
      <table class="table table-sm table-hover mb-0">
        <thead class="table-light sticky-top">
          <tr>
            <?php foreach($columns as $ci => $c): ?>
              <th class="col-<?=$ci?>"><?=htmlspecialchars($c['label'])?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach($display_row_nos as $row_no): ?>
            <?php $r = $template_preview[$row_no]; ?>
            <?php $is_summary = payroll_is_summary_row((int)$row_no); ?>
            <tr class="<?=$is_summary ? 'table-secondary' : ''?>">
              <?php foreach($columns as $ci => $c): ?>
                <?php
                  $key = $c['key'];
                  $val = $r[$key] ?? '';
                  $display = $c['type'] === 'number'
                    ? (is_numeric($val) ? number_format((float)$val, 2, '.', '') : $val)
                    : (string)$val;
                  if ($key === 'no' && is_numeric($val)) {
                    $display = (string)((int)$val);
                  }
                ?>
                <td class="col-<?=$ci?>"><?=htmlspecialchars($display)?></td>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

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
        <button class="btn btn-success btn-compact">Apply Import</button>
      </form>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<div class="card border-0 shadow-sm mb-3">
  <div class="card-header bg-white fw-semibold">Payroll (<?=month_name($month)?> <?=$year?>)</div>
  <div class="table-responsive payroll-table-wrap">
    <form id="payroll-form" method="POST">
      <input type="hidden" name="action" value="save_payroll">
      <input type="hidden" name="month" value="<?=$month?>">
      <input type="hidden" name="year" value="<?=$year?>">
      <table class="table table-hover table-sm mb-0 payroll-grid">
        <thead class="table-light">
          <tr>
            <?php foreach($columns as $ci => $c): ?>
              <th class="col-<?=$ci?>"><?=htmlspecialchars($c['label'])?></th>
            <?php endforeach; ?>
            <th class="col-actions">ACTIONS</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($display_row_nos as $row_no): ?>
            <?php $r = $rows[$row_no]; ?>
            <?php $is_summary = payroll_is_summary_row((int)$row_no); ?>
            <tr class="<?=$is_summary ? 'table-secondary row-summary' : 'row-worker'?>" data-house="<?=htmlspecialchars((string)($r['house'] ?? ''))?>">
              <?php foreach($columns as $ci => $c): ?>
                <?php
                  $key = $c['key'];
                  $val = $r[$key] ?? '';
                  $is_editable = $c['editable'] && !$is_summary && $user['role']!=='viewer';
                  $display = $c['type'] === 'number'
                    ? (is_numeric($val) ? number_format((float)$val, 2, '.', '') : $val)
                    : (string)$val;
                  $is_name_col = $key === 'name';
                  if ($key === 'no' && is_numeric($val)) {
                    $display = (string)((int)$val);
                  }
                ?>
                <td class="col-<?=$ci?> <?=$is_editable ? 'cell-editable' : 'cell-calculated'?>">
                  <?php if ($is_editable): ?>
                    <?php if ($key === 'name'): ?>
                      <button type="button"
                              class="btn btn-link p-0 text-start name-cell"
                              data-row="<?=$row_no?>"
                              data-name="<?=htmlspecialchars((string)$val)?>">
                        <?=htmlspecialchars($display ?: '—')?>
                      </button>
                      <input class="form-control form-control-sm d-none"
                             name="rows[<?=$row_no?>][<?=$key?>]"
                             value="<?=htmlspecialchars((string)$val)?>">
                    <?php elseif ($key === 'house'): ?>
                      <select class="form-select form-select-sm" name="rows[<?=$row_no?>][<?=$key?>]">
                        <option value="LC" <?=(!$val || $val==='LC')?'selected':''?>>LC</option>
                        <?php foreach($houses_list as $h): if($h['house_code']==='LC') continue;?>
                          <option value="<?=$h['house_code']?>" <?=$val==$h['house_code']?'selected':''?>>
                            <?=$h['house_code']?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    <?php elseif ($key === 'role'): ?>
                      <select class="form-select form-select-sm" name="rows[<?=$row_no?>][<?=$key?>]">
                        <option value="">—</option>
                        <?php 
                        $db_roles = DB::query("SELECT role_name FROM payroll_roles ORDER BY role_name");
                        foreach($db_roles as $r_opt_node): $r_opt = $r_opt_node['role_name']; ?>
                          <option value="<?=$r_opt?>" <?=(trim((string)$val)===trim($r_opt))?'selected':''?>><?=$r_opt?></option>
                        <?php endforeach; ?>
                      </select>
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

              <td class="col-actions actions-cell">
                <?php if (!$is_summary && !empty(trim((string)($r['name'] ?? '')))): ?>
                  <div class="actions-stack">
                    <a class="btn btn-sm btn-outline-primary"
                       href="/pages/payroll_payslip.php?month=<?=$month?>&year=<?=$year?>&row=<?=$row_no?>">Payslip</a>
                    <form method="POST" data-confirm="Clear this row (keep NO)?">
                      <input type="hidden" name="action" value="clear_row">
                      <input type="hidden" name="month" value="<?=$month?>">
                      <input type="hidden" name="year" value="<?=$year?>">
                      <input type="hidden" name="row_no" value="<?=$row_no?>">
                      <button class="btn btn-sm btn-outline-warning btn-compact w-100">Clear Row</button>
                    </form>
                    <form method="POST" data-confirm="Delete this row and shift rows up?">
                      <input type="hidden" name="action" value="delete_row_shift">
                      <input type="hidden" name="month" value="<?=$month?>">
                      <input type="hidden" name="year" value="<?=$year?>">
                      <input type="hidden" name="row_no" value="<?=$row_no?>">
                      <button class="btn btn-sm btn-outline-danger btn-compact w-100">Delete & Shift</button>
                    </form>
                  </div>
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

  document.getElementById('toggleTemplatePreview')?.addEventListener('click', () => {
    const card = document.getElementById('templatePreviewCard');
    if (!card) return;
    card.classList.toggle('d-none');
  });

  const applyColumnVisibility = () => {
    document.querySelectorAll('.column-toggle').forEach(cb => {
      const idx = cb.dataset.col;
      const show = cb.checked;
      if (idx === 'actions') {
        document.querySelectorAll('.col-actions').forEach(el => {
          el.classList.toggle('d-none', !show);
        });
      } else {
        document.querySelectorAll(`.col-${idx}`).forEach(el => {
          el.classList.toggle('d-none', !show);
        });
      }
    });

    const visible = document.querySelectorAll('.column-toggle:checked').length;
    const table = document.querySelector('.payroll-grid');
    if (table) {
      table.classList.toggle('grid-wide', visible <= 10);
      table.classList.toggle('grid-medium', visible > 10 && visible <= 16);
      table.classList.toggle('grid-spread', visible <= 12);
    }
    // persist
    const state = {};
    document.querySelectorAll('.column-toggle').forEach(cb => {
      state[cb.dataset.col] = cb.checked;
    });
    localStorage.setItem('payroll_column_visibility', JSON.stringify(state));
  };
  document.querySelectorAll('.column-toggle').forEach(cb => {
    cb.addEventListener('change', applyColumnVisibility);
  });
  document.getElementById('showAllColumns')?.addEventListener('click', () => {
    document.querySelectorAll('.column-toggle').forEach(cb => {
      cb.checked = true;
    });
    applyColumnVisibility();
  });
  applyColumnVisibility();

  document.getElementById('presetAdmin')?.addEventListener('click', () => {
    document.querySelectorAll('.column-toggle').forEach(cb => {
      const type = cb.dataset.type;
      if (type === 'actions') {
        cb.checked = true;
      } else {
        cb.checked = type === 'admin';
      }
    });
    applyColumnVisibility();
  });

  document.getElementById('presetCalc')?.addEventListener('click', () => {
    document.querySelectorAll('.column-toggle').forEach(cb => {
      const type = cb.dataset.type;
      if (type === 'actions') {
        cb.checked = false;
      } else {
        cb.checked = type === 'calc';
      }
    });
    applyColumnVisibility();
  });

  const saved = localStorage.getItem('payroll_column_visibility');
  if (saved) {
    try {
      const state = JSON.parse(saved);
      document.querySelectorAll('.column-toggle').forEach(cb => {
        if (state.hasOwnProperty(cb.dataset.col)) {
          cb.checked = !!state[cb.dataset.col];
        }
      });
      applyColumnVisibility();
    } catch (e) {}
  }

  const setView = (mode) => {
    const table = document.querySelector('.payroll-grid');
    const wrap = document.querySelector('.payroll-table-wrap');
    if (!table) return;
    table.classList.toggle('view-compact', mode === 'compact');
    table.classList.toggle('view-large', mode === 'large');
    wrap?.classList.toggle('view-large', mode === 'large');
    localStorage.setItem('payroll_view_size', mode);
  };
  document.getElementById('viewCompact')?.addEventListener('click', (e) => {
    e.preventDefault();
    setView('compact');
  });
  document.getElementById('viewLarge')?.addEventListener('click', (e) => {
    e.preventDefault();
    setView('large');
  });
  const savedView = localStorage.getItem('payroll_view_size');
  if (savedView) setView(savedView);

  const toggleBlock = (bodyId, btnId, storageKey) => {
    const body = document.getElementById(bodyId);
    const btn = document.getElementById(btnId);
    if (!body || !btn) return;
    const hidden = body.classList.toggle('d-none');
    btn.textContent = hidden ? 'Show' : 'Hide';
    localStorage.setItem(storageKey, hidden ? 'hidden' : 'shown');
  };

  const initBlock = (bodyId, btnId, storageKey) => {
    const body = document.getElementById(bodyId);
    const btn = document.getElementById(btnId);
    if (!body || !btn) return;
    const state = localStorage.getItem(storageKey);
    if (state === 'hidden') {
      body.classList.add('d-none');
      btn.textContent = 'Show';
    }
  };

  document.getElementById('toggleColumnDisplay')?.addEventListener('click', () => {
    toggleBlock('columnDisplayBody', 'toggleColumnDisplay', 'payroll_column_display');
  });
  document.getElementById('columnDisplayHeader')?.addEventListener('click', (e) => {
    const target = e.target;
    if (target && (target.id === 'toggleColumnDisplay' || target.closest('button'))) return;
    toggleBlock('columnDisplayBody', 'toggleColumnDisplay', 'payroll_column_display');
  });
  document.getElementById('toggleAdminImport')?.addEventListener('click', () => {
    toggleBlock('adminImportBody', 'toggleAdminImport', 'payroll_admin_import');
  });
  initBlock('columnDisplayBody', 'toggleColumnDisplay', 'payroll_column_display');
  initBlock('adminImportBody', 'toggleAdminImport', 'payroll_admin_import');

  document.getElementById('toggleSalarySettings')?.addEventListener('click', () => {
    toggleBlock('salarySettingsBody', 'toggleSalarySettings', 'payroll_salary_settings');
  });
  document.getElementById('salarySettingsHeader')?.addEventListener('click', (e) => {
    const target = e.target;
    if (target && (target.id === 'toggleSalarySettings' || target.closest('button'))) return;
    toggleBlock('salarySettingsBody', 'toggleSalarySettings', 'payroll_salary_settings');
  });
  initBlock('salarySettingsBody', 'toggleSalarySettings', 'payroll_salary_settings');

  document.getElementById('toggleHouseFilter')?.addEventListener('click', () => {
    toggleBlock('houseFilterBody', 'toggleHouseFilter', 'payroll_house_filter_block');
  });
  document.getElementById('houseFilterHeader')?.addEventListener('click', (e) => {
    const target = e.target;
    if (target && (target.id === 'toggleHouseFilter' || target.closest('button'))) return;
    toggleBlock('houseFilterBody', 'toggleHouseFilter', 'payroll_house_filter_block');
  });
  initBlock('houseFilterBody', 'toggleHouseFilter', 'payroll_house_filter_block');

  document.getElementById('toggleDropdownMgmt')?.addEventListener('click', () => {
    toggleBlock('dropdownMgmtBody', 'toggleDropdownMgmt', 'payroll_dropdown_mgmt');
  });
  document.getElementById('dropdownMgmtHeader')?.addEventListener('click', (e) => {
    const target = e.target;
    if (target && (target.id === 'toggleDropdownMgmt' || target.closest('button'))) return;
    toggleBlock('dropdownMgmtBody', 'toggleDropdownMgmt', 'payroll_dropdown_mgmt');
  });
  initBlock('dropdownMgmtBody', 'toggleDropdownMgmt', 'payroll_dropdown_mgmt');

  // House Filter Logic
  document.querySelectorAll('.house-filter-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      // Toggle active UI
      document.querySelectorAll('.house-filter-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');

      const house = btn.dataset.house;
      document.querySelectorAll('.payroll-grid tbody tr').forEach(row => {
        if (row.classList.contains('row-summary')) {
           // Always show summaries unless specifically hiding? 
           // For now, let's show summaries at the bottom regardless
           row.classList.remove('d-none');
           return;
        }
        if (house === 'all' || row.dataset.house === house) {
          row.classList.remove('d-none');
        } else {
          row.classList.add('d-none');
        }
      });
    });
  });

</script>
