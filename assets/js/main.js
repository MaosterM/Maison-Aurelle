import './motion/transitions.js';
import './motion/menu.js';
import './motion/flex.js';
import './motion/parallax.js';

const root = document.documentElement;
const loaderDelay = root.classList.contains('gh-on') ? 1000 : 0;   // let the Golden Hour loader lift first

// Kinetic typography: [data-split] -> words -> characters (aria-label keeps the heading readable)
const split = el => {
  const label = el.textContent.trim();
  let i = 0;
  (function walk(n) {
    [...n.childNodes].forEach(c => {
      if (c.nodeType === 3) {
        const f = document.createDocumentFragment();
        c.textContent.split(/(\s+)/).forEach(t => {
          if (!t) return;
          if (!t.trim()) { f.append(' '); return; }
          const w = document.createElement('span');
          w.className = 'w';
          w.setAttribute('aria-hidden', 'true');
          [...t].forEach(ch => {
            const s = document.createElement('span');
            s.className = 'c';
            s.style.setProperty('--i', i++);
            s.textContent = ch;
            w.append(s);
          });
          f.append(w);
        });
        c.replaceWith(f);
      } else if (c.nodeType === 1) walk(c);
    });
  })(el);
  el.setAttribute('aria-label', label);
};
document.querySelectorAll('[data-split]').forEach(split);

// Reveal on first view
const watch = (selector, options) => {
  const io = new IntersectionObserver(es => es.forEach(e => {
    if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); }
  }), options);
  document.querySelectorAll(selector).forEach(el => {
    el.style.setProperty('--base', (+el.dataset.delay || 0) + loaderDelay + 'ms');
    io.observe(el);
  });
};
watch('[data-split]', { threshold: .15 });
// A .reveal starts fully clipped, so Chrome reports its ratio as 0 forever: trigger on position, not on a ratio.
watch('.reveal', { rootMargin: '0px 0px -15% 0px' });

// Motion toggle: signature <-> calm (persisted)
const t = document.getElementById('motion-toggle');
if (t) {
  const sync = () => { t.textContent = 'Motion: ' + (root.dataset.motion === 'calm' ? 'Calm' : 'Signature'); };
  sync();
  t.addEventListener('click', () => {
    root.dataset.motion = root.dataset.motion === 'calm' ? 'signature' : 'calm';
    try { localStorage.setItem('motion', root.dataset.motion); } catch (e) {}
    sync();
    document.dispatchEvent(new Event('motion-change'));
  });
}

// Check-out always follows check-in
const ci = document.querySelector('input[type=date][name=check_in]'), co = document.querySelector('input[type=date][name=check_out]');
if (ci && co) {
  ci.addEventListener('change', () => {
    const d = new Date(ci.value);
    if (isNaN(d)) return;
    d.setUTCDate(d.getUTCDate() + 1);
    const m = d.toISOString().slice(0, 10);
    co.min = m;
    if (!co.value || co.value < m) co.value = m;
  });
  import('./datepicker.js');   // only pages with a date form load the picker; without it the native inputs remain
}
