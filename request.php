<?php
/**
 * Public service request form. Every request gets a tracking number.
 */
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/site.php';
db_or_fail();

$pdo = db();
$enabled = setting('requests_enabled', '1') === '1';
$types = customer_types();

$cats = site_categories();
$svcRows = $pdo->query('SELECT s.id, s.category_id, s.title FROM services s JOIN categories c ON c.id = s.category_id
                        WHERE s.is_active = 1 AND c.is_active = 1 ORDER BY c.sort_order, c.id, s.sort_order, s.id')->fetchAll();
$svcByCat = [];
$svcIds = [];
foreach ($svcRows as $s) {
    $svcByCat[(int) $s['category_id']][] = $s;
    $svcIds[(int) $s['id']] = true;
}

$errors = [];
$data = [
    'service_id'    => (int) ($_GET['service'] ?? 0),
    'customer_type' => array_key_exists((string) ($_GET['type'] ?? ''), $types) ? (string) $_GET['type'] : 'individual',
    'full_name' => '', 'phone' => '', 'email' => '', 'location' => '', 'details' => '',
];

if ($enabled && $_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (['full_name', 'phone', 'email', 'location', 'details', 'customer_type'] as $k) {
        $data[$k] = trim((string) ($_POST[$k] ?? ''));
    }
    $data['service_id'] = (int) ($_POST['service_id'] ?? 0);

    if (!csrf_check()) {
        $errors[] = 'Your session expired. Please try again.';
    } elseif (!empty($_POST['website'])) {
        // Honeypot filled in: a bot. Pretend nothing happened.
        redirect('request.php');
    } else {
        if (isset($_SESSION['last_request']) && time() - (int) $_SESSION['last_request'] < 60) {
            $errors[] = 'Please wait one minute before sending another request.';
        }
        $c = $pdo->prepare('SELECT COUNT(*) FROM requests WHERE ip = ? AND created_at > (NOW() - INTERVAL 1 HOUR)');
        $c->execute([client_ip()]);
        if ((int) $c->fetchColumn() >= 10) {
            $errors[] = 'Too many requests were sent from here. Please try again later or contact us.';
        }
        if (!isset($svcIds[$data['service_id']])) {
            $errors[] = 'Please choose the service you need.';
        }
        if (!array_key_exists($data['customer_type'], $types)) {
            $data['customer_type'] = 'individual';
        }
        if (mb_strlen($data['full_name']) < 3 || mb_strlen($data['full_name']) > 100) {
            $errors[] = 'Please enter your full name.';
        }
        if (!preg_match('/^[0-9+()\-\s]{7,40}$/', $data['phone']) || strlen(phone_digits($data['phone'])) < 7) {
            $errors[] = 'Please enter a valid phone number.';
        }
        if ($data['email'] !== '' && (!filter_var($data['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($data['email']) > 150)) {
            $errors[] = 'The email address is not valid.';
        }
        if (mb_strlen($data['location']) > 120) {
            $errors[] = 'The location is too long.';
        }
        if (mb_strlen($data['details']) > 3000) {
            $errors[] = 'The details must be under 3000 characters.';
        }
        if (empty($_POST['consent'])) {
            $errors[] = 'Please agree to be contacted by our team.';
        }
    }

    if (!$errors) {
        try {
            $s = $pdo->prepare('SELECT s.title, c.title AS cat FROM services s JOIN categories c ON c.id = s.category_id WHERE s.id = ?');
            $s->execute([$data['service_id']]);
            $svc = $s->fetch();

            $pdo->beginTransaction();
            $no = new_tracking_no($pdo);
            $pdo->prepare('INSERT INTO requests (tracking_no, service_id, service_title, category_title, customer_type, full_name, phone, email, location, details, ip)
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([$no, $data['service_id'], $svc['title'], $svc['cat'], $data['customer_type'], $data['full_name'], $data['phone'],
                           $data['email'] ?: null, $data['location'] ?: null, $data['details'] ?: null, client_ip()]);
            $id = (int) $pdo->lastInsertId();
            $pdo->prepare('INSERT INTO request_updates (request_id, status, note) VALUES (?, ?, ?)')
                ->execute([$id, 'received', 'Your request has been received. Our team will contact you shortly.']);
            $pdo->commit();

            $_SESSION['last_request'] = time();
            $_SESSION['request_done'] = ['no' => $no, 'service' => $svc['title'], 'name' => $data['full_name']];
            redirect('request.php?done=1');
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Request save failed: ' . $ex->getMessage());
            $errors[] = 'Sorry, something went wrong. Please try again.';
        }
    }
}

$done = isset($_GET['done']) ? ($_SESSION['request_done'] ?? null) : null;

site_header('Request a Service', 'request', 'Request a government service and receive a Tracking Number to follow it.');
?>
  <section class="page-hero small">
    <div class="container">
      <span class="eyebrow">Request a service</span>
      <h1><?= $done ? 'Your request has been received!' : 'Request a service' ?></h1>
      <?php if (!$done): ?><p class="lead"><?= e(setting('request_intro')) ?></p><?php endif; ?>
    </div>
  </section>

  <section class="section tight">
    <div class="container request-grid">
    <?php if ($done): ?>
      <div class="done-card reveal">
        <div class="done-icon"><i class="bi bi-check2-circle"></i></div>
        <p>Thank you, <b><?= e($done['name']) ?></b>. Your request for <b><?= e($done['service']) ?></b> has been registered.</p>
        <p class="muted">Your Tracking Number:</p>
        <div class="tracking-no" id="trackNo"><?= e($done['no']) ?></div>
        <div class="done-actions">
          <button type="button" class="btn btn-ghost" data-copy="#trackNo"><i class="bi bi-clipboard"></i> <span>Copy</span></button>
          <a class="btn btn-primary" href="track.php?no=<?= e(rawurlencode($done['no'])) ?>"><i class="bi bi-search"></i> Track this request</a>
        </div>
        <div class="note"><i class="bi bi-info-circle-fill"></i><p>Keep this number safe. To track your request you need the Tracking Number and the phone number you used.</p></div>
      </div>
    <?php elseif (!$enabled): ?>
      <div class="done-card">
        <div class="done-icon muted-icon"><i class="bi bi-pause-circle"></i></div>
        <p>Online requests are currently closed. Please contact us or visit our office.</p>
        <a class="btn btn-primary" href="index.php#contact">Contact us</a>
      </div>
    <?php else: ?>
      <form class="contact-form request-form" method="post" action="request.php" novalidate data-validate>
        <?= csrf_field() ?>
        <?php if ($errors): ?>
          <div class="alert alert-error"><ul class="plain"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>
        <div class="hp" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

        <h3 class="form-step"><span>1</span> The service</h3>
        <label>Which service do you need? *
          <select name="service_id" required>
            <option value="">— Choose a service —</option>
            <?php foreach ($cats as $c): if (empty($svcByCat[(int) $c['id']])) continue; ?>
              <optgroup label="<?= e($c['title']) ?>">
                <?php foreach ($svcByCat[(int) $c['id']] as $s): ?>
                  <option value="<?= (int) $s['id'] ?>" <?= $data['service_id'] === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['title']) ?></option>
                <?php endforeach; ?>
              </optgroup>
            <?php endforeach; ?>
          </select>
        </label>
        <fieldset class="radio-cards">
          <legend>I am requesting as *</legend>
          <?php foreach ($types as $k => $label): ?>
            <label><input type="radio" name="customer_type" value="<?= e($k) ?>" <?= $data['customer_type'] === $k ? 'checked' : '' ?>><span><?= e($label) ?></span></label>
          <?php endforeach; ?>
        </fieldset>

        <h3 class="form-step"><span>2</span> Your details</h3>
        <div class="row-2">
          <label>Full name *<input type="text" name="full_name" required minlength="3" maxlength="100" value="<?= e($data['full_name']) ?>" autocomplete="name"></label>
          <label>Phone / WhatsApp *<input type="tel" name="phone" required maxlength="40" value="<?= e($data['phone']) ?>" placeholder="+252 …" autocomplete="tel"></label>
        </div>
        <div class="row-2">
          <label>Email<input type="email" name="email" maxlength="150" value="<?= e($data['email']) ?>" autocomplete="email"></label>
          <label>City / Country<input type="text" name="location" maxlength="120" value="<?= e($data['location']) ?>" placeholder="Mogadishu, London, Minneapolis…"></label>
        </div>

        <h3 class="form-step"><span>3</span> Details</h3>
        <label>Tell us what you need<textarea name="details" rows="5" maxlength="3000" placeholder="e.g. I need my secondary school certificate from 2015…"><?= e($data['details']) ?></textarea></label>
        <label class="check-line"><input type="checkbox" name="consent" value="1" required <?= !empty($_POST['consent']) ? 'checked' : '' ?>> I agree that ONE OFFICE may contact me about this request by phone or email.</label>
        <button type="submit" class="btn btn-accent btn-block"><span>Submit request</span> <i class="bi bi-send"></i></button>
      </form>

      <aside class="request-side">
        <div class="side-card">
          <h3><i class="bi bi-signpost-split"></i> What happens next?</h3>
          <ol class="mini-steps">
            <li>You instantly receive a <b>Tracking Number</b>.</li>
            <li>Our team contacts you to confirm the required documents and the fees.</li>
            <li>We prepare, submit to the institution and follow up.</li>
            <li>When it is ready, we deliver it to you.</li>
          </ol>
        </div>
        <div class="side-card">
          <h3><i class="bi bi-receipt"></i> Fees</h3>
          <p class="muted small">Service Fee, Government Fee, Delivery Fee and Processing Fees are kept separate. You receive a receipt for every payment.</p>
        </div>
        <?php if (setting('phone') !== ''): ?>
        <div class="side-card">
          <h3><i class="bi bi-headset"></i> Need help?</h3>
          <a class="link-arrow" href="tel:<?= e(preg_replace('/[^0-9+]/', '', setting('phone'))) ?>"><?= e(setting('phone')) ?></a>
        </div>
        <?php endif; ?>
      </aside>
    <?php endif; ?>
    </div>
  </section>
<?php site_footer();
