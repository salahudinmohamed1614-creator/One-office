<?php
declare(strict_types=1);
require __DIR__ . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check()) {
    // Wipe all session data and issue a brand-new session id (old one is destroyed)
    $_SESSION = [];
    session_regenerate_id(true);
    flash('success', 'You have been logged out.');
}
redirect('login.php');
