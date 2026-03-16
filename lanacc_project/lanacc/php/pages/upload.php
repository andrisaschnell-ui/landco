<?php
// php/pages/upload.php
require_once __DIR__ . '/../includes/header.php';
require_role('accountant');

$allowed_types = [
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/vnd.ms-excel',
];
$allowed_ext = ['xlsx', 'xls'];

// ── Handle file upload POST ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['upload_files'])) {
    $files    = $_FILES['upload_files'];
    $importer = trim($_POST['importer'] ?? 'auto');
    $loaded = $skipped = $errors = 0;

    // Normalise the $_FILES array for multiple files
    $file_list = [];
    if (is_array($files['name'])) {
        for ($i = 0; $i < count($files['name']); $i++) {
            $file_list[] = [
                'name'     => $files['name'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error'    => $files['error'][$i],
                'size'     => $files['size'][$i],
            ];
        }
    } else {
        $file_list[] = $files;
    }

    foreach ($file_list as $file) {
        if ($file['error'] !== UPLOAD_ERR_OK) { $errors++; continue; }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed_ext)) { $skipped++; continue; }

        // Save to uploads/ with timestamp prefix
        $dest_name = date('Ymd_His_') . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file['name']);
        $dest_path = UPLOAD_DIR . $dest_name;

        if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);

        if (!move_uploaded_file($file['tmp_name'], $dest_path)) {
            $errors++;
            continue;
        }

        // Detect importer from filename if auto
        $detected = $importer === 'auto'
            ? _detect_importer($file['name'])
            : $importer;

        // Log the upload
        $rows = _count_xlsx_rows($dest_path);
        DB::execute(
            "INSERT INTO import_log (filename, importer, records_loaded, records_skipped, status, imported_by)
             VALUES (?,?,?,?,'success',?)",
            [$file['name'], $detected, $rows, 0, $_SESSION['username']]
        );

        // Update employees if BIM file
        if ($detected === 'bim') {
            _process_bim_file($dest_path, $file['name']);
        }

        $loaded++;
    }

    $msg = "$loaded file(s) uploaded successfully.";
    if ($skipped) $msg .= " $skipped skipped (wrong type).";
    if ($errors)  $msg .= " $errors errors.";
    flash($msg, $loaded > 0 ? 'success' : 'warning');
    redirect('/pages/upload.php');
}

// ── Helper: auto-detect importer ──────────────────────────────
function _detect_importer(string $filename): string {
    $f = strtolower($filename);
    if (str_contains($f, 'bim salario') || str_contains($f, 'bim salary'))   return 'bim';
    if (str_contains($f, 'salary sheet'))                                     return 'salaries';
    if (str_contains($f, 'month end'))                                        return 'accounts';
    if (str_contains($f, 'petty cash'))                                       return 'petty_cash';
    if (str_contains($f, 'bdo'))                                              return 'bdo';
    if (str_contains($f, 'cohen') || str_contains($f, 'tafy') ||
        str_contains($f, 'aurora')|| str_contains($f, 'kevin'))               return 'shareholders';
    return 'unknown';
}

function _count_xlsx_rows(string $path): int {
    try {
        // Very lightweight row count without full parse
        $rows = 0;
        $zip = new ZipArchive;
        if ($zip->open($path) === TRUE) {
            $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
            $rows = substr_count($xml, '<row ');
            $zip->close();
        }
        return max(0, $rows - 1); // subtract header
    } catch (Exception $e) {
        return 0;
    }
}

function _process_bim_file(string $path, string $orig_name): void {
    // Parse BIM salary file and upsert employees
    try {
        $zip = new ZipArchive;
        if ($zip->open($path) !== TRUE) return;
        $xml_str = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        // Basic XML parse — for full processing use Python run_all.py
        // This just logs it was received
    } catch (Exception $e) {
        // silent
    }
}

// ── Fetch recent uploads ───────────────────────────────────────
$recent = DB::query(
    "SELECT * FROM import_log ORDER BY import_date DESC LIMIT 20"
);
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><i class="bi bi-cloud-upload me-2 text-primary"></i>Upload Excel Files</h4>
</div>

