<?php
// php/pages/reports.php
require_once __DIR__ . '/../includes/header.php';

$type  = $_GET['type']  ?? 'monthly_ledger';
$year  = (int)($_GET['year']  ?? date('Y'));
$month = (int)($_GET['month'] ?? date('n'));
$sh_id = (int)($_GET['sh']    ?? 0);
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><i class="bi bi-file-earmark-bar-graph me-2 text-primary"></i>Reports</h4>
  <div class="d-flex gap-2">
    <form class="d-flex gap-2">
      <input type="hidden" name="type" value="<?=htmlspecialchars($type)?>">
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
      <?php if($type==='shareholder'): ?>
      <select name="sh" class="form-select form-select-sm" style="width:auto">
        <option value="0">— All —</option>
        <?php foreach(DB::query("SELECT id,name FROM shareholders") as $s): ?>
        <option value="<?=$s['id']?>" <?=$sh_id==$s['id']?'selected':''?>>
          <?=htmlspecialchars($s['name'])?></option>
        <?php endforeach; ?>
      </select>
      <?php endif; ?>
      <button class="btn btn-sm btn-outline-primary">Go</button>
    </form>
    <button onclick="window.print()" class="btn btn-sm btn-secondary">
      <i class="bi bi-printer me-1"></i>Print
    </button>
  </div>
</div>

<!-- Report type tabs -->
<ul class="nav nav-tabs mb-3">
  <?php $tabs = [
    'monthly_ledger' => 'Monthly Ledger',
    'shareholder'    => 'Shareholder',
    'payroll'        => 'Payroll',
    'pl'             => 'P&L per Property',
  ];
  foreach($tabs as $t => $lbl): ?>
  <li class="nav-item">
    <a class="nav-link <?=$t===$type?'active':''?>"
       href="?type=<?=$t?>&month=<?=$month?>&year=<?=$year?>">
       <?=$lbl?>
    </a>
  </li>
  <?php endforeach; ?>
</ul>

