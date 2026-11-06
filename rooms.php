<?php
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/helpers.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/session.php';
$rooms = [];
try { $rooms = db()->query('SELECT * FROM room_types WHERE is_active = 1 ORDER BY sort_order')->fetchAll(); } catch (Throwable $e) {}
$title = 'Rooms and Suites';
$page = 'rooms.php';
$css = 'assets/css/rooms.css';
$js = 'assets/js/rooms.js';
include __DIR__ . '/includes/header.php';
?>
<section class="rooms">
  <span class="label">Stay with us</span>
  <h1 data-split>Rooms and <em>suites</em></h1>
  <p>Six ways to stay, from a quiet city room to a villa with its own pool. Hover, tap or focus a panel to see the details.</p>
  <?php if (!$rooms): ?>
    <p>Our rooms can't be shown right now. Please try again shortly.</p>
  <?php else: ?>
    <div class="filters" role="group" aria-label="Filter rooms">
      <button type="button" data-filter="all" aria-pressed="true">All</button>
      <button type="button" data-filter="room" aria-pressed="false">Rooms</button>
      <button type="button" data-filter="suite" aria-pressed="false">Suites</button>
    </div>
    <div class="stack">
      <?php foreach ($rooms as $i => $r): $am = json_decode((string)$r['amenities'], true) ?: []; ?>
        <article class="room<?= $i === 0 ? ' is-open' : '' ?>" id="<?= e($r['slug']) ?>" data-tier="<?= e($r['tier']) ?>" tabindex="0">
          <img <?= img_attrs((string)$r['hero_image']) ?> alt="<?= e(ROOM_PHOTOS[$r['slug']]['alt'] ?? $r['name']) ?>" style="object-position:<?= e(ROOM_PHOTOS[$r['slug']]['focus'] ?? '50% 50%') ?>"<?= $i === 0 ? ' fetchpriority="high"' : ' loading="lazy"' ?>>
          <header><h2><?= e($r['name']) ?></h2><span class="price">From <?= money($r['base_price']) ?> a night</span></header>
          <div class="more">
            <div class="flip">
              <div class="flip-inner">
                <div class="flip-face flip-front">
                  <p><?= e($r['description']) ?></p>
                  <dl class="spec">
                    <div><dt>Size</dt><dd><?= e($r['size_sqm']) ?> m²</dd></div>
                    <div><dt>Bed</dt><dd><?= e($r['bed_config']) ?></dd></div>
                    <div><dt>View</dt><dd><?= e($r['view']) ?></dd></div>
                    <div><dt>Sleeps</dt><dd><?= (int)$r['max_adults'] ?> adults<?= $r['max_children'] ? ', ' . (int)$r['max_children'] . ' child' . ($r['max_children'] > 1 ? 'ren' : '') : '' ?></dd></div>
                  </dl>
                </div>
                <div class="flip-face flip-back">
                  <p class="flip-title">Included with your stay</p>
                  <ul class="chips"><?php foreach ($am as $a): ?><li><?= e($a) ?></li><?php endforeach; ?></ul>
                </div>
              </div>
            </div>
            <div class="room-acts">
              <a class="btn" href="booking.php?step=1&amp;room=<?= (int)$r['id'] ?>">Reserve this room</a>
              <button class="flip-btn" type="button" aria-pressed="false" hidden>What's included</button>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
