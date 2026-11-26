<?php
/**
 * Generic create / edit / delete screen for the content types in includes/entities.php
 */
declare(strict_types=1);
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/layout.php';
require_login();
require_csrf();

$entities = require __DIR__ . '/includes/entities.php';
$t = (string) ($_GET['t'] ?? '');
if (!isset($entities[$t])) {
    http_response_code(404);
    exit('Unknown section');
}
$ent    = $entities[$t];
$fields = $ent['fields'];
$table  = $t; // safe: whitelisted by the entities array keys
$pdo    = db();
$self   = 'manage.php?t=' . urlencode($t);
$uploadDir = dirname(__DIR__) . '/uploads/';

/** Validate & store an uploaded image. Returns new filename, null if none sent. Throws on invalid file. */
function handle_upload(string $field, string $dir): ?string
{
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $f = $_FILES[$field];
    if ($f['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload failed (error code ' . (int) $f['error'] . ').');
    }
    if ($f['size'] > MAX_UPLOAD_BYTES) {
        throw new RuntimeException('Image is too large (max 2 MB).');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
    if ($ext === null || @getimagesize($f['tmp_name']) === false) {
        throw new RuntimeException('Only JPG, PNG or WEBP images are allowed.');
    }
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $name = bin2hex(random_bytes(12)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], $dir . $name)) {
        throw new RuntimeException('Could not save the image.');
    }
    return $name;
}

function delete_upload(?string $file, string $dir): void
{
    if ($file) {
        $path = $dir . basename($file);
        if (is_file($path)) {
            @unlink($path);
        }
    }
}

function find_row(PDO $pdo, string $table, int $id): ?array
{
    $s = $pdo->prepare("SELECT * FROM `$table` WHERE id = ?");
    $s->execute([$id]);
    return $s->fetch() ?: null;
}

// Options for "select" fields (e.g. the category of a service)
$options = [];
foreach ($fields as $name => $f) {
    if ($f['type'] === 'select') {
        $options[$name] = $pdo->query($f['options_sql'])->fetchAll(PDO::FETCH_KEY_PAIR);
    }
}

$errors = [];
$formData = null;

/* ---------- POST actions ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'delete' && $id > 0) {
        $row = find_row($pdo, $table, $id);
        if ($row) {
            $pdo->prepare("DELETE FROM `$table` WHERE id = ?")->execute([$id]);
            foreach ($fields as $name => $f) {
                if ($f['type'] === 'image') {
                    delete_upload($row[$name] ?? null, $uploadDir);
                }
            }
            flash('success', $ent['singular'] . ' deleted.');
        }
        redirect($self);
    }

    if ($action === 'toggle' && $id > 0 && isset($fields['is_active'])) {
        $pdo->prepare("UPDATE `$table` SET is_active = 1 - is_active WHERE id = ?")->execute([$id]);
        flash('success', 'Visibility updated.');
        redirect($self);
    }

    if ($action === 'save') {
        $existing = $id > 0 ? find_row($pdo, $table, $id) : null;
        if ($id > 0 && !$existing) {
            redirect($self);
        }
        $values = [];
        $newUploads = [];

        foreach ($fields as $name => $f) {
            $label = $f['label'];
            switch ($f['type']) {
                case 'checkbox':
                    $values[$name] = isset($_POST[$name]) ? 1 : 0;
                    break;
                case 'number':
                    $values[$name] = (int) ($_POST[$name] ?? 0);
                    break;
                case 'price':
                    $v = str_replace(',', '', trim((string) ($_POST[$name] ?? '')));
                    if (!is_numeric($v) || (float) $v < 0) {
                        $errors[] = "$label must be a positive number.";
                    }
                    $values[$name] = round((float) $v, 2);
                    break;
                case 'rating':
                    $values[$name] = min(5, max(1, (int) ($_POST[$name] ?? 5)));
                    break;
                case 'select':
                    $v = (int) ($_POST[$name] ?? 0);
                    if (!isset($options[$name][$v])) {
                        $errors[] = "Please choose a $label.";
                    }
                    $values[$name] = $v;
                    break;
                case 'icon':
                    $v = trim((string) ($_POST[$name] ?? ''));
                    if (!preg_match('/^bi-[a-z0-9-]{1,60}$/', $v)) {
                        $errors[] = "$label must look like bi-building.";
                    }
                    $values[$name] = $v;
                    break;
                case 'image':
                    try {
                        $up = handle_upload($name, $uploadDir);
                        if ($up !== null) {
                            $values[$name] = $up;
                            $newUploads[] = $up;
                        } elseif (!empty($_POST['remove_' . $name])) {
                            $values[$name] = null;
                        }
                    } catch (RuntimeException $ex) {
                        $errors[] = $ex->getMessage();
                    }
                    break;
                default: // text, textarea, lines
                    $v = trim((string) ($_POST[$name] ?? ''));
                    if (!empty($f['required']) && $v === '') {
                        $errors[] = "$label is required.";
                    }
                    if (!empty($f['max']) && mb_strlen($v) > $f['max']) {
                        $errors[] = "$label is too long (max {$f['max']} characters).";
                    }
                    $values[$name] = $v === '' ? (empty($f['required']) ? null : '') : $v;
            }
        }

        if ($errors) {
            foreach ($newUploads as $u) {
                delete_upload($u, $uploadDir);
            }
            $formData = array_merge($existing ?? [], $values, ['id' => $id]);
        } else {
            $cols = array_keys($values);
            if ($existing) {
                $set = implode(', ', array_map(fn($c) => "`$c` = ?", $cols));
                $pdo->prepare("UPDATE `$table` SET $set WHERE id = ?")->execute([...array_values($values), $id]);
                // remove replaced / removed images
                foreach ($fields as $name => $f) {
                    if ($f['type'] === 'image' && array_key_exists($name, $values) && $existing[$name] !== $values[$name]) {
                        delete_upload($existing[$name], $uploadDir);
                    }
                }
                flash('success', $ent['singular'] . ' updated.');
            } else {
                $ph = implode(', ', array_fill(0, count($cols), '?'));
                $colSql = implode(', ', array_map(fn($c) => "`$c`", $cols));
                $pdo->prepare("INSERT INTO `$table` ($colSql) VALUES ($ph)")->execute(array_values($values));
                flash('success', $ent['singular'] . ' added.');
            }
            redirect($self);
        }
    }
}

/* ---------- Decide view ---------- */
$view = (string) ($_GET['action'] ?? 'list');
if ($formData !== null) {
    $view = 'form';
} elseif ($view === 'new') {
    $formData = ['id' => 0];
    foreach ($fields as $name => $f) {
        $formData[$name] = $f['default'] ?? '';
        if ($f['type'] === 'select' && isset($_GET[$name])) {
            $formData[$name] = (int) $_GET[$name];
        }
    }
    $view = 'form';
} elseif ($view === 'edit') {
    $formData = find_row($pdo, $table, (int) ($_GET['id'] ?? 0));
    if (!$formData) {
        flash('error', 'Item not found.');
        redirect($self);
    }
    $view = 'form';
}

