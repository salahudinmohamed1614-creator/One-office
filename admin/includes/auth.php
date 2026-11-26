<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';

const ADMIN_SESSION_TIMEOUT = 7200; // 2 hours of inactivity

function is_logged_in(): bool
{
    if (empty($_SESSION['admin_id'])) {
        return false;
    }
    if (time() - (int) ($_SESSION['admin_seen'] ?? 0) > ADMIN_SESSION_TIMEOUT) {
        unset($_SESSION['admin_id'], $_SESSION['admin_name'], $_SESSION['admin_seen']);
        return false;
    }
    $_SESSION['admin_seen'] = time();
    return true;
}

function require_login(): void
{
    db_or_fail();
    if (!is_logged_in()) {
        redirect('login.php');
    }
    header('Cache-Control: no-store');
}

/** Rejects POST requests without a valid CSRF token */
function require_csrf(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_check()) {
        http_response_code(400);
        flash('error', 'Security token expired. Please try again.');
        // Go back to the same page (keeping ?t=... etc). Only allow a local path.
        $back = (string) ($_SERVER['REQUEST_URI'] ?? '');
        if (!preg_match('~^/(?![/\\\\])~', $back)) {
            $back = 'index.php';
        }
        redirect($back);
    }
}

/** URL of a file in assets/ as seen from the admin/ folder */
function admin_asset_url(string $path): string
{
    return '../' . asset($path);
}

function unread_count(): int
{
    return (int) db()->query('SELECT COUNT(*) FROM messages WHERE is_read = 0')->fetchColumn();
}
