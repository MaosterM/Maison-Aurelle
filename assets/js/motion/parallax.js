// Layered parallax: [data-depth] elements drift against the scroll by that fraction. Off for Calm and reduced motion.
const els = [...document.querySelectorAll('[data-depth]')];
if (els.length) {
  const root = document.documentElement;
  const reduce = matchMedia('(prefers-reduced-motion: reduce)');
  const off = () => reduce.matches || root.dataset.motion === 'calm';
  let queued = false;

  const update = () => {
    queued = false;
    if (off()) { els.forEach(e => { e.style.transform = e.dataset.rest || ''; }); return; }
    const h = innerHeight;
    const scale = innerWidth < 761 ? .5 : 1;
    els.forEach(e => {
      const r = e.parentElement.getBoundingClientRect();
      if (r.bottom < -h || r.top > h * 2) return;   // far off screen: nothing to do
      const y = ((r.top + r.height / 2) - h / 2) * -(+e.dataset.depth) * scale;
      e.style.transform = `translate3d(0,${y.toFixed(1)}px,0) ${e.dataset.rest || ''}`;
    });
  };
  const queue = () => { if (!queued) { queued = true; requestAnimationFrame(update); } };

  addEventListener('scroll', queue, { passive: true });
  addEventListener('resize', queue);
  document.addEventListener('motion-change', queue);
  update();
}
