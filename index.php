<?php
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/helpers.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/session.php';

$rooms = [];
try {
    $rooms = db()->query("SELECT slug, name, base_price, size_sqm, bed_config, hero_image, `view` FROM room_types WHERE is_active = 1 ORDER BY sort_order LIMIT 6")->fetchAll();
} catch (Throwable $e) { /* page still renders without the database */ }

$title = 'The Grand Lobby';
$page = 'index.php';
$css = 'assets/css/home.css';
$js = 'assets/js/home.js';
$today = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 day'));
include __DIR__ . '/includes/header.php';
?>
<section class="hero">
  <div class="hero-slides" aria-hidden="true">
    <div class="hs is-active"><img <?= img_attrs('assets/img/hero.jpg') ?> alt="" width="1920" height="1080" fetchpriority="high"></div>
    <div class="hs"><img <?= img_attrs('assets/img/rooms/garden-villa.jpg', '100vw', 'data-') ?> alt="" width="1200" height="1500" style="object-position:50% 100%"></div>
    <div class="hs"><img <?= img_attrs('assets/img/spa.jpg', '100vw', 'data-') ?> alt="" width="1200" height="1500" style="object-position:50% 65%"></div>
  </div>
  <i class="hero-edge" aria-hidden="true"></i>
  <h1 data-split data-delay="500">Where <em>stillness</em> becomes an art form</h1>
  <p data-delay="900">Five-star rooms, a private spa and a concierge who answers at any hour.</p>
  <div class="hero-ctl" role="group" aria-label="Background images" hidden>
    <button class="hero-pause" type="button" aria-pressed="false" aria-label="Pause background images"></button>
    <button class="hero-prev" type="button" aria-label="Previous image">&#8592;</button>
    <span class="hero-count"></span>
    <button class="hero-next" type="button" aria-label="Next image">&#8594;</button>
  </div>
  <form class="booking-bar" action="booking.php" method="get" aria-label="Check availability">
    <input type="hidden" name="step" value="1">
    <label>Check in <input type="date" name="check_in" min="<?= $today ?>" value="<?= $today ?>" required></label>
    <label>Check out <input type="date" name="check_out" min="<?= $tomorrow ?>" value="<?= $tomorrow ?>" required></label>
    <label>Guests
      <select name="adults"><?php for ($i = 1; $i <= 4; $i++): ?><option value="<?= $i ?>"<?= $i === 2 ? ' selected' : '' ?>><?= $i ?> adult<?= $i > 1 ? 's' : '' ?></option><?php endfor; ?></select>
    </label>
    <button class="btn" type="submit">Check availability</button>
  </form>
</section>

<section class="section section--light welcome">
  <div>
    <h2 data-split>A hotel that keeps its own <em>time</em></h2>
    <p class="reveal">Maison Aurelle is built around unhurried mornings, late dinners and rooms that hold the quiet. Arrive, and everything else is arranged.</p>
  </div>
  <div class="layers" aria-hidden="true">
    <img class="l-back" <?= img_attrs('assets/img/rooms/deluxe.jpg', '(max-width: 900px) 70vw, 35vw') ?> alt="" width="1200" height="1500" loading="lazy" data-depth=".05">
    <img class="l-front" <?= img_attrs('assets/img/spa.jpg', '(max-width: 900px) 50vw, 25vw') ?> alt="" width="1200" height="1500" loading="lazy" data-depth=".14">
    <i class="l-slash" data-depth=".26" data-rest="skewX(-12deg)" style="transform:skewX(-12deg)"></i>
  </div>
</section>

<?php if ($rooms): ?>
<section class="section">
  <h2 data-split>Rooms and suites</h2>
  <div class="rail">
    <?php foreach ($rooms as $i => $r): ?>
      <a href="rooms.php#<?= e($r['slug']) ?>"<?= $i === 0 ? ' class="is-open"' : '' ?>>
        <?php if ($r['hero_image']): ?><img <?= img_attrs($r['hero_image'], '(max-width: 760px) 100vw, 50vw') ?> alt="" style="object-position:<?= e(ROOM_PHOTOS[$r['slug']]['focus'] ?? '50% 50%') ?>" loading="lazy"><?php endif; ?>
        <span class="v"><?= e($r['name']) ?></span>
        <div class="more">
          <span class="label">From <?= money($r['base_price']) ?> a night</span>
          <h3><?= e($r['name']) ?></h3>
          <p><?= e($r['size_sqm']) ?> m², <?= e($r['bed_config']) ?>. <?= e($r['view']) ?>.</p>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
