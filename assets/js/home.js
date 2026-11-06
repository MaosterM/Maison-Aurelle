// Home hero slider. Without JavaScript the first image simply stays.
const hero = document.querySelector('.hero');
const slides = hero ? [...hero.querySelectorAll('.hs')] : [];
const ctl = hero?.querySelector('.hero-ctl');

if (slides.length > 1 && ctl) {
  const root = document.documentElement;
  const reduce = matchMedia('(prefers-reduced-motion: reduce)');
  const edge = hero.querySelector('.hero-edge');
  const pauseBtn = ctl.querySelector('.hero-pause');
  const count = ctl.querySelector('.hero-count');
  const HOLD = 7000;
  const still = () => reduce.matches || root.dataset.motion === 'calm';

  let current = 0, moving = false, timer = null, inView = true, hovering = false;
  let userPaused = false;

  const pad = n => String(n).padStart(2, '0');
  const showCount = () => { count.textContent = `${pad(current + 1)} / ${pad(slides.length)}`; };

  const load = slide => {
    const img = slide.querySelector('img');
    if (img.dataset.srcset) { img.srcset = img.dataset.srcset; delete img.dataset.srcset; }
    if (img.dataset.src) { img.src = img.dataset.src; delete img.dataset.src; }
    // Never let a slow or stalled image hold the slider up for long.
    const waited = new Promise(resolve => setTimeout(resolve, 1500));
    return img.decode ? Promise.race([img.decode().catch(() => {}), waited]) : Promise.resolve();
  };

  const syncPause = () => {
    const paused = userPaused || still();
    pauseBtn.hidden = still();   // with Calm or reduced motion the images already hold still, so there is nothing to pause
    pauseBtn.setAttribute('aria-pressed', paused);
    pauseBtn.setAttribute('aria-label', paused ? 'Play background images' : 'Pause background images');
    hero.classList.toggle('is-paused', paused);
    count.setAttribute('aria-live', paused ? 'polite' : 'off');   // announce only when the guest is in control
  };

  const schedule = () => {
    clearTimeout(timer);
    if (userPaused || still() || !inView || hovering || document.hidden) return;
    timer = setTimeout(() => go(current + 1), HOLD);
  };

  const go = async to => {
    if (moving) return;
    const n = (to + slides.length) % slides.length;
    if (n === current) return;
    moving = true;
    clearTimeout(timer);
    const prev = slides[current], next = slides[n];
    await load(next);
    const finish = () => {
      prev.classList.remove('is-active');
      next.classList.add('is-active');
      next.classList.remove('is-in');
      edge.classList.remove('is-run');
      moving = false;
      schedule();
    };
    current = n;
    showCount();
    if (reduce.matches) { finish(); return; }
    hero.style.setProperty('--off', Math.round(hero.offsetHeight * Math.tan(12 * Math.PI / 180)) + 'px');
    next.classList.add('is-in');
    edge.classList.add('is-run');
    let done = false;
    const once = () => { if (!done) { done = true; finish(); } };
    next.addEventListener('animationend', e => { if (e.target === next) once(); }, { once: false });
    // A background tab may never finish the animation, so a timer guarantees the slide still settles.
    setTimeout(once, (parseFloat(getComputedStyle(root).getPropertyValue('--dur-reveal')) || 900) + 300);
  };

  ctl.hidden = false;
  showCount();
  syncPause();

  ctl.querySelector('.hero-prev').addEventListener('click', () => go(current - 1));
  ctl.querySelector('.hero-next').addEventListener('click', () => go(current + 1));
  pauseBtn.addEventListener('click', () => {
    userPaused = !userPaused;
    syncPause();
    schedule();
  });

  // Hovering or focusing the controls holds the current image.
  ctl.addEventListener('pointerenter', () => { hovering = true; schedule(); });
  ctl.addEventListener('pointerleave', () => { hovering = false; schedule(); });
  ctl.addEventListener('focusin', () => { hovering = true; schedule(); });
  ctl.addEventListener('focusout', () => { hovering = false; schedule(); });

  new IntersectionObserver(([e]) => { inView = e.isIntersecting; schedule(); }).observe(hero);
  document.addEventListener('visibilitychange', schedule);
  document.addEventListener('motion-change', () => { syncPause(); schedule(); });

  // Fetch the next image once the page has settled, so it doesn't slow the first paint.
  addEventListener('load', () => setTimeout(() => load(slides[1]), 2500), { once: true });
  schedule();
}
