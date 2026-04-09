<?php
// php/pages/transactions.php
require_once __DIR__ . '/../includes/header.php';

$year    = (int)($_GET['year']  ?? date('Y'));
$month   = (int)($_GET['month'] ?? date('n'));
$prop_id = (int)($_GET['prop']  ?? 0);
$tx_type = $_GET['tx_type'] ?? 'income';

// Handle add/edit/delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_role('accountant');
    $action = $_POST['action'] ?? '';

    if ($action === 'add_income') {
        DB::execute("INSERT INTO income_transactions
            (property_id, tx_date, month, year, description, amount_mzn, amount_usd, exchange_rate)
            VALUES (?,?,?,?,?,?,?,?)", [
            (int)$_POST['property_id'],
            $_POST['tx_date'],
            (int)date('n', strtotime($_POST['tx_date'])),
            (int)date('Y', strtotime($_POST['tx_date'])),
            trim($_POST['description']),
            (float)$_POST['amount_mzn'],
            (float)($_POST['amount_usd']??0) ?: null,
            (float)($_POST['exchange_rate']??0) ?: null,
        ]);
        flash("Income transaction added.");
    } elseif ($action === 'add_expense') {
        DB::execute("INSERT INTO expense_transactions
            (property_id, category_id, tx_date, month, year, description,
             amount_mzn, is_shared, payment_method)
            VALUES (?,?,?,?,?,?,?,?,?)", [
            (int)$_POST['property_id'] ?: null,
            (int)$_POST['category_id'] ?: null,
            $_POST['tx_date'],
            (int)date('n', strtotime($_POST['tx_date'])),
            (int)date('Y', strtotime($_POST['tx_date'])),
            trim($_POST['description']),
            (float)$_POST['amount_mzn'],
            (int)($_POST['is_shared']??0),
            trim($_POST['payment_method']??'bank'),
        ]);
        flash("Expense transaction added.");
    } elseif ($action === 'delete') {
        require_role('admin');
        $tbl = $_POST['tbl'] === 'income' ? 'income_transactions' : 'expense_transactions';
        DB::run("DELETE FROM $tbl WHERE id=?", [(int)$_POST['tx_id']]);
        flash("Transaction deleted.", 'warning');
    }
    redirect("/pages/transactions.php?year=$year&month=$month&prop=$prop_id&tx_type=$tx_type");
}

$properties  = DB::query("SELECT * FROM properties ORDER BY house_code");
$categories  = DB::query("SELECT * FROM expense_categories ORDER BY name");

$income_rows = DB::query(
    "SELECT it.*, p.name prop_name, p.house_code
     FROM income_transactions it
     JOIN properties p ON p.id=it.property_id
     WHERE it.year=? AND it.month=?"
    . ($prop_id ? " AND it.property_id=$prop_id" : "")
    . " ORDER BY it.tx_date, p.house_code",
    [$year, $month]);

$expense_rows = DB::query(
    "SELECT et.*, p.name prop_name, p.house_code, ec.name cat_name
     FROM expense_transactions et
     LEFT JOIN properties p         ON p.id=et.property_id
     LEFT JOIN expense_categories ec ON ec.id=et.category_id
     WHERE et.year=? AND et.month=?"
    . ($prop_id ? " AND et.property_id=$prop_id" : "")
    . " ORDER BY et.tx_date",
    [$year, $month]);
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><i class="bi bi-arrow-left-right me-2 text-primary"></i>Transactions</h4>
  <form class="d-flex gap-2 flex-wrap">
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
    <select name="prop" class="form-select form-select-sm" style="width:auto">
      <option value="0">All Properties</option>
      <?php foreach($properties as $p): ?>
        <option value="<?=$p['id']?>" <?=$prop_id==$p['id']?'selected':''?>>
          <?=$p['house_code']?> – <?=$p['name']?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-sm btn-outline-primary">Filter</button>
  </form>
</div>

<ul class="nav nav-tabs mb-3">
  <li class="nav-item">
    <a class="nav-link <?=$tx_type==='income'?'active':''?>"
       href="?year=<?=$year?>&month=<?=$month?>&prop=<?=$prop_id?>&tx_type=income">
       <i class="bi bi-arrow-up-circle text-success me-1"></i>
       Income (<?=count($income_rows)?>)
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link <?=$tx_type==='expense'?'active':''?>"
       href="?year=<?=$year?>&month=<?=$month?>&prop=<?=$prop_id?>&tx_type=expense">
       <i class="bi bi-arrow-down-circle text-danger me-1"></i>
       Expenses (<?=count($expense_rows)?>)
    </a>
  </li>
</ul>

<?php if ($tx_type === 'income'): ?>