admin_header($ent['title'], $t);

if ($view === 'form'):
    $isEdit = (int) $formData['id'] > 0;
?>
<div class="panel form-panel">
  <div class="panel-head">
    <h2><?= $isEdit ? 'Edit ' : 'Add ' ?><?= e(strtolower($ent['singular'])) ?></h2>
    <a href="<?= e($self) ?>" class="link"><i class="bi bi-arrow-left"></i> Back</a>
  </div>
  <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
  <form method="post" enctype="multipart/form-data" class="form" action="<?= e($self) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= (int) $formData['id'] ?>">
    <?php foreach ($fields as $name => $f): $val = $formData[$name] ?? ''; $req = !empty($f['required']) ? 'required' : ''; ?>
      <?php if ($f['type'] === 'checkbox'): ?>
        <label class="switch"><input type="checkbox" name="<?= e($name) ?>" value="1" <?= (int) $val ? 'checked' : '' ?>><span></span><?= e($f['label']) ?></label>
      <?php elseif ($f['type'] === 'textarea' || $f['type'] === 'lines'): ?>
        <label><?= e($f['label']) ?><textarea name="<?= e($name) ?>" rows="<?= $f['type'] === 'lines' ? 6 : 4 ?>" <?= $req ?> maxlength="<?= (int) ($f['max'] ?? 5000) ?>"><?= e($val) ?></textarea></label>
      <?php elseif ($f['type'] === 'image'): ?>
        <div class="field">
          <span class="label"><?= e($f['label']) ?></span>
          <div class="img-field">
            <?php if (!empty($val) && is_string($val)): ?>
              <img src="../uploads/<?= e(basename($val)) ?>" alt="" class="thumb lg">
            <?php endif; ?>
            <div class="grow">
              <input type="file" name="<?= e($name) ?>" accept="image/jpeg,image/png,image/webp">
              <?php if (!empty($val)): ?><label class="check"><input type="checkbox" name="remove_<?= e($name) ?>" value="1"> Remove current photo</label><?php endif; ?>
            </div>
          </div>
        </div>
      <?php elseif ($f['type'] === 'select'): ?>
        <label><?= e($f['label']) ?>
          <select name="<?= e($name) ?>" <?= $req ?>>
            <option value="">— Choose —</option>
            <?php foreach ($options[$name] as $optId => $optLabel): ?>
              <option value="<?= (int) $optId ?>" <?= (int) $val === (int) $optId ? 'selected' : '' ?>><?= e($optLabel) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
      <?php elseif ($f['type'] === 'rating'): ?>
        <label><?= e($f['label']) ?>
          <select name="<?= e($name) ?>"><?php for ($i = 5; $i >= 1; $i--): ?><option value="<?= $i ?>" <?= (int) $val === $i ? 'selected' : '' ?>><?= str_repeat('★', $i) ?> (<?= $i ?>)</option><?php endfor; ?></select>
        </label>
      <?php elseif ($f['type'] === 'icon'): ?>
        <label><?= e($f['label']) ?>
          <div class="icon-input"><i class="bi <?= e(safe_icon((string) $val)) ?>" data-icon-preview></i><input type="text" name="<?= e($name) ?>" value="<?= e($val) ?>" <?= $req ?> pattern="bi-[a-z0-9\-]+" data-icon-input></div>
          <?php if (!empty($f['help'])): ?><small class="muted"><?= e($f['help']) ?></small><?php endif; ?>
        </label>
      <?php else: ?>
        <label><?= e($f['label']) ?>
          <input type="<?= in_array($f['type'], ['number', 'price'], true) ? 'number' : 'text' ?>" name="<?= e($name) ?>" value="<?= e($val) ?>" <?= $req ?>
            <?= $f['type'] === 'price' ? 'step="0.01" min="0"' : '' ?> <?= !empty($f['max']) ? 'maxlength="' . (int) $f['max'] . '"' : '' ?>>
        </label>
      <?php endif; ?>
    <?php endforeach; ?>
    <div class="form-actions">
      <button class="btn btn-primary" type="submit"><i class="bi bi-check2"></i> Save</button>
      <a href="<?= e($self) ?>" class="btn btn-ghost">Cancel</a>
    </div>
  </form>
