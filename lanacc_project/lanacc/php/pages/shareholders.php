<?php
// php/pages/shareholders.php
require_once __DIR__ . '/../includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_role('accountant');
    $action = $_POST['action'] ?? '';

    if ($action === 'save_shareholder') {
        $id = (int)($_POST['id'] ?? 0);
        $fields = [trim($_POST['name']), trim($_POST['email']??'')?: null,
                   trim($_POST['phone']??'')?: null, trim($_POST['notes']??'')?: null,
                   (int)($_POST['active']??1)];
        if ($id) {
            DB::run("UPDATE shareholders SET name=?,email=?,phone=?,notes=?,active=? WHERE id=?",
                    [...$fields, $id]);
            flash("Shareholder updated.");
        } else {
            DB::execute("INSERT INTO shareholders (name,email,phone,notes,active) VALUES(?,?,?,?,?)",
                        $fields);
            flash("Shareholder added.");
        }
    } elseif ($action === 'save_tx') {
        // Add manual shareholder transaction
        DB::execute("INSERT INTO shareholder_transactions
            (shareholder_id, property_id, tx_date, month, year,
             description, tx_type, amount_mzn, amount_usd, exchange_rate, is_shared)
            VALUES (?,?,?,?,?,?,?,?,?,?,?)", [
            (int)$_POST['shareholder_id'],
            (int)$_POST['property_id'] ?: null,
            $_POST['tx_date'],
            (int)date('n', strtotime($_POST['tx_date'])),
            (int)date('Y', strtotime($_POST['tx_date'])),
            trim($_POST['description']),
            $_POST['tx_type'],
            (float)$_POST['amount_mzn'],
            (float)($_POST['amount_usd']??0) ?: null,
            (float)($_POST['exchange_rate']??0) ?: null,
            (int)($_POST['is_shared']??0),
        ]);
        flash("Transaction added.");
    } elseif ($action === 'delete_tx') {
        require_role('admin');
        DB::run("DELETE FROM shareholder_transactions WHERE id=?", [(int)$_POST['tx_id']]);
        flash("Transaction deleted.", 'warning');
    }
    redirect('/pages/shareholders.php' . ($_POST['sh_id'] ? '?sh='.$_POST['sh_id'] : ''));
}

$shareholders  = DB::query("SELECT * FROM shareholders ORDER BY name");
$properties    = DB::query("SELECT * FROM properties ORDER BY house_code");
$selected_sh   = isset($_GET['sh']) ? (int)$_GET['sh'] : null;
$year          = (int)($_GET['year']  ?? date('Y'));

$sh_detail = $selected_sh
    ? DB::queryOne("SELECT * FROM shareholders WHERE id=?", [$selected_sh])
    : null;

$sh_transactions = $selected_sh
    ? DB::query(
        "SELECT st.*, p.name AS property_name
         FROM shareholder_transactions st
         LEFT JOIN properties p ON p.id = st.property_id
         WHERE st.shareholder_id=? AND st.year=?
         ORDER BY st.month, st.tx_date",
        [$selected_sh, $year])
    : [];

