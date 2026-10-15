<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
date_default_timezone_set('Europe/Warsaw');

$https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $https,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

set_exception_handler(static function (Throwable $exception): void {
    error_log('Lume Dental application error: ' . $exception->getMessage());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    echo '<!doctype html><html lang="pl"><meta charset="utf-8"><title>Błąd</title>'
        . '<body><h1>Nie udało się wykonać tej operacji.</h1>'
        . '<p>Spróbuj ponownie później. Jeśli problem się powtarza, skontaktuj się z administratorem.</p></body></html>';
});

function db(): PDO
{
    static $connection = null;
    if ($connection instanceof PDO) {
        return $connection;
    }

    $host = getenv('LUME_DB_HOST') ?: '127.0.0.1';
    $database = getenv('LUME_DB_NAME') ?: 'lume_dental';
    $username = getenv('LUME_DB_USER') ?: 'root';
    $password = getenv('LUME_DB_PASSWORD') ?: '';
    $portValue = getenv('LUME_DB_PORT');
    $port = $portValue === false || $portValue === ''
        ? 3306
        : filter_var($portValue, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
    if ($port === false) {
        throw new InvalidArgumentException('LUME_DB_PORT must be between 1 and 65535.');
    }
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $database);

    $connection = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $connection;
}

function app_url(string $path = ''): string
{
    $directory = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    $directory = rtrim($directory, '/');
    $basePath = basename($directory) === 'public'
        ? $directory
        : ($directory === '' ? '' : $directory) . '/public';

    return $basePath . '/' . ltrim($path, '/');
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function post_string(string $key): string
{
    $value = $_POST[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

function csrf_token(): string
{
    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_is_valid(): bool
{
    $submitted = $_POST['csrf_token'] ?? null;
    $stored = $_SESSION['csrf_token'] ?? null;

    return is_string($submitted)
        && is_string($stored)
        && hash_equals($stored, $submitted);
}

function is_post_request(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function take_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);

    return is_array($flash) ? $flash : null;
}

function current_user(): ?array
{
    static $checked = false;
    static $user = null;
    if ($checked) {
        return $user;
    }
    $checked = true;

    $userId = filter_var($_SESSION['user_id'] ?? null, FILTER_VALIDATE_INT);
    if ($userId === false || $userId === null || $userId < 1) {
        return null;
    }

    $statement = db()->prepare(
        'SELECT id, name, surname, email, phone, role, active
         FROM users
         WHERE id = :id
         LIMIT 1'
    );
    $statement->execute(['id' => $userId]);
    $record = $statement->fetch();

    if ($record === false || (int) $record['active'] !== 1) {
        $_SESSION = [];
        session_regenerate_id(true);
        return null;
    }

    $user = $record;
    return $user;
}

function require_login(): array
{
    $user = current_user();
    if ($user === null) {
        set_flash('error', 'Zaloguj się, aby przejść do tej części serwisu.');
        header('Location: ' . app_url('login.php'));
        exit;
    }

    return $user;
}

function require_role(string $role): array
{
    $user = require_login();
    if ($user['role'] !== $role) {
        header('Location: ' . app_url('dashboard.php'));
        exit;
    }

    return $user;
}

function role_label(string $role): string
{
    return match ($role) {
        'admin' => 'Administrator',
        'employee' => 'Pracownik',
        default => 'Klient',
    };
}

function redirect_to_dashboard(): never
{
    header('Location: ' . app_url('dashboard.php'));
    exit;
}
