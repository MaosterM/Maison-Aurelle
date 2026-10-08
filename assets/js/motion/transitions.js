const root = document.documentElement;
const sx = document.querySelector('.sx');
const ms = v => parseFloat(getComputedStyle(root).getPropertyValue(v)) || 0;
const total = () => ms('--dur-wipe') + ms('--stagger') * 2;
const reduce = matchMedia('(prefers-reduced-motion: reduce)');

const reset = () => {
  sx.classList.add('reset');
  sx.classList.remove('is-cover', 'is-out');
  void sx.offsetWidth;
  sx.classList.remove('reset');
};

if (sx) {
  // Arrived through a wipe: start covered, then reveal in the same direction.
  if (root.classList.contains('sx-pending')) {
    sx.classList.add('is-cover');
    root.classList.remove('sx-pending');
    void sx.offsetWidth;
    try { sessionStorage.removeItem('sx'); } catch (e) {}
    requestAnimationFrame(() => {
      sx.classList.replace('is-cover', 'is-out');
      setTimeout(reset, total() + 200);
    });
  }

  // Back/forward cache restores the old DOM; clear any overlay state.
  addEventListener('pageshow', e => { if (e.persisted) reset(); });

  document.addEventListener('click', e => {
    const a = e.target.closest('a[href]');
    if (!a || e.defaultPrevented || e.button || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey ||
        reduce.matches || a.target || a.hasAttribute('download') || a.dataset.noTransition !== undefined) return;
    const u = new URL(a.href, location.href);
    if (u.origin !== location.origin || (u.pathname === location.pathname && u.search === location.search)) return;
    e.preventDefault();
    sx.classList.remove('is-out');
    sx.classList.add('is-cover');
    try { sessionStorage.setItem('sx', '1'); } catch (err) {}
    setTimeout(() => { location.href = u.href; }, total() + 100);
  });
}
