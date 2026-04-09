<?php
// php/includes/auth.php
session_start();

function is_logged_in(): bool {
    return isset($_SESSION['user_id']);
}

function require_login(): void {
    if (!is_logged_in()) {
        header('Location: /login.php');
        exit;
    }
}

function require_role(string $role): void {
    require_login();
    $roles = ['viewer' => 1, 'accountant' => 2, 'admin' => 3];
    $user_level  = $roles[$_SESSION['user_role'] ?? 'viewer'] ?? 0;
    $needed      = $roles[$role] ?? 99;
    if ($user_level < $needed) {
        http_response_code(403);
        die('<h2>403 – Access Denied</h2>');
    }
}

function current_user(): array {
    return [
        'id'       => $_SESSION['user_id']   ?? 0,
        'username' => $_SESSION['username']  ?? '',
        'role'     => $_SESSION['user_role'] ?? 'viewer',
    ];
}
