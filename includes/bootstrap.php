<?php
declare(strict_types=1);

// Shared hosting (cPanel) often defaults to an old PHP version – show a clear message instead of a blank page.
if (PHP_VERSION_ID < 80100) {
    http_response_code(500);
    exit('ONE OFFICE needs PHP 8.1 or newer (this server runs PHP ' . PHP_VERSION . '). '
        . 'In cPanel open "Select PHP Version" or "MultiPHP Manager" and choose PHP 8.1+.');
}

require_once __DIR__ . '/../config.php';

ini_set('display_errors', APP_DEBUG ? '1' : '0');
error_reporting(E_ALL);
date_default_timezone_set('Africa/Mogadishu');

if (session_status() === PHP_SESSION_NONE) {
    session_name('ONEOFFICE_SID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

if (!headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

/** Shared PDO connection */
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

/** Escape output for HTML */
function e($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/* ---------- CSRF ---------- */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): bool
{
    $sent = $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return is_string($sent) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $sent);
}

/* ---------- Settings ---------- */
function settings(bool $refresh = false): array
{
    static $cache = null;
    if ($cache === null || $refresh) {
        $cache = [];
        foreach (db()->query('SELECT skey, svalue FROM settings') as $row) {
            $cache[$row['skey']] = $row['svalue'];
        }
    }
    return $cache;
}

function setting(string $key, string $default = ''): string
{
    $all = settings();
    return isset($all[$key]) && $all[$key] !== '' ? (string) $all[$key] : $default;
}

/* ---------- Flash messages ---------- */
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

/** Initials for avatar placeholders */
function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $out = '';
    foreach (array_slice($parts, 0, 2) as $p) {
        $out .= mb_strtoupper(mb_substr($p, 0, 1));
    }
    return $out ?: '?';
}

/** Only allow Bootstrap-Icons class names */
function safe_icon(?string $icon): string
{
    $icon = (string) $icon;
    return preg_match('/^bi-[a-z0-9-]{1,60}$/', $icon) ? $icon : 'bi-star';
}

/** Run a .sql file statement by statement (used by install.php) */
function run_sql_file(PDO $pdo, string $file): void
{
    $sql = (string) file_get_contents($file);
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);
    foreach (preg_split('/;\s*(\r?\n|$)/', $sql) as $stmt) {
        if (trim($stmt) !== '') {
            $pdo->exec($stmt);
        }
    }
}

/** Cache-busting URL for a file inside assets/ */
function asset(string $path): string
{
    $file = dirname(__DIR__) . '/assets/' . $path;
    return 'assets/' . $path . (is_file($file) ? '?v=' . filemtime($file) : '');
}

/**
 * Request statuses, in workflow order (mirrors the 5-step service model).
 * key => [Somali label, English label, icon, step number (0 = not on the timeline)]
 */
function request_statuses(): array
{
    return [
        'received'   => ['Received',                     'Received',            'bi-inbox',             1],
        'preparing'  => ['Preparing documents',          'Preparing documents', 'bi-folder2-open',      2],
        'needs_info' => ['Action needed from you',       'Action needed',       'bi-exclamation-circle', 2],
        'submitted'  => ['Submitted to institution',     'Submitted to institution', 'bi-bank',          3],
        'ready'      => ['Ready for delivery',           'Ready / collected',   'bi-box-seam',          4],
        'delivered'  => ['Delivered',                    'Delivered',           'bi-check2-circle',     5],
        'cancelled'  => ['Cancelled',                    'Cancelled',           'bi-x-circle',          0],
    ];
}

function status_label(string $status, bool $english = false): string
{
    $s = request_statuses()[$status] ?? null;
    return $s ? $s[$english ? 1 : 0] : $status;
}

function status_badge(string $status, bool $english = false): string
{
    $s = request_statuses()[$status] ?? ['?', '?', 'bi-question-circle', 0];
    return '<span class="st st-' . e(preg_replace('/[^a-z_]/', '', $status)) . '"><i class="bi ' . e($s[2]) . '"></i> '
        . e($s[$english ? 1 : 0]) . '</span>';
}

/** Customer types for service requests */
function customer_types(): array
{
    return ['individual' => 'Individual', 'business' => 'Company / Business', 'organization' => 'Organization / NGO', 'diaspora' => 'Somali living abroad'];
}

/** Unique, easy-to-read tracking number, e.g. OO-261126-7KQ3 */
function new_tracking_no(PDO $pdo): string
{
    $alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ'; // no 0/O/1/I confusion
    $check = $pdo->prepare('SELECT 1 FROM requests WHERE tracking_no = ?');
    for ($try = 0; $try < 20; $try++) {
        $code = '';
        for ($i = 0; $i < 4; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        $no = 'OO-' . date('ymd') . '-' . $code;
        $check->execute([$no]);
        if (!$check->fetchColumn()) {
            return $no;
        }
    }
    throw new RuntimeException('Could not generate a tracking number.');
}

/** Digits only – used to compare phone numbers */
function phone_digits(string $phone): string
{
    return preg_replace('/\D+/', '', $phone);
}

function money($amount): string
{
    $v = (float) $amount;
    return setting('currency', '$') . number_format($v, fmod($v, 1.0) == 0.0 ? 0 : 2);
}

/** Show a friendly page if the database is not reachable / not installed */
function db_or_fail(): void
{
    try {
        db()->query('SELECT 1 FROM settings LIMIT 1');
    } catch (Throwable $ex) {
        http_response_code(503);
        error_log('ONE Office DB error: ' . $ex->getMessage());
        echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>Setup needed</title><body style="font-family:system-ui;padding:40px;max-width:640px;margin:auto">'
            . '<h1>ONE Office – setup needed</h1><p>The database is not reachable or not installed yet.</p>'
            . '<p>1. Check <code>config.php</code><br>2. Open <a href="install.php">install.php</a> to create the tables.</p>'
            . (APP_DEBUG ? '<pre>' . e($ex->getMessage()) . '</pre>' : '') . '</body>';
        exit;
    }
}
