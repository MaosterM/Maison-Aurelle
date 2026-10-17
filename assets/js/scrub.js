// Scroll-linked reveal: each [data-scrub] element gets --p (0 to 1) as it travels up the viewport.
const els = [...document.querySelectorAll('[data-scrub]')];
const bar = document.querySelector('.sprog');
const calm = matchMedia('(prefers-reduced-motion: reduce)').matches;
if (calm) els.forEach(e => e.style.setProperty('--p', 1));

let queued = false;
const update = () => {
  queued = false;
  const vh = innerHeight;
  if (!calm) els.forEach(e => {
    const p = Math.min(1, Math.max(0, (vh - e.getBoundingClientRect().top) / (vh * .6)));
    e.style.setProperty('--p', p.toFixed(3));
  });
  if (bar) {
    const max = document.documentElement.scrollHeight - vh;
    bar.style.setProperty('--sp', max > 0 ? (scrollY / max).toFixed(3) : 0);
  }
};
const queue = () => { if (!queued) { queued = true; requestAnimationFrame(update); } };
addEventListener('scroll', queue, { passive: true });
addEventListener('resize', queue);
update();
