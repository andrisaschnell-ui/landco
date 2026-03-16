<?php
// php/pages/dashboard.php
require_once __DIR__ . '/../includes/header.php';

$year  = (int)($_GET['year']  ?? date('Y'));
$month = (int)($_GET['month'] ?? date('n'));

// KPIs
$total_income   = DB::queryOne(
    "SELECT COALESCE(SUM(amount_mzn),0) v FROM income_transactions WHERE year=? AND month=?",
    [$year, $month])['v'] ?? 0;

$total_expenses = DB::queryOne(
    "SELECT COALESCE(SUM(amount_mzn),0) v FROM expense_transactions WHERE year=? AND month=?",
    [$year, $month])['v'] ?? 0;

$total_payroll  = DB::queryOne(
    "SELECT COALESCE(total_gross_mzn,0) v FROM salary_runs WHERE year=? AND month=?",
    [$year, $month])['v'] ?? 0;

$shareholder_count = DB::queryOne("SELECT COUNT(*) c FROM shareholders WHERE active=1")['c'] ?? 0;
$employee_count    = DB::queryOne("SELECT COUNT(*) c FROM employees   WHERE active=1")['c'] ?? 0;

// Monthly income by property (chart data)
$income_by_prop = DB::query(
    "SELECT p.name prop, COALESCE(SUM(it.amount_mzn),0) total
     FROM properties p
     LEFT JOIN income_transactions it ON it.property_id=p.id AND it.year=? AND it.month=?
     GROUP BY p.id", [$year, $month]);

// Last 6 months income trend
$trend = DB::query(
    "SELECT month, year, SUM(amount_mzn) total
     FROM income_transactions
     WHERE year=? AND month <= ?
     GROUP BY year,month ORDER BY year,month DESC LIMIT 6",
    [$year, $month]);

// Recent import log
$imports = DB::query(
    "SELECT filename, importer, import_date, records_loaded, status
     FROM import_log ORDER BY import_date DESC LIMIT 10");
?>

<!-- ── Page header ── -->
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><i class="bi bi-speedometer2 me-2 text-primary"></i>Dashboard</h4>
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
    <button class="btn btn-sm btn-outline-primary" type="submit">Go</button>
  </form>
</div>

<!-- ── KPI Cards ── -->
<div class="row g-3 mb-4">
  <?php
  $kpis = [
    ['Income',    $total_income,        'arrow-up-circle-fill',  'success'],
    ['Expenses',  $total_expenses,      'arrow-down-circle-fill','danger'],
    ['Payroll',   $total_payroll,       'people-fill',           'warning'],
    ['Net',       $total_income - $total_expenses - $total_payroll, 'graph-up', 'primary'],
  ];
  foreach($kpis as [$label,$val,$icon,$color]): ?>
  <div class="col-6 col-xl-3">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="rounded-circle bg-<?=$color?> bg-opacity-10 p-3">
          <i class="bi bi-<?=$icon?> text-<?=$color?> fs-4"></i>
        </div>
        <div>
          <div class="text-muted small"><?=$label?> (<?=month_name($month)?>)</div>
          <div class="fw-bold fs-5"><?=fmt_mzn((float)$val)?></div>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- ── Charts + quick stats ── -->
<div class="row g-3 mb-4">
  <div class="col-lg-5">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header bg-white fw-semibold">Income by Property</div>
      <div class="card-body"><canvas id="propChart" height="220"></canvas></div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header bg-white fw-semibold">6-Month Income Trend</div>
      <div class="card-body"><canvas id="trendChart" height="220"></canvas></div>
    </div>
  </div>
  <div class="col-lg-3">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header bg-white fw-semibold">Overview</div>
      <div class="card-body">
        <ul class="list-group list-group-flush">
          <li class="list-group-item d-flex justify-content-between">
            <span>Shareholders</span><strong><?=$shareholder_count?></strong>
          </li>
          <li class="list-group-item d-flex justify-content-between">
            <span>Employees</span><strong><?=$employee_count?></strong>
          </li>
          <li class="list-group-item d-flex justify-content-between">
            <span>INSS Due</span>
            <strong class="text-danger">
              <?=fmt_mzn((float)DB::queryOne(
                "SELECT COALESCE(total_inss_employee+total_inss_employer,0) v
                 FROM salary_runs WHERE year=? AND month=?", [$year,$month])['v']??0)?>
            </strong>
          </li>
          <li class="list-group-item d-flex justify-content-between">
            <span>IRPS Due</span>
            <strong class="text-danger">
              <?=fmt_mzn((float)DB::queryOne(
                "SELECT COALESCE(total_irps,0) v FROM salary_runs WHERE year=? AND month=?",
                [$year,$month])['v']??0)?>
            </strong>
          </li>
        </ul>
        <div class="mt-3 d-grid gap-2">
          <a href="/pages/upload.php"  class="btn btn-sm btn-primary">
            <i class="bi bi-cloud-upload me-1"></i>Upload Files
          </a>
          <a href="/pages/reports.php" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-file-earmark-pdf me-1"></i>Reports
          </a>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ── Recent imports ── -->
<div class="card border-0 shadow-sm">
  <div class="card-header bg-white fw-semibold">Recent File Imports</div>
  <div class="table-responsive">
    <table class="table table-hover table-sm mb-0">
      <thead class="table-light">
        <tr><th>File</th><th>Importer</th><th>Date</th><th>Loaded</th><th>Status</th></tr>
      </thead>
      <tbody>
        <?php foreach($imports as $i): ?>
        <tr>
          <td class="text-truncate" style="max-width:200px"><?=htmlspecialchars($i['filename'])?></td>
          <td><?=htmlspecialchars($i['importer'])?></td>
          <td><?=date('d M H:i', strtotime($i['import_date']))?></td>
          <td><?=$i['records_loaded']?></td>
          <td><span class="badge bg-<?=$i['status']==='success'?'success':($i['status']==='partial'?'warning':'danger')?>">
            <?=$i['status']?></span></td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($imports)): ?>
          <tr><td colspan="5" class="text-center text-muted py-3">No imports yet</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
const propData = <?= json_encode(array_values($income_by_prop)) ?>;
new Chart(document.getElementById('propChart'), {
  type: 'doughnut',
  data: {
    labels: propData.map(r => r.prop),
    datasets: [{ data: propData.map(r => parseFloat(r.total)),
      backgroundColor: ['#0d6efd','#198754','#ffc107','#dc3545'] }]
  },
  options: { plugins: { legend: { position: 'bottom' } } }
});

const tData = <?= json_encode(array_reverse($trend)) ?>;
new Chart(document.getElementById('trendChart'), {
  type: 'line',
  data: {
    labels: tData.map(r => `${r.month}/${r.year}`),
    datasets: [{ label:'Income MZN', data: tData.map(r => parseFloat(r.total)),
      fill: true, tension: 0.3,
      borderColor: '#0d6efd', backgroundColor: 'rgba(13,110,253,0.1)' }]
  },
  options: { plugins:{ legend:{display:false} }, scales:{ y:{ beginAtZero:false } } }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
