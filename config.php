<?php
// /var/www/html/calibraciones/config.php
declare(strict_types=1);

/**
 * Carga variables desde .env (simple)
 */
date_default_timezone_set('America/Mexico_City');

function env(string $key, ?string $default=null): ?string {
    static $vars=null;
    if ($vars === null) {
        $vars = [];
        $path = __DIR__.'/.env';
        if (is_readable($path)) {
            foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#')) continue;
                [$k, $v] = array_pad(explode('=', $line, 2), 2, '');
                $vars[trim($k)] = trim($v);
            }
        }
    }
    return $vars[$key] ?? $default;
}

/**
 * Conexión PDO a MySQL/MariaDB
 */
function pdo(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $host = env('DB_HOST', 'localhost');
    $db   = env('DB_NAME', 'calibraciones');
    $dsn  = "mysql:host={$host};dbname={$db};charset=utf8mb4";
    $user = env('DB_USER', '');
    $pass = env('DB_PASS', '');

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    try {
        $pdo = new PDO($dsn, $user, $pass, $options);
        $pdo->exec("SET time_zone = '-06:00'"); // Adjust DB offset for Mexico City
    } catch (Throwable $e) {
        http_response_code(500);
        exit('DB connection error.');
    }
    return $pdo;
}

/**
 * Sesión segura (llamar al inicio de cada página)
 */
function secure_session_start(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => !empty($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

/**
 * CSRF helpers
 */
function csrf_token(): string {
    secure_session_start();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}
function csrf_validate(string $token): bool {
    secure_session_start();
    return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
}

/**
 * Guards de autenticación/rol
 */
function require_auth(string|array|null $role=null): void {
    secure_session_start();
    if (empty($_SESSION['username'])) {
        header('Location: login.php'); exit();
    }
    if ($role !== null) {
        $myRole = $_SESSION['role'] ?? null;
        if (is_array($role)) {
            if (!in_array($myRole, $role, true)) {
                http_response_code(403);
                exit('Forbidden');
            }
        } else {
            if ($myRole !== $role) {
                http_response_code(403);
                exit('Forbidden');
            }
        }
    }
    
    // Bloquear accesos del rol golden_consulta a otras pantallas
    if (($_SESSION['role'] ?? '') === 'golden_consulta') {
        $allowedFiles = ['golden_admin.php', 'golden_export.php', 'golden_inventory_print.php', 'golden_history.php', 'logout.php'];
        $currentFile = basename($_SERVER['PHP_SELF']);
        if (!in_array($currentFile, $allowedFiles, true)) {
            header('Location: golden_admin.php');
            exit();
        }
    }
}
