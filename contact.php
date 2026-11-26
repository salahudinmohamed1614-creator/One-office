<?php
/**
 * Contact page.
 * GET  -> shows the page with contact details and the form.
 * POST -> saves the message (AJAX returns JSON, normal POST redirects back here).
 */
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/site.php';
db_or_fail();

$isAjax = (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest');

function respond(bool $ok, string $message, bool $isAjax, array $old = []): never
{
    if ($isAjax) {
        http_response_code($ok ? 200 : 422);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => $ok, 'message' => $message]);
        exit;
    }
    flash($ok ? 'success' : 'error', $message);
    if (!$ok) {
        $_SESSION['contact_old'] = $old;
    }
    redirect('contact.php#contact-form');
}

/* ================= POST: save the message ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'name'    => trim((string) ($_POST['name'] ?? '')),
        'email'   => trim((string) ($_POST['email'] ?? '')),
        'phone'   => trim((string) ($_POST['phone'] ?? '')),
        'subject' => trim((string) ($_POST['subject'] ?? '')),
        'message' => trim((string) ($_POST['message'] ?? '')),
    ];

    if (!csrf_check()) {
        respond(false, 'Your session expired. Please refresh the page and try again.', $isAjax, $data);
    }
    // Honeypot: bots fill hidden fields. Pretend success.
    if (!empty($_POST['website'])) {
        respond(true, 'Thank you! Your message has been sent.', $isAjax);
    }
    // Simple throttle: one message per 30 seconds per session
    if (isset($_SESSION['last_contact']) && time() - (int) $_SESSION['last_contact'] < 30) {
        respond(false, 'Please wait a few seconds before sending another message.', $isAjax, $data);
    }

    $errors = [];
    if (mb_strlen($data['name']) < 2 || mb_strlen($data['name']) > 100) {
        $errors[] = 'Please enter your name.';
    }
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($data['email']) > 150) {
        $errors[] = 'Please enter a valid email address.';
    }
    if ($data['phone'] !== '' && !preg_match('/^[0-9+()\-\s]{6,40}$/', $data['phone'])) {
        $errors[] = 'Please enter a valid phone number.';
    }
    if (mb_strlen($data['message']) < 10 || mb_strlen($data['message']) > 5000) {
        $errors[] = 'Your message must be between 10 and 5000 characters.';
    }
    $data['subject'] = mb_substr($data['subject'], 0, 150);

    if ($errors) {
        respond(false, implode(' ', $errors), $isAjax, $data);
    }

    try {
        $stmt = db()->prepare('INSERT INTO messages (name, email, phone, subject, message, ip) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$data['name'], $data['email'], $data['phone'] ?: null, $data['subject'] ?: null, $data['message'], client_ip()]);
    } catch (Throwable $ex) {
        error_log('Contact save failed: ' . $ex->getMessage());
        respond(false, 'Sorry, something went wrong. Please try again later.', $isAjax, $data);
    }

    $_SESSION['last_contact'] = time();
    respond(true, 'Thank you! Your message has been received. We will contact you soon.', $isAjax);
}

/* ================= GET: the contact page ================= */
$categories = site_categories();
$flashes = take_flashes();
$old = $_SESSION['contact_old'] ?? [];
unset($_SESSION['contact_old']);
$requestsOn = setting('requests_enabled', '1') === '1';
$phone = setting('phone');
$wa = phone_digits(setting('whatsapp'));
$subject = (string) ($old['subject'] ?? ($_GET['subject'] ?? ''));

