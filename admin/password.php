<?php
declare(strict_types=1);
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/layout.php';
require_login();
require_csrf();

$pdo = db();
$errors = [];
$s = $pdo->prepare('SELECT id, username, name, password_hash FROM admins WHERE id = ?');
$s->execute([$_SESSION['admin_id']]);
$admin = $s->fetch();
if (!$admin) {
    redirect('login.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim((string) ($_POST['name'] ?? ''));
    $current = (string) ($_POST['current'] ?? '');
    $new     = (string) ($_POST['new'] ?? '');
    $confirm = (string) ($_POST['confirm'] ?? '');

    if (!password_verify($current, $admin['password_hash'])) {
        $errors[] = 'Current password is wrong.';
    }
    if ($name === '' || mb_strlen($name) > 100) {
        $errors[] = 'Please enter your name.';
    }
    if ($new !== '' && mb_strlen($new) < 8) {
        $errors[] = 'New password must be at least 8 characters.';
    }
    if ($new !== $confirm) {
        $errors[] = 'New passwords do not match.';
    }

    if (!$errors) {
        if ($new !== '') {
            $pdo->prepare('UPDATE admins SET name = ?, password_hash = ? WHERE id = ?')
                ->execute([$name, password_hash($new, PASSWORD_DEFAULT), $admin['id']]);
            session_regenerate_id(true);
        } else {
            $pdo->prepare('UPDATE admins SET name = ? WHERE id = ?')->execute([$name, $admin['id']]);
        }
        $_SESSION['admin_name'] = $name;
        flash('success', 'Account updated.');
        redirect('password.php');
    }
}

admin_header('Account & password', 'password');
?>
<div class="panel form-panel">
  <div class="panel-head"><h2>Signed in as <?= e($admin['username']) ?></h2></div>
  <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
  <form method="post" class="form">
    <?= csrf_field() ?>
    <label>Display name<input type="text" name="name" required maxlength="100" value="<?= e($_POST['name'] ?? $admin['name']) ?>"></label>
    <label>New password <small class="muted">(leave empty to keep current)</small><input type="password" name="new" minlength="8" autocomplete="new-password"></label>
    <label>Confirm new password<input type="password" name="confirm" minlength="8" autocomplete="new-password"></label>
    <label>Current password *<input type="password" name="current" required autocomplete="current-password"></label>
    <div class="form-actions"><button class="btn btn-primary" type="submit"><i class="bi bi-check2"></i> Save changes</button></div>
  </form>
</div>
<?php admin_footer();
