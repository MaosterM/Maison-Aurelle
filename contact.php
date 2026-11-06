<?php
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/helpers.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/session.php';
require __DIR__ . '/includes/reservation.php';
require __DIR__ . '/includes/hotel-info.php';
require __DIR__ . '/includes/mailer.php';

$topics = ['Reservations', 'Arrival and transfer', 'Dining and spa', 'Events and private hire', 'Billing', 'Other'];
$v = ['name' => '', 'email' => '', 'topic' => '', 'message' => ''];
$errors = [];
$formError = '';

$sent = $_SESSION['contact_sent'] ?? null;
unset($_SESSION['contact_sent']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) { http_response_code(400); exit('Invalid request. Go back and try again.'); }
    foreach ($v as $k => $_) $v[$k] = is_string($_POST[$k] ?? null) ? trim($_POST[$k]) : '';
    $firstName = explode(' ', $v['name'])[0];

    // Honeypot: a person never sees this field. Answer a bot as if it worked and store nothing.
    if (($_POST['website'] ?? '') !== '') {
        $_SESSION['contact_sent'] = ['name' => $firstName];
        header('Location: contact.php#inquiry'); exit;
    }

    if (mb_strlen($v['name']) < 2 || mb_strlen($v['name']) > 120) $errors['name'] = 'Please enter your name.';
    if (!filter_var($v['email'], FILTER_VALIDATE_EMAIL) || strlen($v['email']) > 190) $errors['email'] = 'Please enter a valid email address.';
    if (!in_array($v['topic'], $topics, true)) $errors['topic'] = 'Please choose a topic.';
    if (mb_strlen($v['message']) < 5 || mb_strlen($v['message']) > 1000) $errors['message'] = 'Please write a message of 5 to 1000 characters.';

    if (!$errors) {
        try {
            $db = db();
            if (!rate_ok($db, 'ct:' . hash('sha256', $_SERVER['REMOTE_ADDR'] ?? ''), 5, 900)) {
                $formError = 'Too many messages. Please wait a few minutes, or call the front desk.';
            } else {
                submit_contact_inquiry($db, $v['name'], $v['email'], $v['topic'], $v['message']);
                mail_front_desk($v['name'], $v['email'], $v['topic'], $v['message']);   // it is saved either way, so a mail failure can't lose it
                $_SESSION['contact_sent'] = ['name' => $firstName];
                header('Location: contact.php#inquiry'); exit;
            }
        } catch (Throwable $x) {
            error_log('Contact inquiry could not be saved.');
            $formError = 'We could not send your message just now. Please call the front desk.';
        }
    }
}

// Attributes that tie a field to its error message for assistive technology.
$aria = fn(string $k): string => isset($errors[$k]) ? ' aria-invalid="true" aria-describedby="err-' . $k . '"' : '';
$note = fn(string $k): string => isset($errors[$k]) ? '<span class="err" id="err-' . $k . '">' . e($errors[$k]) . '</span>' : '';

$tel = 'tel:' . preg_replace('/[^0-9+]/', '', HOTEL_PHONE);
$directions = 'https://www.google.com/maps/dir/?api=1&destination=' . HOTEL_LAT . ',' . HOTEL_LNG;
$osm = 'https://www.openstreetmap.org/?mlat=' . HOTEL_LAT . '&mlon=' . HOTEL_LNG . '#map=16/' . HOTEL_LAT . '/' . HOTEL_LNG;

$faqs = array_filter(hotel_faqs(), fn($f) => $f['page']);

$title = 'Contact';
$page = 'contact.php';
$css = ['assets/css/booking.css', 'assets/css/contact.css'];
$js = 'assets/js/contact.js';
include __DIR__ . '/includes/header.php';
?>
<section class="ct-hero">
  <span class="label">Contact</span>
  <h1 data-split data-delay="300">We answer at <em>any</em> hour</h1>
  <p>Call, write or ask our concierge. The front desk is staffed day and night.</p>
  <a class="btn" href="#inquiry" data-open-chat>Talk to our concierge</a>
</section>