<!-- ── Report Output ─────────────────────────────────────────── -->
<div class="card border-0 shadow-sm report-body">
<?php if ($type === 'monthly_ledger'): ?>
  <!-- ── MONTHLY LEDGER (for Auditor) ─────────────────────── -->
  <?php
  $recons = DB::query(
      "SELECT mr.*, p.name prop_name, p.house_code, s.name sh_name
       FROM monthly_recon mr
       JOIN properties p  ON p.id=mr.property_id
       JOIN shareholders s ON s.id=p.shareholder_id
       WHERE mr.month=? AND mr.year=?
       ORDER BY p.house_code", [$month, $year]);

  $run = DB::queryOne(
      "SELECT * FROM salary_runs WHERE month=? AND year=?", [$month, $year]);

  $shared_exp = DB::query(
      "SELECT ec.name cat, SUM(et.amount_mzn) total
       FROM expense_transactions et
       LEFT JOIN expense_categories ec ON ec.id=et.category_id
       WHERE et.month=? AND et.year=? AND et.is_shared=1
       GROUP BY ec.id ORDER BY total DESC", [$month, $year]);
  ?>
  <div class="card-header bg-white">
    <div class="row align-items-center">
      <div class="col">
        <h5 class="mb-0">LANDCO LDA — Monthly General Ledger</h5>
        <div class="text-muted"><?=month_name($month)?> <?=$year?></div>
      </div>
      <div class="col-auto text-muted small">
        Generated: <?=date('d M Y H:i')?>
      </div>
    </div>
  </div>
  <div class="card-body">

    <!-- Income by property -->
    <h6 class="fw-bold text-primary border-bottom pb-1 mb-2">INCOME BY PROPERTY</h6>
    <table class="table table-sm table-bordered mb-3">
      <thead class="table-primary">
        <tr><th>Property</th><th>Shareholder</th>
            <th class="text-end">Income MZN</th>
            <th class="text-end">Expenses MZN</th>
            <th class="text-end">Net MZN</th></tr>
      </thead>
      <tbody>
        <?php $tot_inc=$tot_exp=0; foreach($recons as $r):
          $tot_inc += $r['total_income_mzn'];
          $tot_exp += $r['total_expenses_mzn'];
        ?>
        <tr>
          <td><?=htmlspecialchars($r['prop_name'])?> (<?=$r['house_code']?>)</td>
          <td><?=htmlspecialchars($r['sh_name'])?></td>
          <td class="text-end"><?=fmt_mzn((float)$r['total_income_mzn'])?></td>
          <td class="text-end"><?=fmt_mzn((float)$r['total_expenses_mzn'])?></td>
          <td class="text-end fw-bold"><?=fmt_mzn((float)$r['total_income_mzn']-(float)$r['total_expenses_mzn'])?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot class="table-secondary fw-bold">
        <tr>
          <td colspan="2">TOTAL</td>
          <td class="text-end"><?=fmt_mzn($tot_inc)?></td>
          <td class="text-end"><?=fmt_mzn($tot_exp)?></td>
          <td class="text-end"><?=fmt_mzn($tot_inc - $tot_exp)?></td>
        </tr>
      </tfoot>
    </table>

    <!-- Payroll summary -->
    <h6 class="fw-bold text-primary border-bottom pb-1 mb-2">PAYROLL SUMMARY</h6>
    <?php if($run): ?>
    <table class="table table-sm table-bordered mb-3">
      <tbody>
        <tr><td>Total Gross Payroll</td><td class="text-end"><?=fmt_mzn((float)$run['total_gross_mzn'])?></td></tr>
        <tr><td>INSS Employee (3%)</td><td class="text-end"><?=fmt_mzn((float)$run['total_inss_employee'])?></td></tr>
        <tr><td>INSS Employer (4%)</td><td class="text-end"><?=fmt_mzn((float)$run['total_inss_employer'])?></td></tr>
        <tr><td>IRPS (Income Tax)</td><td class="text-end"><?=fmt_mzn((float)$run['total_irps'])?></td></tr>
        <tr class="fw-bold table-success">
          <td>Net Payroll Paid</td>
          <td class="text-end"><?=fmt_mzn((float)$run['total_net_mzn'])?></td>
        </tr>
      </tbody>
    </table>
    <?php else: echo '<p class="text-muted">No payroll data.</p>'; endif; ?>

    <!-- Shared expenses -->
    <?php if(!empty($shared_exp)): ?>
    <h6 class="fw-bold text-primary border-bottom pb-1 mb-2">SHARED EXPENSES BREAKDOWN</h6>
    <table class="table table-sm table-bordered mb-3">
      <thead class="table-primary">
        <tr><th>Category</th><th class="text-end">Amount MZN</th></tr>
      </thead>
      <tbody>
        <?php foreach($shared_exp as $se): ?>
        <tr><td><?=htmlspecialchars($se['cat']??'Other')?></td>
            <td class="text-end"><?=fmt_mzn((float)$se['total'])?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>

<?php elseif ($type === 'shareholder'): ?>
  <!-- ── SHAREHOLDER STATEMENT ──────────────────────────────── -->
  <?php
  $where_sh = $sh_id ? "AND s.id = $sh_id" : "";
  $stmts = DB::query(
      "SELECT sb.*, s.name sh_name, p.name prop_name, p.house_code
       FROM shareholder_balances sb
       JOIN shareholders s ON s.id=sb.shareholder_id
       JOIN properties   p ON p.shareholder_id=s.id
       WHERE sb.year=? $where_sh ORDER BY s.name, sb.month",
      [$year]);
  ?>
  <div class="card-header bg-white">
    <h5 class="mb-0">SHAREHOLDER STATEMENT <?=$year?></h5>
  </div>
  <div class="card-body">
    <?php
    $grouped = [];
    foreach($stmts as $r) $grouped[$r['sh_name']][] = $r;
    foreach($grouped as $sh_name => $rows):
      $prop = $rows[0];
    ?>
    <h6 class="fw-bold text-primary mt-3">
      <?=htmlspecialchars($sh_name)?> — <?=$prop['prop_name']?> (<?=$prop['house_code']?>)
    </h6>
    <table class="table table-sm table-bordered mb-2">
      <thead class="table-primary">
        <tr><th>Month</th>
            <th class="text-end">Opening</th>
            <th class="text-end">Income</th>
            <th class="text-end">Expenses</th>
            <th class="text-end">Closing</th></tr>
      </thead>
      <tbody>
        <?php foreach($rows as $r): ?>
        <tr>
          <td><?=month_name((int)$r['month'])?></td>
          <td class="text-end"><?=fmt_mzn((float)$r['opening_balance_mzn'])?></td>
          <td class="text-end text-success"><?=fmt_mzn((float)$r['total_income_mzn'])?></td>
          <td class="text-end text-danger"><?=fmt_mzn((float)$r['total_expenses_mzn'])?></td>
          <td class="text-end fw-bold"><?=fmt_mzn((float)$r['closing_balance_mzn'])?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endforeach; ?>
    <?php if(empty($grouped)) echo '<p class="text-muted">No data.</p>'; ?>
  </div>

<?php elseif ($type === 'payroll'): ?>
  <!-- ── PAYROLL REPORT ───────────────────────────────────── -->
  <?php
  $run   = DB::queryOne("SELECT * FROM salary_runs WHERE month=? AND year=?", [$month,$year]);
  $lines = $run ? DB::query(
      "SELECT sl.*, e.name emp_name, e.nib, e.role
       FROM salary_lines sl JOIN employees e ON e.id=sl.employee_id
       WHERE sl.salary_run_id=? ORDER BY e.name", [$run['id']]) : [];
  ?>
  <div class="card-header bg-white">
    <h5 class="mb-0">PAYROLL REPORT — <?=month_name($month)?> <?=$year?></h5>
  </div>
  <div class="card-body">
    <?php if($run): ?>
    <table class="table table-sm table-bordered">
      <thead class="table-primary">
        <tr>
          <th>#</th><th>Employee</th><th>Role</th><th>NIB</th>
          <th class="text-end">Gross</th>
          <th class="text-end">INSS Emp</th>
          <th class="text-end">IRPS</th>
          <th class="text-end">Net</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($lines as $i => $l): ?>
        <tr>
          <td><?=$i+1?></td>
          <td><?=htmlspecialchars($l['emp_name'])?></td>
          <td><?=htmlspecialchars($l['role']??'—')?></td>
          <td><code><?=$l['nib']??'—'?></code></td>
          <td class="text-end"><?=fmt_mzn((float)$l['gross_salary'])?></td>
          <td class="text-end"><?=fmt_mzn((float)$l['inss_employee'])?></td>
          <td class="text-end"><?=fmt_mzn((float)$l['irps'])?></td>
          <td class="text-end fw-bold"><?=fmt_mzn((float)$l['net_salary'])?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot class="table-secondary fw-bold">
        <tr>
          <td colspan="4">TOTALS</td>
          <td class="text-end"><?=fmt_mzn((float)$run['total_gross_mzn'])?></td>
          <td class="text-end"><?=fmt_mzn((float)$run['total_inss_employee'])?></td>
          <td class="text-end"><?=fmt_mzn((float)$run['total_irps'])?></td>
          <td class="text-end"><?=fmt_mzn((float)$run['total_net_mzn'])?></td>
        </tr>
      </tfoot>
    </table>
    <div class="row mt-2">
      <div class="col-md-4">
        <table class="table table-sm table-bordered">
          <tr><td>INSS Employer (4%)</td><td class="text-end"><?=fmt_mzn((float)$run['total_inss_employer'])?></td></tr>
          <tr><td>INSS Employee (3%)</td><td class="text-end"><?=fmt_mzn((float)$run['total_inss_employee'])?></td></tr>
          <tr class="fw-bold"><td>Total INSS</td><td class="text-end"><?=fmt_mzn((float)$run['total_inss_employee']+(float)$run['total_inss_employer'])?></td></tr>
        </table>
      </div>
    </div>
    <?php else: echo '<p class="text-muted">No payroll run found.</p>'; endif; ?>
  </div>

<?php elseif ($type === 'pl'): ?>
  <!-- ── P&L PER PROPERTY ──────────────────────────────────── -->
  <?php
  $pl_data = DB::query(
      "SELECT * FROM v_monthly_pl WHERE year=? ORDER BY house_code, month",
      [$year]);
  ?>
  <div class="card-header bg-white">
    <h5 class="mb-0">P&amp;L PER PROPERTY — <?=$year?></h5>
  </div>
  <div class="card-body">
    <?php
    $grouped = [];
    foreach($pl_data as $r) $grouped[$r['house_code']][] = $r;
    foreach($grouped as $code => $rows): $prop = $rows[0]; ?>
    <h6 class="fw-bold text-primary mt-2"><?=$prop['property_name']?> (<?=$code?>) — <?=$prop['shareholder']?></h6>
    <table class="table table-sm table-bordered mb-3">
      <thead class="table-primary">
        <tr><th>Month</th>
            <th class="text-end">Opening</th>
            <th class="text-end">Income</th>
            <th class="text-end">Expenses</th>
            <th class="text-end">Net</th>
            <th class="text-end">Closing</th></tr>
      </thead>
      <tbody>
        <?php foreach($rows as $r): ?>
        <tr>
          <td><?=month_name((int)$r['month'])?></td>
          <td class="text-end"><?=fmt_mzn((float)$r['opening_balance_mzn'])?></td>
          <td class="text-end text-success"><?=fmt_mzn((float)$r['total_income_mzn'])?></td>
          <td class="text-end text-danger"><?=fmt_mzn((float)$r['total_expenses_mzn'])?></td>
          <td class="text-end fw-bold <?=$r['net_mzn']>=0?'text-success':'text-danger'?>">
            <?=fmt_mzn((float)$r['net_mzn'])?></td>
          <td class="text-end"><?=fmt_mzn((float)$r['closing_balance_mzn'])?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endforeach; ?>
    <?php if(empty($grouped)) echo '<p class="text-muted">No P&L data for '.$year.'.</p>'; ?>
  </div>
<?php endif; ?>
</div>

<style>
@media print {
  nav, .btn, form, .nav-tabs { display:none!important; }
  .card { border:none!important; box-shadow:none!important; }
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
