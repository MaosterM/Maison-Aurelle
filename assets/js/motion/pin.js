// Pinned horizontal menu: vertical scrolling drives the track sideways.
// Without this (no JavaScript, phones, Calm, reduced motion) the track is an ordinary swipeable row.
const section = document.querySelector('.tm');
if (section) {
  const root = document.documentElement;
  const track = section.querySelector('.tm-track');
  const cards = [...track.querySelectorAll('.tmc')];
  const count = section.querySelector('.tm-count');
  const bar = section.querySelector('.tm-bar');
  const reduce = matchMedia('(prefers-reduced-motion: reduce)');
  const pad = n => String(n).padStart(2, '0');
  let distance = 0, pinned = false, queued = false;

  const update = () => {
    queued = false;
    if (!pinned) return;
    const p = Math.min(1, Math.max(0, -section.getBoundingClientRect().top / distance));
    track.style.transform = `translate3d(${(-p * distance).toFixed(1)}px,0,0)`;
    bar.style.transform = `scaleX(${p.toFixed(3)})`;
    count.textContent = `${pad(Math.round(p * (cards.length - 1)) + 1)} / ${pad(cards.length)}`;
  };
  const queue = () => { if (!queued) { queued = true; requestAnimationFrame(update); } };

  const unpin = () => {
    pinned = false;
    section.classList.remove('is-pinned');
    section.style.height = '';
    track.style.transform = '';
    track.tabIndex = 0;   // an ordinary row: arrow keys scroll it
  };

  const setup = () => {
    unpin();
    if (innerWidth < 761 || innerHeight < 620 || reduce.matches || root.dataset.motion === 'calm') return;
    section.classList.add('is-pinned');
    distance = track.offsetWidth - root.clientWidth;
    if (distance <= 0) { unpin(); return; }   // everything already fits on screen
    section.style.height = innerHeight + distance + 'px';
    track.tabIndex = -1;   // pinned, arrow keys can't move it; the cards' own buttons bring each card into view
    pinned = true;
    update();
  };

  // Tabbing to a card that is off to the side: scroll the page to where that card is in view.
  track.addEventListener('focusin', e => {
    if (!pinned) return;
    const card = e.target.closest('.tmc');
    if (!card) return;
    const wanted = card.offsetLeft - (root.clientWidth - card.offsetWidth) / 2;
    const p = Math.min(1, Math.max(0, wanted / distance));
    scrollTo({ top: section.getBoundingClientRect().top + scrollY + p * distance, behavior: 'auto' });
  });

  addEventListener('scroll', queue, { passive: true });
  let resizing;
  addEventListener('resize', () => { clearTimeout(resizing); resizing = setTimeout(setup, 150); });
  addEventListener('load', setup);       // images and fonts can change the track's width
  document.fonts?.ready.then(setup);
  document.addEventListener('motion-change', setup);
  setup();
}