<div class="row g-3">
  <!-- Upload form -->
  <div class="col-lg-5">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white fw-semibold">Upload File(s)</div>
      <div class="card-body">
        <form method="POST" enctype="multipart/form-data" id="uploadForm">

          <div class="mb-3">
            <label class="form-label">Excel File(s) <span class="text-muted">(xlsx)</span></label>
            <input type="file" name="upload_files[]" id="fileInput"
                   class="form-control" accept=".xlsx,.xls" multiple required>
            <div class="form-text">You can select multiple files at once.</div>
          </div>

          <div class="mb-3">
            <label class="form-label">Importer Type</label>
            <select name="importer" class="form-select">
              <option value="auto">Auto-detect from filename</option>
              <option value="salaries">Salary Sheets (Folha de Salarios)</option>
              <option value="bim">BIM Salary Transfers</option>
              <option value="accounts">Month-End Accounts</option>
              <option value="petty_cash">Petty Cash</option>
              <option value="shareholders">Shareholder Files</option>
              <option value="bdo">BDO Bank Control</option>
            </select>
          </div>

          <div id="fileList" class="mb-3 d-none">
            <div class="fw-semibold small text-muted mb-1">Selected files:</div>
            <ul id="fileNames" class="list-group list-group-flush small"></ul>
          </div>

          <button type="submit" class="btn btn-primary w-100">
            <i class="bi bi-upload me-1"></i>Upload & Log Files
          </button>
        </form>

        <hr>
        <div class="alert alert-info small mb-0">
          <i class="bi bi-info-circle me-1"></i>
          After uploading, run the Python ETL script on your Windows machine to
          parse and load the data into MySQL:<br>
          <code>python run_all.py --only salaries</code>
        </div>
      </div>
    </div>
  </div>

  <!-- File type guide -->
  <div class="col-lg-7">
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-header bg-white fw-semibold">File Type Reference</div>
      <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
          <thead class="table-light">
            <tr><th>Filename Pattern</th><th>Importer</th><th>Data Loaded</th></tr>
          </thead>
          <tbody>
            <tr><td><code>01 Salary sheet for Landco.xlsx</code></td><td>salaries</td>
                <td>Employee payroll, INSS, IRPS</td></tr>
            <tr><td><code>01 BIM SALARIOS 2026.xlsx</code></td><td>bim</td>
                <td>BIM bank transfer list</td></tr>
            <tr><td><code>01 JAN MONTH END 2026.xlsx</code></td><td>accounts</td>
                <td>Income, expenses, recon</td></tr>
            <tr><td><code>01 Petty Cash PETTY CASH 2026.xlsx</code></td><td>petty_cash</td>
                <td>Fuel, parks fees, cash</td></tr>
            <tr><td><code>01 COCO COHEN2026.xlsx</code></td><td>shareholders</td>
                <td>Shareholder P&amp;L statement</td></tr>
            <tr><td><code>01 BDO Bank Control 2026.xlsx</code></td><td>bdo</td>
                <td>Bank transactions</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Recent uploads -->
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white fw-semibold">Recent Uploads</div>
      <div class="table-responsive" style="max-height:300px;overflow-y:auto">
        <table class="table table-sm table-hover mb-0">
          <thead class="table-light sticky-top">
            <tr><th>File</th><th>Type</th><th>Date</th><th>Rows</th><th>By</th></tr>
          </thead>
          <tbody>
            <?php foreach($recent as $r): ?>
            <tr>
              <td class="text-truncate" style="max-width:160px">
                <?=htmlspecialchars($r['filename'])?></td>
              <td><span class="badge bg-secondary"><?=$r['importer']?></span></td>
              <td><?=date('d M H:i', strtotime($r['import_date']))?></td>
              <td><?=$r['records_loaded']?></td>
              <td><?=htmlspecialchars($r['imported_by']??'python')?></td>
            </tr>
            <?php endforeach; ?>
            <?php if(empty($recent)): ?>
              <tr><td colspan="5" class="text-center text-muted">No uploads yet</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script>
document.getElementById('fileInput').addEventListener('change', function() {
  const list = document.getElementById('fileList');
  const ul   = document.getElementById('fileNames');
  ul.innerHTML = '';
  if (this.files.length > 0) {
    list.classList.remove('d-none');
    [...this.files].forEach(f => {
      const li = document.createElement('li');
      li.className = 'list-group-item py-1';
      li.innerHTML = `<i class="bi bi-file-earmark-excel text-success me-1"></i>${f.name}
        <span class="text-muted ms-1">(${(f.size/1024).toFixed(0)} KB)</span>`;
      ul.appendChild(li);
    });
  } else {
    list.classList.add('d-none');
  }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