</div>
<?php else:
    $order = $ent['order'] ?? ((isset($fields['sort_order']) ? 'sort_order, ' : '') . 'id'); // from the whitelist file, not user input
    $rows = $pdo->query("SELECT * FROM `$table` ORDER BY $order")->fetchAll();
?>
<div class="panel">
  <div class="panel-head">
    <h2><?= count($rows) ?> <?= e(strtolower($ent['title'])) ?></h2>
    <a href="<?= e($self) ?>&amp;action=new" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Add <?= e(strtolower($ent['singular'])) ?></a>
  </div>
  <?php if (!$rows): ?>
    <p class="empty"><i class="bi bi-folder2-open"></i> Nothing here yet. Click “Add” to create the first one.</p>
  <?php else: ?>
  <div class="table-wrap">
    <table class="table">
      <thead><tr>
        <?php foreach ($ent['list'] as $col): ?><th><?= e($fields[$col]['label']) ?></th><?php endforeach; ?>
        <th class="right">Actions</th>
      </tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <?php foreach ($ent['list'] as $col): $f = $fields[$col]; $v = $r[$col]; ?>
            <td data-label="<?= e($f['label']) ?>">
              <?php if ($f['type'] === 'icon'): ?><span class="icon-chip"><i class="bi <?= e(safe_icon($v)) ?>"></i></span>
              <?php elseif ($f['type'] === 'image'): ?>
                <?php if ($v): ?><img class="thumb" src="../uploads/<?= e(basename($v)) ?>" alt=""><?php else: ?><span class="avatar-sm"><?= e(initials((string) ($r['name'] ?? '?'))) ?></span><?php endif; ?>
              <?php elseif ($col === 'is_active'): ?>
                <form method="post" action="<?= e($self) ?>" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                  <button type="submit" class="status <?= $v ? 'on' : 'off' ?>" title="Click to toggle"><?= $v ? 'Visible' : 'Hidden' ?></button></form>
              <?php elseif ($f['type'] === 'checkbox'): ?><?= $v ? '<span class="status on">Yes</span>' : '<span class="muted">—</span>' ?>
              <?php elseif ($f['type'] === 'price'): ?><?= e(setting('currency', '$') . number_format((float) $v, 2)) ?>
              <?php elseif ($f['type'] === 'rating'): ?><span class="stars"><?= str_repeat('★', (int) $v) ?></span>
              <?php elseif ($f['type'] === 'select'): ?><span class="chip"><?= e($options[$col][$v] ?? '—') ?></span>
              <?php else: ?><?= e(mb_strimwidth((string) $v, 0, 70, '…')) ?><?php endif; ?>
            </td>
          <?php endforeach; ?>
          <td class="right actions">
            <a href="<?= e($self) ?>&amp;action=edit&amp;id=<?= (int) $r['id'] ?>" class="icon-btn" title="Edit"><i class="bi bi-pencil"></i></a>
            <form method="post" action="<?= e($self) ?>" class="inline" data-confirm="<?= e($ent['delete_warning'] ?? 'Delete this ' . strtolower($ent['singular']) . '? This cannot be undone.') ?>">
              <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
              <button type="submit" class="icon-btn danger" title="Delete"><i class="bi bi-trash"></i></button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
<?php endif;
admin_footer();
