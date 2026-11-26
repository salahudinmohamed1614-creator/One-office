<?php
declare(strict_types=1);
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/layout.php';
require_login();
require_csrf();

// key => [label, type, max length]
$groups = [
    'General' => [
        'site_name'   => ['Website name', 'text', 60],
        'tagline'     => ['Tagline', 'text', 120],
        'slogan'      => ['Slogan', 'text', 120],
        'currency'    => ['Currency symbol', 'text', 5],
        'footer_text' => ['Footer text', 'text', 200],
    ],
    'Homepage' => [
        'hero_kicker' => ['Hero badge text', 'text', 120],
        'hero_title'  => ['Hero title', 'text', 150],
        'hero_text'   => ['Hero text', 'textarea', 500],
        'about_title' => ['About / introduction title', 'text', 150],
        'about_text'  => ['About / introduction text', 'textarea', 2000],
        'disclaimer'  => ['Disclaimer (what ONE OFFICE is not)', 'textarea', 500],
    ],
    'Service requests' => [
        'requests_enabled' => ['Accept online requests (1 = yes, 0 = no)', 'bool', 1],
        'request_intro'    => ['Text above the request form', 'textarea', 600],
    ],
    'Contact' => [
        'phone'    => ['Phone', 'text', 40],
        'whatsapp' => ['WhatsApp number (shows a chat button; leave empty to hide)', 'text', 40],
        'email'    => ['Email', 'email', 150],
        'address'  => ['Address', 'text', 200],
        'hours'    => ['Opening hours', 'text', 100],
    ],
    'Social media (full https:// links, leave empty to hide)' => [
        'facebook'  => ['Facebook', 'url', 255],
        'twitter'   => ['X / Twitter', 'url', 255],
        'instagram' => ['Instagram', 'url', 255],
        'linkedin'  => ['LinkedIn', 'url', 255],
    ],
];

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $save = [];
    foreach ($groups as $fields) {
        foreach ($fields as $key => [$label, $type, $max]) {
            $v = trim((string) ($_POST[$key] ?? ''));
            if (mb_strlen($v) > $max) {
                $errors[] = "$label is too long.";
            } elseif ($type === 'email' && $v !== '' && !filter_var($v, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "$label is not a valid email.";
            } elseif ($type === 'url' && $v !== '' && $v !== '#' && !preg_match('~^https?://~i', $v)) {
                $errors[] = "$label must start with https://";
            } elseif ($type === 'number' && $v !== '' && !ctype_digit($v)) {
                $errors[] = "$label must be a whole number.";
            }
            if ($type === 'bool') {
                $v = isset($_POST[$key]) ? '1' : '0';
            }
            $save[$key] = $v;
        }
    }
    if ($save['site_name'] === '') {
        $errors[] = 'Website name is required.';
    }
    if (!$errors) {
        $stmt = db()->prepare('INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)');
        foreach ($save as $k => $v) {
            $stmt->execute([$k, $v]);
        }
        flash('success', 'Settings saved.');
        redirect('settings.php');
    }
}

$current = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : settings();

admin_header('Site settings', 'settings');
?>
<?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
<form method="post" class="form">
  <?= csrf_field() ?>
  <?php foreach ($groups as $title => $fields): ?>
    <section class="panel">
      <div class="panel-head"><h2><?= e($title) ?></h2></div>
      <div class="form-grid">
        <?php foreach ($fields as $key => [$label, $type, $max]): $v = (string) ($current[$key] ?? ''); ?>
          <?php if ($type === 'bool'): ?>
            <label class="switch full"><input type="checkbox" name="<?= e($key) ?>" value="1" <?= $v === '1' ? 'checked' : '' ?>><span></span>Accept service requests from the website</label>
          <?php elseif ($type === 'textarea'): ?>
            <label class="full"><?= e($label) ?><textarea name="<?= e($key) ?>" rows="4" maxlength="<?= $max ?>"><?= e($v) ?></textarea></label>
          <?php else: ?>
            <label><?= e($label) ?><input type="<?= $type === 'number' ? 'number' : ($type === 'email' ? 'email' : 'text') ?>" name="<?= e($key) ?>" value="<?= e($v) ?>" maxlength="<?= $max ?>" <?= $type === 'number' ? 'min="0"' : '' ?>></label>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endforeach; ?>
  <div class="sticky-save"><button class="btn btn-primary" type="submit"><i class="bi bi-check2"></i> Save settings</button></div>
</form>
<?php admin_footer();