$sh_balances = $selected_sh
    ? DB::query(
        "SELECT * FROM shareholder_balances WHERE shareholder_id=? AND year=?
         ORDER BY month", [$selected_sh, $year])
    : [];
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><i class="bi bi-person-badge me-2 text-primary"></i>Shareholders</h4>
  <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addShModal">
    <i class="bi bi-plus-lg me-1"></i>Add Shareholder
  </button>
</div>

<div class="row g-3">
  <!-- Sidebar: shareholder list -->
  <div class="col-lg-3">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white fw-semibold">Shareholders</div>
      <div class="list-group list-group-flush">
        <?php foreach($shareholders as $sh): ?>
        <a href="?sh=<?=$sh['id']?>&year=<?=$year?>"
           class="list-group-item list-group-item-action <?= $selected_sh==$sh['id']?'active':'' ?>">
          <div class="d-flex justify-content-between">
            <span><?= htmlspecialchars($sh['name']) ?></span>
            <?php
            $prop = DB::queryOne("SELECT house_code FROM properties WHERE shareholder_id=?", [$sh['id']]);
            if($prop): ?><span class="badge bg-secondary"><?=$prop['house_code']?></span><?php endif; ?>
          </div>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Detail panel -->
  <div class="col-lg-9">
    <?php if ($sh_detail): ?>
    <!-- Balance summary -->
    <div class="row g-2 mb-3">
      <?php
      $tot_inc = array_sum(array_column($sh_balances, 'total_income_mzn'));
      $tot_exp = array_sum(array_column($sh_balances, 'total_expenses_mzn'));
      $prop_detail = DB::queryOne("SELECT * FROM properties WHERE shareholder_id=?", [$sh_detail['id']]);
      ?>
      <div class="col-md-3">
        <div class="card border-0 bg-success bg-opacity-10 text-center p-3">
          <div class="text-muted small">Total Income <?=$year?></div>
          <div class="fw-bold text-success"><?=fmt_mzn($tot_inc)?></div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card border-0 bg-danger bg-opacity-10 text-center p-3">
          <div class="text-muted small">Total Expenses <?=$year?></div>
          <div class="fw-bold text-danger"><?=fmt_mzn($tot_exp)?></div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card border-0 bg-primary bg-opacity-10 text-center p-3">
          <div class="text-muted small">Net Balance</div>
          <div class="fw-bold text-primary"><?=fmt_mzn($tot_inc - $tot_exp)?></div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card border-0 bg-warning bg-opacity-10 text-center p-3">
          <div class="text-muted small">Property</div>
          <div class="fw-bold"><?= $prop_detail ? $prop_detail['name'] . ' (' . $prop_detail['house_code'] . ')' : '—' ?></div>
        </div>
      </div>
    </div>

    <!-- Year selector + add transaction -->
    <div class="d-flex gap-2 mb-3">
      <form class="d-flex gap-2">
        <input type="hidden" name="sh" value="<?=$selected_sh?>">
        <select name="year" class="form-select form-select-sm" style="width:auto">
          <?php for($y=2025;$y<=2027;$y++): ?>
            <option value="<?=$y?>" <?=$y==$year?'selected':''?>><?=$y?></option>
          <?php endfor; ?>
        </select>
        <button class="btn btn-sm btn-outline-primary">Go</button>
      </form>
      <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#addTxModal">
        <i class="bi bi-plus-lg me-1"></i>Add Transaction
      </button>
      <a href="/pages/reports.php?type=shareholder&sh=<?=$selected_sh?>&year=<?=$year?>"
         class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-file-earmark-pdf me-1"></i>Report
      </a>
    </div>

    <!-- Transactions table -->
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white fw-semibold">
        <?= htmlspecialchars($sh_detail['name']) ?> — Transactions <?=$year?>
      </div>
      <div class="table-responsive">
        <table class="table table-hover table-sm mb-0">
          <thead class="table-light">
            <tr>
              <th>Month</th><th>Date</th><th>Property</th><th>Description</th>
              <th>Type</th><th class="text-end">MZN</th><th class="text-end">USD</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach($sh_transactions as $tx):
              $badge = match($tx['tx_type']) {
                'income'  => 'success', 'opening_balance' => 'info',
                'expense' => 'danger',  'drawing' => 'warning',
                default   => 'secondary'
              };
            ?>
            <tr>
              <td><?= month_name((int)$tx['month']) ?></td>
              <td><?= $tx['tx_date'] ? date('d M', strtotime($tx['tx_date'])) : '—' ?></td>
              <td><?= htmlspecialchars($tx['property_name'] ?? '—') ?></td>
              <td><?= htmlspecialchars($tx['description'] ?? '—') ?></td>
              <td><span class="badge bg-<?=$badge?>"><?=$tx['tx_type']?></span></td>
              <td class="text-end"><?= fmt_mzn((float)$tx['amount_mzn']) ?></td>
              <td class="text-end"><?= $tx['amount_usd'] ? fmt_usd((float)$tx['amount_usd']) : '—' ?></td>
              <td>
                <?php if($user['role']==='admin'): ?>
                <form method="POST" class="d-inline"
                      onsubmit="return confirm('Delete this transaction?')">
                  <input type="hidden" name="action"  value="delete_tx">
                  <input type="hidden" name="tx_id"   value="<?=$tx['id']?>">
                  <input type="hidden" name="sh_id"   value="<?=$selected_sh?>">
                  <button class="btn btn-xs btn-outline-danger btn-sm py-0" type="submit">
                    <i class="bi bi-trash"></i>
                  </button>
                </form>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if(empty($sh_transactions)): ?>
              <tr><td colspan="8" class="text-center text-muted py-3">No transactions for <?=$year?></td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <?php else: ?>
    <div class="card border-0 shadow-sm">
      <div class="card-body text-center py-5 text-muted">
        <i class="bi bi-person-badge fs-1 d-block mb-2"></i>
        Select a shareholder from the list
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Add Shareholder Modal -->
<div class="modal fade" id="addShModal" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content">
    <div class="modal-header">
      <h5 class="modal-title">Add Shareholder</h5>
      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="action" value="save_shareholder">
        <div class="mb-3">
          <label class="form-label">Name *</label>
          <input type="text" name="name" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Email</label>
          <input type="email" name="email" class="form-control">
        </div>
        <div class="mb-3">
          <label class="form-label">Phone</label>
          <input type="text" name="phone" class="form-control">
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary">Save</button>
      </div>
    </form>
  </div></div>
</div>

<!-- Add Transaction Modal -->
<?php if($sh_detail): ?>
<div class="modal fade" id="addTxModal" tabindex="-1">
  <div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header">
      <h5 class="modal-title">Add Transaction — <?= htmlspecialchars($sh_detail['name']) ?></h5>
      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <form method="POST">
      <div class="modal-body row g-3">
        <input type="hidden" name="action"         value="save_tx">
        <input type="hidden" name="shareholder_id" value="<?=$selected_sh?>">
        <input type="hidden" name="sh_id"          value="<?=$selected_sh?>">
        <div class="col-md-4">
          <label class="form-label">Date *</label>
          <input type="date" name="tx_date" class="form-control" required
                 value="<?=date('Y-m-d')?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Type *</label>
          <select name="tx_type" class="form-select" required>
            <option value="income">Income</option>
            <option value="expense">Expense</option>
            <option value="drawing">Drawing</option>
            <option value="opening_balance">Opening Balance</option>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">Property</label>
          <select name="property_id" class="form-select">
            <option value="">— None —</option>
            <?php foreach($properties as $pr): ?>
            <option value="<?=$pr['id']?>"><?=$pr['name']?> (<?=$pr['house_code']?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-8">
          <label class="form-label">Description *</label>
          <input type="text" name="description" class="form-control" required>
        </div>
        <div class="col-md-4">
          <label class="form-label">Shared?</label>
          <select name="is_shared" class="form-select">
            <option value="0">No</option>
            <option value="1">Yes</option>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">Amount MZN *</label>
          <input type="number" name="amount_mzn" class="form-control" step="0.01" min="0" required>
        </div>
        <div class="col-md-4">
          <label class="form-label">Amount USD</label>
          <input type="number" name="amount_usd" class="form-control" step="0.01" min="0">
        </div>
        <div class="col-md-4">
          <label class="form-label">Exchange Rate</label>
          <input type="number" name="exchange_rate" class="form-control" step="0.01" min="0">
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary">Save Transaction</button>
      </div>
    </form>
  </div></div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
