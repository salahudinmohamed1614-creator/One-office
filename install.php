<?php
/**
 * ONE Office installer.
 * Creates the tables, inserts sample content and the first admin account.
 * It locks itself automatically once an admin exists. Delete this file after installing.
 */
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$errors = [];
$done = false;
$locked = false;

// Create the database if it does not exist yet (local XAMPP).
// On shared hosting (cPanel, InfinityFree) the database is created in the control panel
// and the user may not be allowed to run CREATE DATABASE, so a failure here is ignored.
try {
    $server = new PDO(
        sprintf('mysql:host=%s;port=%s;charset=utf8mb4', DB_HOST, DB_PORT),
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $server->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '', DB_NAME) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
} catch (Throwable $ex) {
    // ignore – we try to connect to the existing database below
}
try {
    $pdo = db();
    run_sql_file($pdo, __DIR__ . '/database/schema.sql');
    $locked = (int) $pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn() > 0;
} catch (Throwable $ex) {
    $errors[] = 'Database connection failed. Check config.php. ' . (APP_DEBUG ? $ex->getMessage() : '');
}

if (!$errors && !$locked && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $name     = trim((string) ($_POST['name'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirm  = (string) ($_POST['confirm'] ?? '');

    if (!csrf_check()) {
        $errors[] = 'Session expired, please try again.';
    }
    if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) {
        $errors[] = 'Username must be 3-50 characters (letters, numbers, _ . -).';
    }
    if (mb_strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    if (!$errors) {
        try {
            if ((int) $pdo->query('SELECT COUNT(*) FROM settings')->fetchColumn() === 0) {
                run_sql_file($pdo, __DIR__ . '/database/seed.sql');
            }
            $stmt = $pdo->prepare('INSERT INTO admins (username, name, password_hash) VALUES (?, ?, ?)');
            $stmt->execute([$username, $name !== '' ? $name : 'Administrator', password_hash($password, PASSWORD_DEFAULT)]);
            $done = true;
        } catch (Throwable $ex) {
            $errors[] = 'Install failed. ' . (APP_DEBUG ? $ex->getMessage() : 'Check the server error log.');
        }
    }
}
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Install ONE Office</title>
<link rel="stylesheet" href="assets/css/admin.css">
</head>
<body class="auth-page">
<main class="auth-card">
  <div class="auth-logo"><img src="assets/img/logo.png" alt="ONE OFFICE"></div>
  <h1>Installer</h1>
  <?php foreach ($errors as $err): ?>
    <div class="alert alert-error"><?= e($err) ?></div>
  <?php endforeach; ?>

  <?php if ($done): ?>
    <div class="alert alert-success">Installed successfully! Your admin account is ready.</div>
    <p class="muted">For security, <strong>delete install.php</strong> from the server now.</p>
    <a class="btn btn-primary btn-block" href="admin/login.php">Go to admin login</a>
  <?php elseif ($locked): ?>
    <div class="alert alert-info">ONE Office is already installed. The installer is locked.</div>
    <a class="btn btn-primary btn-block" href="admin/login.php">Go to admin login</a>
  <?php elseif (!$errors || $_SERVER['REQUEST_METHOD'] === 'POST'): ?>
    <p class="muted">Database connected. Create the first administrator account.</p>
    <form method="post" class="form">
      <?= csrf_field() ?>
      <label>Full name<input type="text" name="name" maxlength="100" value="<?= e($_POST['name'] ?? '') ?>"></label>
      <label>Username<input type="text" name="username" required maxlength="50" value="<?= e($_POST['username'] ?? 'admin') ?>"></label>
      <label>Password<input type="password" name="password" required minlength="8"></label>
      <label>Confirm password<input type="password" name="confirm" required minlength="8"></label>
      <button class="btn btn-primary btn-block" type="submit">Install</button>
    </form>
  <?php endif; ?>
</main>
</body>
</html>
