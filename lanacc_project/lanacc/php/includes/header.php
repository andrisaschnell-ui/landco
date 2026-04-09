<?php
// php/includes/header.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth.php';
require_login();
$user  = current_user();
$flash = get_flash();
$page  = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= APP_NAME ?> – <?= ucfirst($page) ?></title>
  <link rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>

<!-- ── Top Navbar ─────────────────────────────────────── -->
<nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top">
  <div class="container-fluid">
    <a class="navbar-brand fw-bold" href="/pages/dashboard.php">
      <i class="bi bi-bar-chart-fill me-1"></i><?= APP_NAME ?>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
            data-bs-target="#navmain">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navmain">
      <ul class="navbar-nav me-auto">
        <li class="nav-item">
          <a class="nav-link <?= $page==='dashboard'?'active':'' ?>"
             href="/pages/dashboard.php">
            <i class="bi bi-speedometer2"></i> Dashboard
          </a>
        </li>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
            <i class="bi bi-people"></i> People
          </a>
          <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="/pages/shareholders.php">
              <i class="bi bi-person-badge me-1"></i>Shareholders</a></li>
            <li><a class="dropdown-item" href="/pages/employees.php">
              <i class="bi bi-person-workspace me-1"></i>Employees</a></li>
          </ul>
        </li>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
            <i class="bi bi-cash-stack"></i> Finance
          </a>
          <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="/pages/transactions.php">
              <i class="bi bi-arrow-left-right me-1"></i>Transactions</a></li>
            <li><a class="dropdown-item" href="/pages/bank.php">
              <i class="bi bi-bank me-1"></i>Bank</a></li>
            <li><a class="dropdown-item" href="/pages/petty_cash.php">
              <i class="bi bi-wallet2 me-1"></i>Petty Cash</a></li>
          </ul>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $page==='payroll'?'active':'' ?>"
             href="/pages/payroll.php">
            <i class="bi bi-receipt"></i> Payroll
          </a>
        </li>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle <?= $page==='reports'?'active':'' ?>"
             href="#" data-bs-toggle="dropdown">
            <i class="bi bi-file-earmark-bar-graph"></i> Reports
          </a>
          <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="/pages/reports.php?type=shareholder">
              <i class="bi bi-person-lines-fill me-1"></i>Shareholder Statement</a></li>
            <li><a class="dropdown-item" href="/pages/reports.php?type=monthly_ledger">
              <i class="bi bi-journal-text me-1"></i>Monthly Ledger (Auditor)</a></li>
            <li><a class="dropdown-item" href="/pages/reports.php?type=payroll">
              <i class="bi bi-people-fill me-1"></i>Payroll Summary</a></li>
            <li><a class="dropdown-item" href="/pages/reports.php?type=pl">
              <i class="bi bi-graph-up me-1"></i>P&amp;L per Property</a></li>
          </ul>
        </li>
        <?php if ($user['role'] === 'admin'): ?>
        <li class="nav-item">
          <a class="nav-link <?= $page==='upload'?'active':'' ?>"
             href="/pages/upload.php">
            <i class="bi bi-cloud-upload"></i> Upload
          </a>
        </li>
        <?php endif; ?>
      </ul>
      <ul class="navbar-nav">
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
            <i class="bi bi-person-circle me-1"></i><?= htmlspecialchars($user['username']) ?>
          </a>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><span class="dropdown-item-text text-muted small">
              Role: <?= ucfirst($user['role']) ?>
            </span></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="/logout.php">
              <i class="bi bi-box-arrow-right me-1"></i>Logout</a></li>
          </ul>
        </li>
      </ul>
    </div>
  </div>
</nav>

<!-- ── Flash messages ─────────────────────────────────── -->
<?php if ($flash): ?>
<div class="container-fluid mt-2">
  <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : $flash['type'] ?>
              alert-dismissible fade show" role="alert">
    <?= htmlspecialchars($flash['msg']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
</div>
<?php endif; ?>

<div class="container-fluid py-3">
