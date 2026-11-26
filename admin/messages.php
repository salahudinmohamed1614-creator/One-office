<?php
declare(strict_types=1);
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/layout.php';
require_login();
require_csrf();

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);
    if ($action === 'delete' && $id > 0) {
        $pdo->prepare('DELETE FROM messages WHERE id = ?')->execute([$id]);
        flash('success', 'Message deleted.');
    } elseif ($action === 'unread' && $id > 0) {
        $pdo->prepare('UPDATE messages SET is_read = 0 WHERE id = ?')->execute([$id]);
        flash('success', 'Marked as unread.');
    } elseif ($action === 'read_all') {
        $pdo->exec('UPDATE messages SET is_read = 1 WHERE is_read = 0');
        flash('success', 'All messages marked as read.');
    }
    redirect('messages.php');
}

/* ----- Single message ----- */
if (isset($_GET['id'])) {
    $s = $pdo->prepare('SELECT * FROM messages WHERE id = ?');
    $s->execute([(int) $_GET['id']]);
    $m = $s->fetch();
    if (!$m) {
        flash('error', 'Message not found.');
        redirect('messages.php');
    }
    if (!$m['is_read']) {
        $pdo->prepare('UPDATE messages SET is_read = 1 WHERE id = ?')->execute([$m['id']]);
    }
    admin_header('Message', 'messages');
    ?>
    <div class="panel message-view">
      <div class="panel-head">
        <a href="messages.php" class="link"><i class="bi bi-arrow-left"></i> All messages</a>
        <div class="actions">
          <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="unread"><input type="hidden" name="id" value="<?= (int) $m['id'] ?>"><button class="btn btn-ghost btn-sm" type="submit"><i class="bi bi-envelope"></i> Mark unread</button></form>
          <form method="post" class="inline" data-confirm="Delete this message?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $m['id'] ?>"><button class="btn btn-danger btn-sm" type="submit"><i class="bi bi-trash"></i> Delete</button></form>
        </div>
      </div>
      <div class="msg-head">
        <div class="avatar-sm lg"><?= e(initials($m['name'])) ?></div>
        <div>
          <h2><?= e($m['subject'] ?: 'No subject') ?></h2>
          <p class="muted"><b><?= e($m['name']) ?></b> · <a href="mailto:<?= e($m['email']) ?>"><?= e($m['email']) ?></a>
            <?php if ($m['phone']): ?> · <a href="tel:<?= e($m['phone']) ?>"><?= e($m['phone']) ?></a><?php endif; ?></p>
          <small class="muted"><?= e(date('l, F j, Y \a\t g:i A', strtotime($m['created_at']))) ?> · IP <?= e($m['ip']) ?></small>
        </div>
      </div>
      <div class="msg-body"><?= nl2br(e($m['message'])) ?></div>
      <a class="btn btn-primary" href="mailto:<?= e($m['email']) ?>?subject=<?= rawurlencode('Re: ' . ($m['subject'] ?: 'Your message')) ?>"><i class="bi bi-reply"></i> Reply by email</a>
    </div>
    <?php
    admin_footer();
    exit;
}

/* ----- List ----- */
$filter = ($_GET['filter'] ?? '') === 'unread' ? 'unread' : 'all';
$q = trim((string) ($_GET['q'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$per = 15;

$where = [];
$params = [];
if ($filter === 'unread') {
    $where[] = 'is_read = 0';
}
if ($q !== '') {
    $where[] = '(name LIKE ? OR email LIKE ? OR subject LIKE ? OR message LIKE ?)';
    $like = '%' . addcslashes($q, '%_\\') . '%';
    array_push($params, $like, $like, $like, $like);
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$c = $pdo->prepare("SELECT COUNT(*) FROM messages $whereSql");
$c->execute($params);
$total = (int) $c->fetchColumn();
$pages = max(1, (int) ceil($total / $per));
$page = min($page, $pages);
$offset = ($page - 1) * $per;

$s = $pdo->prepare("SELECT id, name, email, subject, message, is_read, created_at FROM messages $whereSql ORDER BY created_at DESC, id DESC LIMIT $per OFFSET $offset");
$s->execute($params);
$rows = $s->fetchAll();

$qs = fn(array $extra) => 'messages.php?' . http_build_query(array_filter(array_merge(['filter' => $filter === 'unread' ? 'unread' : null, 'q' => $q ?: null], $extra)));

admin_header('Messages', 'messages');
?>
<div class="panel">
  <div class="panel-head wrap">
    <div class="tabs">
      <a href="messages.php" class="<?= $filter === 'all' ? 'active' : '' ?>">All</a>
      <a href="messages.php?filter=unread" class="<?= $filter === 'unread' ? 'active' : '' ?>">Unread <?php $u = unread_count(); if ($u): ?><b class="count"><?= $u ?></b><?php endif; ?></a>
    </div>
    <div class="toolbar">
      <form method="get" class="search">
        <?php if ($filter === 'unread'): ?><input type="hidden" name="filter" value="unread"><?php endif; ?>
        <i class="bi bi-search"></i><input type="search" name="q" value="<?= e($q) ?>" placeholder="Search messages…">
      </form>
      <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="read_all"><button class="btn btn-ghost btn-sm" type="submit"><i class="bi bi-check2-all"></i> Mark all read</button></form>
    </div>
  </div>

  <?php if (!$rows): ?>
    <p class="empty"><i class="bi bi-inbox"></i> No messages found.</p>
  <?php else: ?>
    <ul class="msg-list big">
      <?php foreach ($rows as $m): ?>
        <li class="<?= $m['is_read'] ? '' : 'unread' ?>">
          <a href="messages.php?id=<?= (int) $m['id'] ?>">
            <div class="avatar-sm"><?= e(initials($m['name'])) ?></div>
            <div class="grow">
              <b><?= e($m['name']) ?> <span class="muted small">&lt;<?= e($m['email']) ?>&gt;</span></b>
              <small><strong><?= e($m['subject'] ?: 'No subject') ?></strong> — <?= e(mb_strimwidth($m['message'], 0, 90, '…')) ?></small>
            </div>
            <time><?= e(date('M j, g:i A', strtotime($m['created_at']))) ?></time>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
    <?php if ($pages > 1): ?>
      <nav class="pager">
        <?php for ($i = 1; $i <= $pages; $i++): ?>
          <a href="<?= e($qs(['page' => $i])) ?>" class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
      </nav>
    <?php endif; ?>
  <?php endif; ?>
</div>
<?php admin_footer();
