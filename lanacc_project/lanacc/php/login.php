<?php
// php/login.php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) { redirect('/pages/dashboard.php'); }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $user = DB::queryOne(
        "SELECT * FROM app_users WHERE username = ? AND active = 1", [$username]
    );

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['username']  = $user['username'];
        $_SESSION['user_role'] = $user['role'];
        DB::run("UPDATE app_users SET last_login=NOW() WHERE id=?", [$user['id']]);
        redirect('/pages/dashboard.php');
    } else {
        $error = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>LANACC – Login</title>
  <link rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="bg-light">
<div class="container d-flex justify-content-center align-items-center min-vh-100">
  <div class="col-md-4">
    <div class="card shadow-sm border-0">
      <div class="card-body p-5">
        <div class="text-center mb-4">
          <i class="bi bi-bar-chart-fill text-primary" style="font-size:3rem"></i>
          <h2 class="fw-bold text-primary mt-2">LANACC</h2>
          <p class="text-muted">Landco Accounts System</p>
        </div>
        <?php if ($error): ?>
          <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST">
          <div class="mb-3">
            <label class="form-label">Username</label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-person"></i></span>
              <input type="text" name="username" class="form-control"
                     required autofocus value="<?= htmlspecialchars($_POST['username']??'') ?>">
            </div>
          </div>
          <div class="mb-4">
            <label class="form-label">Password</label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-lock"></i></span>
              <input type="password" name="password" class="form-control" required>
            </div>
          </div>
          <button type="submit" class="btn btn-primary w-100 py-2">
            <i class="bi bi-box-arrow-in-right me-1"></i>Login
          </button>
        </form>
      </div>
    </div>
    <p class="text-center text-muted small mt-3">Default: admin / Admin@2026</p>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