site_header('Contact us', 'contact', 'Contact ONE OFFICE – phone, email, address, opening hours and a message form.');
?>
  <section class="page-hero">
    <div class="container">
      <span class="eyebrow">Contact</span>
      <h1>Get in touch</h1>
      <p class="lead">Have a question about a service, fees or documents? Call us, visit our office or send a message – One Office, One Customer Service.</p>
    </div>
  </section>

  <!-- Contact cards -->
  <section class="section tight">
    <div class="container">
      <div class="contact-cards">
        <?php if ($phone !== ''): ?>
          <a class="contact-card reveal" href="tel:<?= e(preg_replace('/[^0-9+]/', '', $phone)) ?>">
            <i class="bi bi-telephone"></i><small>Call us</small><b><?= e($phone) ?></b>
          </a>
        <?php endif; ?>
        <?php if ($wa !== ''): ?>
          <a class="contact-card reveal" href="https://wa.me/<?= e($wa) ?>" target="_blank" rel="noopener">
            <i class="bi bi-whatsapp"></i><small>WhatsApp</small><b><?= e(setting('whatsapp')) ?></b>
          </a>
        <?php endif; ?>
        <?php if (setting('email') !== ''): ?>
          <a class="contact-card reveal" href="mailto:<?= e(setting('email')) ?>">
            <i class="bi bi-envelope"></i><small>Email us</small><b><?= e(setting('email')) ?></b>
          </a>
        <?php endif; ?>
        <?php if (setting('address') !== ''): ?>
          <div class="contact-card reveal">
            <i class="bi bi-geo-alt"></i><small>Visit our office</small><b><?= e(setting('address')) ?></b>
          </div>
        <?php endif; ?>
        <?php if (setting('hours') !== ''): ?>
          <div class="contact-card reveal">
            <i class="bi bi-clock"></i><small>Opening hours</small><b><?= e(setting('hours')) ?></b>
          </div>
        <?php endif; ?>
      </div>

      <div class="contact-grid contact-page-grid" id="contact-form">
        <form class="contact-form reveal" id="contactForm" method="post" action="contact.php" novalidate>
          <h2 class="form-title">Send us a message</h2>
          <p class="muted small">We usually reply within one working day.</p>
          <?= csrf_field() ?>
          <div class="form-alert" id="formAlert" role="status" aria-live="polite">
            <?php foreach ($flashes as $f): ?>
              <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
            <?php endforeach; ?>
          </div>
          <div class="hp" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
          <div class="row-2">
            <label>Full name *<input type="text" name="name" required maxlength="100" value="<?= e($old['name'] ?? '') ?>" autocomplete="name"></label>
            <label>Email *<input type="email" name="email" required maxlength="150" value="<?= e($old['email'] ?? '') ?>" autocomplete="email"></label>
          </div>
          <div class="row-2">
            <label>Phone<input type="tel" name="phone" maxlength="40" value="<?= e($old['phone'] ?? '') ?>" autocomplete="tel"></label>
            <label>Subject
              <select name="subject">
                <option value="General question">General question</option>
                <?php foreach ($categories as $c): ?>
                  <option value="<?= e($c['title']) ?>" <?= $subject === $c['title'] ? 'selected' : '' ?>><?= e($c['title']) ?></option>
                <?php endforeach; ?>
                <option value="Fees & payments" <?= $subject === 'Fees & payments' ? 'selected' : '' ?>>Fees & payments</option>
                <option value="Partnership" <?= $subject === 'Partnership' ? 'selected' : '' ?>>Partnership</option>
              </select>
            </label>
          </div>
          <label>Message *<textarea name="message" rows="6" required minlength="10" maxlength="5000"><?= e($old['message'] ?? '') ?></textarea></label>
          <button type="submit" class="btn btn-primary btn-block"><span>Send message</span> <i class="bi bi-send"></i></button>
        </form>

        <aside class="request-side">
          <div class="side-card reveal">
            <h3><i class="bi bi-lightning-charge"></i> Looking for something quick?</h3>
            <ul class="quick-links">
              <?php if ($requestsOn): ?><li><a href="request.php"><i class="bi bi-send"></i> Request a service <i class="bi bi-arrow-right"></i></a></li><?php endif; ?>
              <li><a href="track.php"><i class="bi bi-search"></i> Track your request <i class="bi bi-arrow-right"></i></a></li>
              <li><a href="services.php"><i class="bi bi-grid"></i> Browse all services <i class="bi bi-arrow-right"></i></a></li>
              <li><a href="index.php#faq"><i class="bi bi-question-circle"></i> Read the FAQ <i class="bi bi-arrow-right"></i></a></li>
            </ul>
          </div>
          <div class="side-card reveal">
            <h3><i class="bi bi-globe-americas"></i> Living abroad?</h3>
            <p class="muted small">We serve Somalis in the diaspora too. Send us a message with the service you need and the country you live in.</p>
          </div>
          <?php if (setting('disclaimer') !== ''): ?>
          <div class="side-card reveal">
            <h3><i class="bi bi-info-circle"></i> Please note</h3>
            <p class="muted small"><?= e(setting('disclaimer')) ?></p>
          </div>
          <?php endif; ?>
        </aside>
      </div>
    </div>
  </section>
<?php site_footer();
