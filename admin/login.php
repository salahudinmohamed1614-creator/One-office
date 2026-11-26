<?php
declare(strict_types=1);
require __DIR__ . '/includes/auth.php';
db_or_fail();

if (is_logged_in()) {
    redirect('index.php');
}

const MAX_ATTEMPTS = 5;
const LOCK_MINUTES = 15;

$error = '';
$pdo = db();
$ip = client_ip();

$pdo->prepare('DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 1 DAY)')->execute();
$stmt = $pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND attempted_at > (NOW() - INTERVAL ' . LOCK_MINUTES . ' MINUTE)');
$stmt->execute([$ip]);
$attempts = (int) $stmt->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (!csrf_check()) {
        $error = 'Session expired. Please try again.';
    } elseif ($attempts >= MAX_ATTEMPTS) {
        $error = 'Too many failed attempts. Try again in ' . LOCK_MINUTES . ' minutes.';
    } else {
        $q = $pdo->prepare('SELECT id, name, password_hash FROM admins WHERE username = ? LIMIT 1');
        $q->execute([$username]);
        $admin = $q->fetch();

        if ($admin && password_verify($password, $admin['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id']   = (int) $admin['id'];
            $_SESSION['admin_name'] = $admin['name'];
            $_SESSION['admin_seen'] = time();
            unset($_SESSION['csrf']);

            if (password_needs_rehash($admin['password_hash'], PASSWORD_DEFAULT)) {
                $pdo->prepare('UPDATE admins SET password_hash = ? WHERE id = ?')
                    ->execute([password_hash($password, PASSWORD_DEFAULT), $admin['id']]);
            }
            $pdo->prepare('UPDATE admins SET last_login = NOW() WHERE id = ?')->execute([$admin['id']]);
            $pdo->prepare('DELETE FROM login_attempts WHERE ip = ?')->execute([$ip]);
            redirect('index.php');
        }

        $pdo->prepare('INSERT INTO login_attempts (ip, attempted_at) VALUES (?, NOW())')->execute([$ip]);
        $left = MAX_ATTEMPTS - $attempts - 1;
        $error = 'Wrong username or password.' . ($left > 0 ? " {$left} attempt(s) left." : '');
    }
}
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Admin login · <?= e(setting('site_name', 'ONE Office')) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="icon" type="image/png" href="<?= e(admin_asset_url('img/favicon.png')) ?>">
<link rel="stylesheet" href="<?= e(admin_asset_url('css/admin.css')) ?>">
</head>
<body class="auth-page">
<main class="auth-card">
  <div class="auth-logo"><img src="<?= e(admin_asset_url('img/logo.png')) ?>" alt="<?= e(setting('site_name', 'ONE OFFICE')) ?>"></div>
  <h1>Admin login</h1>
  <p class="muted">Sign in to manage requests, services and the website.</p>
  <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
  <?php foreach (take_flashes() as $f): ?><div class="alert alert-<?= e($f['type']) ?>"><?= e($f['message']) ?></div><?php endforeach; ?>
  <form method="post" class="form">
    <?= csrf_field() ?>
    <label>Username<input type="text" name="username" required autofocus autocomplete="username" value="<?= e($_POST['username'] ?? '') ?>"></label>
    <label>Password<input type="password" name="password" required autocomplete="current-password"></label>
    <button class="btn btn-primary btn-block" type="submit"><i class="bi bi-box-arrow-in-right"></i> Sign in</button>
  </form>
  <a class="back" href="../index.php"><i class="bi bi-arrow-left"></i> Back to website</a>
</main>
</body>
</html>
