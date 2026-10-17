<?php
// Expects: $title, $page. Optional: $css (extra stylesheet path).
$nav = [
  'index.php'       => ['Lobby', 'Arrive and settle in.'],
  'rooms.php'       => ['Rooms', 'Six ways to stay.'],
  'booking.php'     => ['Reserve', 'Dates, rooms and extras.'],
  'experiences.php' => ['Experiences', 'Spa and gym.'],
  'manage.php'      => ['Manage', 'Change or cancel a stay.'],
  'contact.php'     => ['Contact', 'We answer at any hour.'],
];
?>
<!doctype html>
<html lang="en" data-motion="signature">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($title) ?> | <?= e(HOTEL_NAME) ?></title>
<script>try{var r=document.documentElement;r.classList.add('js');r.dataset.motion=localStorage.getItem('motion')||'signature';if(sessionStorage.getItem('sx')){r.classList.add('sx-pending');setTimeout(function(){r.classList.remove('sx-pending')},3000)}}catch(e){}</script>
<noscript><style>.sx,.sx-pending .sx{display:none}</style></noscript>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,500;1,300&family=Manrope:wght@400;500;600&display=swap">
<link rel="stylesheet" href="assets/css/tokens.css">
<link rel="stylesheet" href="assets/css/base.css">
<link rel="stylesheet" href="assets/css/motion.css">
<?php foreach ((array)($css ?? []) as $c): ?><link rel="stylesheet" href="<?= e($c) ?>"><?php endforeach; ?>
</head>
<body>
<a class="skip" href="#main">Skip to content</a>
<div class="sx" aria-hidden="true"><i></i><i></i><i><b>MA</b></i></div>
<header class="site-header">
  <a class="logo" href="index.php" aria-label="<?= e(HOTEL_NAME) ?> home"><?= e(HOTEL_NAME) ?></a>
  <nav aria-label="Main">
    <ul class="nav">
      <?php foreach ($nav as $href => $v): ?>
        <li><a href="<?= e($href) ?>"<?= $href === $page ? ' aria-current="page"' : '' ?>><?= e($v[0]) ?></a></li>
      <?php endforeach; ?>
    </ul>
  </nav>
  <div class="hdr-actions">
    <a class="btn btn--sm" href="booking.php">Reserve</a>
    <button class="menu-btn" type="button" aria-expanded="false" aria-controls="menu">Menu</button>
  </div>
</header>
<div class="menu" id="menu" role="dialog" aria-modal="true" aria-label="Site menu" inert>
  <button class="menu-close" type="button">Close</button>
  <ul class="menu-list">
    <?php $n = 0; foreach ($nav as $href => $v): ?>
      <li style="--n:<?= $n ?>"><a href="<?= e($href) ?>" data-tag="<?= e($v[1]) ?>"<?= $href === $page ? ' aria-current="page"' : '' ?>><span class="num"><?= sprintf('%02d', ++$n) ?></span><span><?= e($v[0]) ?></span></a></li>
    <?php endforeach; ?>
  </ul>
  <p class="menu-preview" aria-hidden="true"><span></span></p>
</div>
<main id="main">