<section class="ct-cards" aria-label="Ways to reach us">
  <div class="cc">
    <span class="label">Front desk</span>
    <p class="cc-value"><?= e(HOTEL_PHONE) ?></p>
    <p class="cc-note">Open 24 hours, every day.</p>
    <div class="cc-acts">
      <a class="btn btn--sm" href="<?= e($tel) ?>">Call us</a>
      <button class="copy" type="button" data-copy="<?= e(HOTEL_PHONE) ?>" data-what="Phone number" aria-label="Copy phone number">Copy</button>
    </div>
  </div>
  <div class="cc">
    <span class="label">Reservations</span>
    <p class="cc-value"><?= e(HOTEL_EMAIL) ?></p>
    <p class="cc-note">We reply within one day.</p>
    <div class="cc-acts">
      <a class="btn btn--sm" href="mailto:<?= e(HOTEL_EMAIL) ?>">Email us</a>
      <button class="copy" type="button" data-copy="<?= e(HOTEL_EMAIL) ?>" data-what="Email address" aria-label="Copy email address">Copy</button>
    </div>
  </div>
  <div class="cc">
    <span class="label">Find us</span>
    <p class="cc-value"><?= e(HOTEL_ADDRESS) ?></p>
    <p class="cc-note">Valet parking at the main entrance.</p>
    <div class="cc-acts">
      <a class="btn btn--sm" href="#location">See the map</a>
      <button class="copy" type="button" data-copy="<?= e(HOTEL_ADDRESS) ?>" data-what="Address" aria-label="Copy address">Copy</button>
    </div>
  </div>
</section>
<p class="sr-only" id="copy-status" role="status"></p>

<section class="ct-map" id="location">
  <div class="ct-map-head">
    <h2 data-split>On the <em>quay</em></h2>
    <a class="btn" href="<?= e($directions) ?>" target="_blank" rel="noopener">Get directions<span class="sr-only"> (opens in a new tab)</span></a>
  </div>
  <div class="map reveal" id="map" role="region" aria-label="Map showing <?= e(HOTEL_NAME) ?>"
       data-lat="<?= e(HOTEL_LAT) ?>" data-lng="<?= e(HOTEL_LNG) ?>" data-name="<?= e(HOTEL_NAME) ?>" data-address="<?= e(HOTEL_ADDRESS) ?>">
    <div class="map-fallback">
      <p><?= e(HOTEL_ADDRESS) ?></p>
      <p><a href="<?= e($osm) ?>" target="_blank" rel="noopener">Open in OpenStreetMap<span class="sr-only"> (opens in a new tab)</span></a></p>
    </div>
  </div>
</section>

<section class="section section--light ct-form" id="inquiry">
  <div>
    <span class="label">Write to us</span>
    <h2 data-split>Tell us what you <em>need</em></h2>
    <p>Dietary needs, a late arrival, a table for a birthday. Send it here and the front desk will reply by email.</p>
  </div>
  <div>
    <?php if ($sent): ?>
      <div class="sent" role="status" tabindex="-1">
        <svg viewBox="0 0 48 48" width="56" height="56" aria-hidden="true"><circle cx="24" cy="24" r="22"/><path d="M14 25l7 7 13-15"/></svg>
        <h3>Thank you, <?= e($sent['name']) ?>.</h3>
        <p>Your message is with our front desk. We will reply by email within one day.</p>
        <a class="btn btn--sm" href="contact.php#inquiry" data-no-transition>Send another message</a>
      </div>
    <?php else: ?>
      <?php if ($formError): ?><p class="alert" role="alert"><?= e($formError) ?></p>
      <?php elseif ($errors): ?><p class="alert" role="alert">Please check the fields marked below.</p><?php endif; ?>
      <form method="post" action="contact.php#inquiry">
        <?= csrf_field() ?>
        <div class="hp" aria-hidden="true"><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div>
        <div class="row">
          <div><label class="fld">Name<input name="name" minlength="2" maxlength="120" required autocomplete="name" value="<?= e($v['name']) ?>"<?= $aria('name') ?>></label><?= $note('name') ?></div>
          <div><label class="fld">Email<input type="email" name="email" maxlength="190" required autocomplete="email" value="<?= e($v['email']) ?>"<?= $aria('email') ?>></label><?= $note('email') ?></div>
        </div>
        <div class="ct-field">
          <label class="fld">Topic
            <select name="topic" required<?= $aria('topic') ?>>
              <option value="">Choose a topic</option>
              <?php foreach ($topics as $t): ?><option<?= $v['topic'] === $t ? ' selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?>
            </select>
          </label><?= $note('topic') ?>
        </div>
        <div class="ct-field">
          <label class="fld">Message<textarea name="message" rows="5" minlength="5" maxlength="1000" required<?= $aria('message') ?>><?= e($v['message']) ?></textarea></label><?= $note('message') ?>
        </div>
        <button class="btn" type="submit">Send message</button>
      </form>
    <?php endif; ?>
  </div>
</section>

<section class="section ct-faq">
  <span class="label">Before you ask</span>
  <h2 data-split>Questions we <em>hear</em> most</h2>
  <div class="faq">
    <?php foreach ($faqs as $f): ?>
      <details>
        <summary><span><?= e($f['q']) ?></span><i class="ic" aria-hidden="true"></i></summary>
        <p><?= e($f['a']) ?></p>
      </details>
    <?php endforeach; ?>
  </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