<!-- Add income form -->
<?php if ($user['role'] !== 'viewer'): ?>
<div class="card border-0 shadow-sm mb-3">
  <div class="card-header bg-white fw-semibold">Add Income Transaction</div>
  <div class="card-body">
    <form method="POST" class="row g-2">
      <input type="hidden" name="action" value="add_income">
      <div class="col-md-2">
        <input type="date" name="tx_date" class="form-control form-control-sm"
               value="<?=date('Y-m-d')?>" required>
      </div>
      <div class="col-md-3">
        <select name="property_id" class="form-select form-select-sm" required>
          <?php foreach($properties as $p): ?>
          <option value="<?=$p['id']?>"><?=$p['house_code']?> – <?=$p['name']?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <input type="text" name="description" class="form-control form-control-sm"
               placeholder="Description" required>
      </div>
      <div class="col-md-2">
        <input type="number" name="amount_mzn" class="form-control form-control-sm"
               placeholder="Amount MZN" step="0.01" min="0" required>
      </div>
      <div class="col-md-1">
        <input type="number" name="amount_usd" class="form-control form-control-sm"
               placeholder="USD" step="0.01" min="0">
      </div>
      <div class="col-md-1">
        <button type="submit" class="btn btn-success btn-sm w-100">
          <i class="bi bi-plus-lg"></i>
        </button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
  <div class="card-header bg-white d-flex justify-content-between">
    <span class="fw-semibold">Income — <?=month_name($month)?> <?=$year?></span>
    <span class="fw-bold text-success">
      Total: <?=fmt_mzn(array_sum(array_column($income_rows,'amount_mzn')))?>
    </span>
  </div>
  <div class="table-responsive">
    <table class="table table-hover table-sm mb-0">
      <thead class="table-light">
        <tr><th>Date</th><th>Property</th><th>Description</th>
            <th class="text-end">MZN</th><th class="text-end">USD</th><th></th></tr>
      </thead>
      <tbody>
        <?php foreach($income_rows as $r): ?>
        <tr>
          <td><?=$r['tx_date']?date('d M', strtotime($r['tx_date'])):'—'?></td>
          <td><span class="badge bg-primary"><?=$r['house_code']?></span>
              <?=htmlspecialchars($r['prop_name'])?></td>
          <td><?=htmlspecialchars($r['description']??'—')?></td>
          <td class="text-end"><?=fmt_mzn((float)$r['amount_mzn'])?></td>
          <td class="text-end"><?=$r['amount_usd']?fmt_usd((float)$r['amount_usd']):'—'?></td>
          <td>
            <?php if($user['role']==='admin'): ?>
            <form method="POST" class="d-inline"
                  onsubmit="return confirm('Delete?')">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="tbl"    value="income">
              <input type="hidden" name="tx_id"  value="<?=$r['id']?>">
              <button class="btn btn-xs btn-outline-danger btn-sm py-0" type="submit">
                <i class="bi bi-trash"></i>
              </button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($income_rows)): ?>
          <tr><td colspan="6" class="text-center text-muted py-3">No income records</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php else: /* expenses */ ?>

<!-- Add expense form -->
<?php if ($user['role'] !== 'viewer'): ?>
<div class="card border-0 shadow-sm mb-3">
  <div class="card-header bg-white fw-semibold">Add Expense Transaction</div>
  <div class="card-body">
    <form method="POST" class="row g-2">
      <input type="hidden" name="action" value="add_expense">
      <div class="col-md-2">
        <input type="date" name="tx_date" class="form-control form-control-sm"
               value="<?=date('Y-m-d')?>" required>
      </div>
      <div class="col-md-2">
        <select name="property_id" class="form-select form-select-sm">
          <option value="">— Shared —</option>
          <?php foreach($properties as $p): ?>
          <option value="<?=$p['id']?>"><?=$p['house_code']?> <?=$p['name']?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <select name="category_id" class="form-select form-select-sm">
          <option value="">— Category —</option>
          <?php foreach($categories as $c): ?>
          <option value="<?=$c['id']?>"><?=htmlspecialchars($c['name'])?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <input type="text" name="description" class="form-control form-control-sm"
               placeholder="Description" required>
      </div>
      <div class="col-md-1">
        <input type="number" name="amount_mzn" class="form-control form-control-sm"
               placeholder="MZN" step="0.01" min="0" required>
      </div>
      <div class="col-md-1">
        <select name="payment_method" class="form-select form-select-sm">
          <option value="bank">Bank</option>
          <option value="cash">Cash</option>
        </select>
      </div>
      <div class="col-md-1">
        <div class="form-check mt-1">
          <input class="form-check-input" type="checkbox" name="is_shared" value="1" id="is_shared">
          <label class="form-check-label" for="is_shared">Shared</label>
        </div>
      </div>
      <div class="col-md-1">
        <button type="submit" class="btn btn-danger btn-sm w-100">
          <i class="bi bi-plus-lg"></i>
        </button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
  <div class="card-header bg-white d-flex justify-content-between">
    <span class="fw-semibold">Expenses — <?=month_name($month)?> <?=$year?></span>
    <span class="fw-bold text-danger">
      Total: <?=fmt_mzn(array_sum(array_column($expense_rows,'amount_mzn')))?>
    </span>
  </div>
  <div class="table-responsive">
    <table class="table table-hover table-sm mb-0">
      <thead class="table-light">
        <tr><th>Date</th><th>Property</th><th>Category</th><th>Description</th>
            <th>Method</th><th class="text-end">MZN</th><th>Shared</th><th></th></tr>
      </thead>
      <tbody>
        <?php foreach($expense_rows as $r): ?>
        <tr>
          <td><?=$r['tx_date']?date('d M', strtotime($r['tx_date'])):'—'?></td>
          <td><?=$r['house_code']??"<span class='badge bg-secondary'>Shared</span>"?></td>
          <td><?=htmlspecialchars($r['cat_name']??'—')?></td>
          <td><?=htmlspecialchars($r['description']??'—')?></td>
          <td><span class="badge bg-<?=$r['payment_method']==='cash'?'warning':'info'?>">
            <?=$r['payment_method']??'bank'?></span></td>
          <td class="text-end"><?=fmt_mzn((float)$r['amount_mzn'])?></td>
          <td><?=$r['is_shared']?'<i class="bi bi-check text-success"></i>':''?></td>
          <td>
            <?php if($user['role']==='admin'): ?>
            <form method="POST" class="d-inline" onsubmit="return confirm('Delete?')">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="tbl"    value="expense">
              <input type="hidden" name="tx_id"  value="<?=$r['id']?>">
              <button class="btn btn-xs btn-outline-danger btn-sm py-0" type="submit">
                <i class="bi bi-trash"></i>
              </button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($expense_rows)): ?>
          <tr><td colspan="8" class="text-center text-muted py-3">No expense records</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
