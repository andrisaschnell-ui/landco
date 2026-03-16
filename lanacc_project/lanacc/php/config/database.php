<?php
// php/config/database.php
define('DB_HOST',     $_ENV['DB_HOST']     ?? getenv('DB_HOST')     ?: 'localhost');
define('DB_PORT',     $_ENV['DB_PORT']     ?? getenv('DB_PORT')     ?: '3306');
define('DB_NAME',     $_ENV['DB_NAME']     ?? getenv('DB_NAME')     ?: 'lanacc');
define('DB_USER',     $_ENV['DB_USER']     ?? getenv('DB_USER')     ?: 'lanacc_user');
define('DB_PASSWORD', $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?: '');
define('APP_SECRET',  $_ENV['APP_SECRET']  ?? getenv('APP_SECRET')  ?: 'changeme');

define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('APP_NAME', 'LANACC');
define('APP_VERSION', '1.0');

class DB {
    private static ?PDO $pdo = null;

    public static function get(): PDO {
        if (self::$pdo === null) {
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT
                 . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            self::$pdo = new PDO($dsn, DB_USER, DB_PASSWORD, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        }
        return self::$pdo;
    }

    public static function query(string $sql, array $params = []): array {
        $stmt = self::get()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function queryOne(string $sql, array $params = []): ?array {
        $stmt = self::get()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function execute(string $sql, array $params = []): int {
        $stmt = self::get()->prepare($sql);
        $stmt->execute($params);
        return (int) self::get()->lastInsertId();
    }

    public static function run(string $sql, array $params = []): int {
        $stmt = self::get()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }
}

// ── Helpers ────────────────────────────────────────────────────
function fmt_mzn(float $v): string {
    return 'MZN ' . number_format($v, 2);
}
function fmt_usd(float $v): string {
    return '$ ' . number_format($v, 2);
}
function month_name(int $m): string {
    return date('F', mktime(0,0,0,$m,1));
}
function redirect(string $url): void {
    header("Location: $url"); exit;
}
function flash(string $msg, string $type = 'success'): void {
    $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
}
function get_flash(): ?array {
    if (isset($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}
