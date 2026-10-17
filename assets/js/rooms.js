const list = document.querySelector('.stack');
if (list) {
  const items = [...list.children];
  const touch = matchMedia('(hover: none), (max-width: 760px)');
  const calm = matchMedia('(prefers-reduced-motion: reduce)');
  const open = el => items.forEach(i => i.classList.toggle('is-open', i === el));

  // Deep link from the home carousel: rooms.php#slug
  const h = location.hash && document.getElementById(location.hash.slice(1));
  if (h && list.contains(h)) { open(h); setTimeout(() => h.scrollIntoView({ behavior: 'smooth', block: 'center' }), 400); }

  // Touch and small screens: tap a panel to open or close it
  list.addEventListener('click', e => {
    const i = e.target.closest('.room');
    if (touch.matches && i && !e.target.closest('a')) open(i.classList.contains('is-open') ? null : i);
  });

  // Filter chips with FLIP: measure, change layout, animate from the old position
  const btns = [...document.querySelectorAll('[data-filter]')];
  btns.forEach(b => b.addEventListener('click', () => {
    const f = b.dataset.filter;
    btns.forEach(x => x.setAttribute('aria-pressed', x === b));
    const first = new Map(items.map(i => [i, i.getBoundingClientRect()]));
    items.forEach(i => { i.hidden = f !== 'all' && i.dataset.tier !== f; });
    const vis = items.filter(i => !i.hidden);
    if (!vis.some(i => i.classList.contains('is-open'))) open(vis[0]);
    if (calm.matches) return;
    vis.forEach(i => {
      const a = first.get(i), z = i.getBoundingClientRect(), dy = a.top - z.top;
      if (a.height && dy) i.animate([{ transform: `translateY(${dy}px)` }, { transform: 'none' }], { duration: 650, easing: 'cubic-bezier(.16,1,.3,1)' });
    });
  }));
}
