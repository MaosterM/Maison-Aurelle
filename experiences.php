<?php
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/helpers.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/session.php';

$spa = []; $pt = null;
try {
    $db = db();
    $spa = $db->query("SELECT * FROM addons WHERE is_active = 1 AND category = 'spa' ORDER BY sort_order")->fetchAll();
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
</section>

<section class="xp split">
  <figure class="slab from-left" style="--dir:1" data-scrub><img src="assets/img/spa.jpg" alt="Stacked stones and candlelight beside still water" loading="lazy"></figure>
  <div>
    <span class="label">The spa</span>
    <h2 data-split>Slow, deliberate <em>care</em></h2>
    <p>Four treatment rooms, warm stone and a tea lounge. Open daily 09:00 to 21:00.</p>
    <?php if ($spa): ?>
      <ul class="rows">
        <?php foreach ($spa as $a): ?>
          <li><div><h3><?= e($a['name']) ?></h3><p><?= e($a['description']) ?></p><span class="price"><?= money($a['price']) ?> <?= e($rule[$a['pricing_type']]) ?></span></div><?php pick($a); ?></li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?><p>The treatment menu can't be shown right now.</p><?php endif; ?>
  </div>
</section>

<section class="xp split flip">
  <figure class="slab from-right" style="--dir:-1" data-scrub><img src="assets/img/gym.jpg" alt="A barbell with weight plates in low light" loading="lazy"></figure>
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
