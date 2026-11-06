<?php
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/helpers.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/session.php';

$spa = []; $pt = null;
try {
    $db = db();
    $spa = $db->query("SELECT * FROM addons WHERE is_active = 1 AND (category = 'spa' OR slug IN ('wellness-retreat','romance-escape')) ORDER BY sort_order")->fetchAll();
    $pt = $db->query("SELECT * FROM addons WHERE is_active = 1 AND slug = 'personal-training'")->fetch() ?: null;
} catch (Throwable $e) {}
$rule = ['per_stay' => 'per stay', 'per_night' => 'per night', 'per_person' => 'per person', 'per_person_per_night' => 'per person, per night'];
$equip = ['Free weights to 50 kg', 'Cable and resistance machines', 'Treadmills and rowers', 'Stretch and mobility studio'];
$classes = [['Morning flow', '07:00'], ['Strength circuit', '12:30'], ['Evening stretch', '18:30']];

function pick(array $a): void { ?>
  <form method="post" action="booking.php"><?= csrf_field() ?><input type="hidden" name="do" value="pick"><input type="hidden" name="addon" value="<?= (int)$a['id'] ?>"><button class="btn btn--sm" type="submit">Add to my stay</button></form>
<?php }

$title = 'Experiences';
$page = 'experiences.php';
$css = 'assets/css/experiences.css';
$js = 'assets/js/scrub.js';
include __DIR__ . '/includes/header.php';
?>
<div class="sprog" aria-hidden="true"></div>
<section class="xp xp-hero">
  <span class="label">Spa and gym</span>
  <h1 data-split data-delay="300">Where the day <em>slows</em> down</h1>
  <p>Treatments that take their time, and a gym that is quiet when you need it to be. Add anything to your stay and we'll have it waiting.</p>
  <span class="ghost" aria-hidden="true" data-depth=".18">Rest</span>
</section>

<section class="xp split">
  <figure class="slab from-left" style="--dir:1" data-scrub><img <?= img_attrs('assets/img/spa.jpg', '(max-width: 800px) 100vw, 50vw') ?> alt="Three lit candles on a table in front of a window" width="1200" height="1500" loading="lazy"></figure>
  <div>
    <span class="label">The spa</span>
    <h2 data-split>Slow, deliberate <em>care</em></h2>
    <p>Four treatment rooms, warm stone and a tea lounge. Open daily 09:00 to 21:00.</p>
    <?php if (!$spa): ?><p>The treatment menu can't be shown right now.</p><?php endif; ?>
  </div>
</section>

<?php if ($spa): ?>
<section class="tm" aria-labelledby="tm-title">
  <div class="tm-pin">
    <header class="tm-head">
      <div>
        <span class="label">The treatment menu</span>
        <h2 id="tm-title" data-split>Choose your <em>hour</em></h2>
      </div>
      <p class="tm-count" aria-hidden="true"></p>
    </header>
    <i class="tm-rail" aria-hidden="true"><i class="tm-bar"></i></i>
    <div class="tm-track" tabindex="0" role="group" aria-label="Treatments, scrolls sideways">
      <?php foreach ($spa as $i => $a): ?>
        <article class="tmc">
          <img class="tmc-img" src="<?= e(small_image('assets/img/spa.jpg')) ?>" alt="" width="1200" height="1500" loading="lazy" style="object-position:<?= $i * 25 ?>% 50%">
          <span class="tmc-n" aria-hidden="true"><?= sprintf('%02d', $i + 1) ?></span>
          <h3><?= e($a['name']) ?></h3>
          <p><?= e($a['description']) ?></p>
          <?php if ($a['savings_note']): ?><p class="tmc-note"><?= e($a['savings_note']) ?></p><?php endif; ?>
          <div class="tmc-foot">
            <span class="price"><?= money($a['price']) ?> <?= e($rule[$a['pricing_type']]) ?></span>
            <?php pick($a); ?>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="xp split flip">
  <figure class="slab from-right" style="--dir:-1" data-scrub><img <?= img_attrs('assets/img/gym.jpg', '(max-width: 800px) 100vw, 50vw') ?> alt="A barbell with a weight plate resting on a gym floor" width="1200" height="1500" loading="lazy"></figure>
  <div>
    <span class="label">The gym</span>
    <h2 data-split>Train in <em>quiet</em></h2>
    <p>Open 05:30 to 23:00 for guests, with a coach on the floor until 20:00.</p>
    <ul class="chips"><?php foreach ($equip as $q): ?><li><?= e($q) ?></li><?php endforeach; ?></ul>
    <ul class="rows">
      <?php foreach ($classes as $c): ?><li><div><h3><?= e($c[0]) ?></h3></div><span class="price"><?= e($c[1]) ?></span></li><?php endforeach; ?>
      <?php if ($pt): ?><li><div><h3><?= e($pt['name']) ?></h3><p><?= e($pt['description']) ?></p><span class="price"><?= money($pt['price']) ?> <?= e($rule[$pt['pricing_type']]) ?></span></div><?php pick($pt); ?></li><?php endif; ?>
    </ul>
  </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
