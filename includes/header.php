<?php
// Expects: $title, $page. Optional: $css (extra stylesheet path).
// This file runs in the page's scope, so its own variables start with $_nav to avoid overwriting the page's.
$_nav = [
  'index.php'       => ['Lobby', 'Arrive and settle in.'],
  'rooms.php'       => ['Rooms', 'Six ways to stay.'],
  'booking.php'     => ['Reserve', 'Dates, rooms and extras.'],
  'experiences.php' => ['Experiences', 'Spa and gym.'],
  'manage.php'      => ['Manage', 'Change or cancel a stay.'],
  'contact.php'     => ['Contact', 'We answer at any hour.'],
];
?>
<!doctype html>
<html lang="en" data-motion="signature" data-max-nights="<?= (int)MAX_NIGHTS ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($title) ?> | <?= e(HOTEL_NAME) ?></title>
<meta name="description" content="<?= e($description ?? HOTEL_NAME . ' is a fictional five-star hotel: rooms and suites, a private spa, and online booking.') ?>">
<?php if (!empty($noindex)): ?><meta name="robots" content="noindex,nofollow"><?php endif; ?>
<script>try{var r=document.documentElement,w=sessionStorage.getItem('sx');r.classList.add('js');r.dataset.motion=localStorage.getItem('motion')||'signature';if(w){r.classList.add('sx-pending');setTimeout(function(){r.classList.remove('sx-pending')},3000)}if(!w&&!sessionStorage.getItem('gh')&&r.dataset.motion!=='calm'&&!matchMedia('(prefers-reduced-motion: reduce)').matches)r.classList.add('gh-on');sessionStorage.setItem('gh','1')}catch(e){}</script>
<noscript><style>.sx,.sx-pending .sx{display:none}</style></noscript>
<link rel="icon" href="assets/img/favicon.svg" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,500;1,300&family=Manrope:wght@400;500;600&display=swap">
<link rel="stylesheet" href="assets/css/tokens.css">
<link rel="stylesheet" href="assets/css/base.css">
<link rel="stylesheet" href="assets/css/motion.css">
<link rel="stylesheet" href="assets/css/components.css">
<link rel="stylesheet" href="assets/css/chat.css">
<link rel="stylesheet" href="assets/css/datepicker.css">
<?php foreach ((array)($css ?? []) as $_navCss): ?><link rel="stylesheet" href="<?= e($_navCss) ?>"><?php endforeach; ?>
</head>
<body>
<a class="skip" href="#main">Skip to content</a>
<div class="gh" aria-hidden="true"><div class="gh-sky"><i class="gh-sun"></i></div><i class="gh-line"></i><b>Maison Aurelle</b></div>
<div class="sx" aria-hidden="true"><i></i><i></i><i><b>MA</b></i></div>
<header class="site-header">
  <a class="logo" href="index.php" aria-label="<?= e(HOTEL_NAME) ?> home"><?= e(HOTEL_NAME) ?></a>
  <nav aria-label="Main">
    <ul class="nav">
      <?php foreach ($_nav as $_navHref => $_navItem): ?>
        <li><a href="<?= e($_navHref) ?>"<?= $_navHref === $page ? ' aria-current="page"' : '' ?>><?= e($_navItem[0]) ?></a></li>
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
    <?php $_navNum = 0; foreach ($_nav as $_navHref => $_navItem): ?>
      <li style="--n:<?= $_navNum ?>"><a href="<?= e($_navHref) ?>" data-tag="<?= e($_navItem[1]) ?>"<?= $_navHref === $page ? ' aria-current="page"' : '' ?>><span class="num"><?= sprintf('%02d', ++$_navNum) ?></span><span><?= e($_navItem[0]) ?></span></a></li>
    <?php endforeach; ?>
  </ul>
  <p class="menu-preview" aria-hidden="true"><span></span></p>
</div>
<main id="main">
