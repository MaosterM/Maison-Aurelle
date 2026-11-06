// Replaces each check-in and check-out pair with a range calendar and a nights dial.
// The real date inputs stay in the form and carry the values, so the server sees nothing different.
const MAX = +document.documentElement.dataset.maxNights || 30;
const reduce = matchMedia('(prefers-reduced-motion: reduce)');

// Dates are plain YYYY-MM-DD strings, handled in UTC so no time zone can shift a day.
const parse = s => new Date(s + 'T00:00:00Z');
const iso = d => d.toISOString().slice(0, 10);
const add = (s, n) => { const d = parse(s); d.setUTCDate(d.getUTCDate() + n); return iso(d); };
const diff = (a, b) => Math.round((parse(b) - parse(a)) / 864e5);
const fmt = (s, o) => parse(s).toLocaleDateString('en-GB', { timeZone: 'UTC', ...o });
const short = s => fmt(s, { day: 'numeric', month: 'short', year: 'numeric' });
const long = s => fmt(s, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
const monthOf = s => s.slice(0, 7);
const addMonths = (m, n) => { const d = parse(m + '-01'); d.setUTCMonth(d.getUTCMonth() + n); return iso(d).slice(0, 7); };

const R = 64;
const ticks = Array.from({ length: MAX }, (_, i) => `<line x1="80" y1="${i % 7 === 0 ? 8 : 11}" x2="80" y2="15" transform="rotate(${(360 * i) / MAX} 80 80)"/>`).join('');

const wrap = document.createElement('div');
wrap.className = 'dp-wrap';
wrap.hidden = true;
wrap.innerHTML = `
  <div class="dp" role="dialog" aria-modal="true" aria-labelledby="dp-title">
    <header class="dp-head">
      <h2 id="dp-title">Choose your dates</h2>
      <button class="dp-text" type="button" data-act="close">Close</button>
    </header>
    <div class="dp-body">
      <div class="dp-cal">
        <div class="dp-nav">
          <button class="dp-arrow" type="button" data-act="prev" aria-label="Previous month">&#8592;</button>
          <p id="dp-month" aria-live="polite"></p>
          <button class="dp-arrow" type="button" data-act="next" aria-label="Next month">&#8594;</button>
        </div>
        <table class="dp-grid" role="grid" aria-labelledby="dp-month"></table>
      </div>
      <div class="dp-side">
        <div class="dp-dial" role="slider" tabindex="0" aria-label="Number of nights" aria-valuemin="1">
          <svg viewBox="0 0 160 160" aria-hidden="true">
            <g class="dp-ticks">${ticks}</g>
            <circle class="dp-track" cx="80" cy="80" r="${R}"/>
            <circle class="dp-arc" cx="80" cy="80" r="${R}" pathLength="${MAX}" transform="rotate(-90 80 80)"/>
            <g class="dp-handle"><rect x="74" y="10" width="12" height="12" transform="rotate(45 80 16)"/></g>
          </svg>
          <div class="dp-read" aria-hidden="true"><b></b><span></span></div>
        </div>
        <div class="dp-steps">
          <button class="dp-arrow" type="button" data-act="less" aria-label="One night fewer">&#8722;</button>
          <button class="dp-arrow" type="button" data-act="more" aria-label="One night more">+</button>
        </div>
      </div>
    </div>
    <footer class="dp-foot">
      <p class="dp-sum"></p>
      <p class="dp-hint"></p>
      <button class="btn btn--sm" type="button" data-act="done">Done</button>
    </footer>
    <p class="sr-only" id="dp-status" role="status"></p>
  </div>`;
document.body.append(wrap);

const panel = wrap.querySelector('.dp');
const grid = wrap.querySelector('.dp-grid');
const monthEl = wrap.querySelector('#dp-month');
const prevBtn = wrap.querySelector('[data-act=prev]');
const dial = wrap.querySelector('.dp-dial');
const arc = wrap.querySelector('.dp-arc');
const handle = wrap.querySelector('.dp-handle');
const numEl = wrap.querySelector('.dp-read b');
const unitEl = wrap.querySelector('.dp-read span');
const sumEl = wrap.querySelector('.dp-sum');
const hintEl = wrap.querySelector('.dp-hint');
const statusEl = wrap.querySelector('#dp-status');

let pair = null;     // the form being edited
let ci = '', nights = 1, view = '', focusDay = '', pickingOut = false, opener = null;
const sold = new Set();
const loaded = new Set();

const query = () => {
  const p = new URLSearchParams({ adults: pair.adults() });
  if (pair.room()) p.set('room', pair.room());
  if (pair.exclude) p.set('exclude', '1');
  return p;
};

// How many nights can start on this day before reaching a sold-out night?
const freeRun = from => {
  let n = 0;
  while (n < MAX && !sold.has(add(from, n))) n++;
  return n;
};

const loadMonth = async m => {
  const key = query().toString() + '|' + m;
  if (loaded.has(key)) return;
  loaded.add(key);
  try {
    const p = query();
    p.set('month', m);
    const res = await fetch('api/availability.php?' + p);
    if (!res.ok) return;
    const data = await res.json();
    data.sold_out.forEach(d => sold.add(d));
    if (pair) render();
  } catch (e) { /* no marks on the calendar; the server still checks when booking */ }
};

const isOff = d => {
  if (d < pair.ci.min) return true;
  if (!pickingOut || d <= ci) return sold.has(d);
  const n = diff(ci, d);            // choosing a check-out: every night before it must be free
  return n > MAX || n > freeRun(ci);
};

const renderGrid = () => {
  const refocus = grid.contains(document.activeElement);
  const first = parse(view + '-01');
  const blanks = (first.getUTCDay() + 6) % 7;   // weeks start on Monday
  const out = add(ci, nights);
  const count = parse(addMonths(view, 1) + '-01').getTime() - first.getTime();
  const days = Math.round(count / 864e5);
  let cells = '<td></td>'.repeat(blanks);
  for (let i = 0; i < days; i++) {
    const d = add(view + '-01', i);
    const off = isOff(d);
    const isIn = d === ci, isOut = d === out, inside = d > ci && d < out;
    const cls = ['dp-d', isIn && 'is-start', isOut && 'is-end', inside && 'in-range', off && 'is-off', sold.has(d) && 'is-sold'].filter(Boolean).join(' ');
    const label = long(d) + (isIn ? ', check in' : '') + (isOut ? ', check out' : '') + (sold.has(d) ? ', sold out' : '');
    cells += `<td role="gridcell" aria-selected="${isIn || isOut || inside}"><button type="button" class="${cls}" data-d="${d}" tabindex="${d === focusDay ? 0 : -1}" aria-label="${label}"${off ? ' aria-disabled="true"' : ''}>${i + 1}</button></td>`;
    if ((blanks + i) % 7 === 6) cells += '</tr><tr role="row">';
  }
  const heads = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'].map(h => `<th scope="col" abbr="${h}">${h[0]}</th>`).join('');
  grid.innerHTML = `<thead><tr role="row">${heads}</tr></thead><tbody><tr role="row">${cells}</tr></tbody>`;
  monthEl.textContent = fmt(view + '-01', { month: 'long', year: 'numeric' });
  prevBtn.disabled = view <= monthOf(pair.ci.min);
  if (refocus) grid.querySelector(`[data-d="${focusDay}"]`)?.focus();
};

const renderDial = () => {
  const out = add(ci, nights);
  const limit = Math.max(1, freeRun(ci));
  arc.setAttribute('stroke-dasharray', `${nights} ${MAX}`);
  handle.style.transform = `rotate(${(360 * nights) / MAX}deg)`;
  if (numEl.textContent !== String(nights)) {
    numEl.textContent = nights;
    if (!reduce.matches) numEl.animate([{ transform: 'translateY(-35%)', opacity: 0.2 }, { transform: 'none', opacity: 1 }], { duration: 240, easing: 'cubic-bezier(.16,1,.3,1)' });
  }
  unitEl.textContent = nights === 1 ? 'night' : 'nights';
  dial.setAttribute('aria-valuemax', limit);
  dial.setAttribute('aria-valuenow', nights);
  dial.setAttribute('aria-valuetext', `${nights} ${nights === 1 ? 'night' : 'nights'}, checking out ${long(out)}`);
  sumEl.textContent = `${short(ci)} to ${short(out)}`;
};

const render = () => {
  renderGrid();
  renderDial();
};

// A quiet line about what is free, once the dates have settled.
let hintTimer, hintAbort;
const refreshHint = () => {
  clearTimeout(hintTimer);
  hintAbort?.abort();
  hintEl.textContent = pickingOut ? 'Now choose your check-out date, or turn the dial.' : '';
  if (pickingOut) return;
  hintTimer = setTimeout(async () => {
    hintAbort = new AbortController();
    const p = query();
    p.set('check_in', ci);
    p.set('check_out', add(ci, nights));
    try {
      const res = await fetch('api/availability.php?' + p, { signal: hintAbort.signal });
      const d = await res.json();
      if (!res.ok) { hintEl.textContent = d.error || ''; return; }
      if (!d.rooms.length) hintEl.textContent = 'Nothing is free for these dates.';
      else if (pair.room()) hintEl.textContent = 'Free for these dates.';
      else hintEl.textContent = `${d.rooms.length} room type${d.rooms.length > 1 ? 's' : ''} free, from ${d.from} a night.`;
    } catch (e) { /* the hint is only a courtesy */ }
  }, 250);
};

const announce = () => { statusEl.textContent = `Check in ${long(ci)}. ${nights} ${nights === 1 ? 'night' : 'nights'}. Check out ${long(add(ci, nights))}.`; };

const setNights = (n, quiet) => {
  const limit = Math.max(1, freeRun(ci));
  const wanted = Math.max(1, n);
  nights = Math.min(wanted, limit);
  pickingOut = false;
  render();
  refreshHint();
  if (wanted > limit && limit < MAX) hintEl.textContent = `Only ${limit} ${limit === 1 ? 'night is' : 'nights are'} free from this date.`;
  if (!quiet) announce();
};

const showMonth = m => {
  view = m;
  loadMonth(m);
  loadMonth(addMonths(m, 1));
  render();
};

const pick = d => {
  if (isOff(d)) return;
  focusDay = d;
  if (pickingOut && d > ci) {
    nights = diff(ci, d);
    pickingOut = false;
  } else {
    ci = d;
    nights = Math.min(nights, Math.max(1, freeRun(ci)));
    pickingOut = true;
  }
  render();
  refreshHint();
  announce();
};

const moveFocus = d => {
  const min = pair.ci.min;
  if (d < min) d = min;
  focusDay = d;
  if (monthOf(d) !== view) showMonth(monthOf(d));
  else renderGrid();
  grid.querySelector(`[data-d="${d}"]`)?.focus();
};

grid.addEventListener('click', e => {
  const b = e.target.closest('.dp-d');
  if (b) pick(b.dataset.d);
});
grid.addEventListener('keydown', e => {
  const b = e.target.closest('.dp-d');
  if (!b) return;
  const d = b.dataset.d;
  const week = (parse(d).getUTCDay() + 6) % 7;
  const moves = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -7, ArrowDown: 7, Home: -week, End: 6 - week };
  if (e.key in moves) moveFocus(add(d, moves[e.key]));
  else if (e.key === 'PageUp' || e.key === 'PageDown') moveFocus(addMonths(monthOf(d), e.key === 'PageUp' ? -1 : 1) + d.slice(7, 10).replace(/-(29|30|31)$/, '-28'));
  else return;
  e.preventDefault();
});

dial.addEventListener('keydown', e => {
  const steps = { ArrowRight: 1, ArrowUp: 1, ArrowLeft: -1, ArrowDown: -1, PageUp: 7, PageDown: -7 };
  if (e.key in steps) setNights(nights + steps[e.key]);
  else if (e.key === 'Home') setNights(1);
  else if (e.key === 'End') setNights(MAX);
  else return;
  e.preventDefault();
});

// Dragging: the angle from 12 o'clock gives the nights. A big jump means the pointer crossed the top, so it is ignored.
const fromPointer = e => {
  const r = dial.getBoundingClientRect();
  let a = Math.atan2(e.clientX - (r.left + r.width / 2), (r.top + r.height / 2) - e.clientY);
  if (a < 0) a += Math.PI * 2;
  return Math.round((a / (Math.PI * 2)) * MAX) || MAX;
};
let dragging = false;
dial.addEventListener('pointerdown', e => {
  dragging = true;
  dial.setPointerCapture(e.pointerId);
  dial.classList.add('is-drag');
  setNights(fromPointer(e), true);
});
dial.addEventListener('pointermove', e => {
  if (!dragging) return;
  const n = fromPointer(e);
  if (Math.abs(n - nights) <= MAX / 2 && n !== nights) setNights(n, true);
});
const endDrag = () => {
  if (!dragging) return;
  dragging = false;
  dial.classList.remove('is-drag');
  announce();
};
dial.addEventListener('pointerup', endDrag);
dial.addEventListener('pointercancel', endDrag);

const close = () => {
  wrap.hidden = true;
  document.documentElement.classList.remove('dp-open');
  clearTimeout(hintTimer);
  hintAbort?.abort();
  opener?.focus();
  pair = null;
};

const done = () => {
  const out = add(ci, nights);
  pair.ci.value = ci;
  pair.ci.dispatchEvent(new Event('change', { bubbles: true }));
  pair.co.value = out;
  pair.co.dispatchEvent(new Event('change', { bubbles: true }));
  pair.sync();
  close();
};

wrap.addEventListener('click', e => {
  if (e.target === wrap) return close();
  const act = e.target.closest('[data-act]')?.dataset.act;
  if (act === 'close') close();
  else if (act === 'done') done();
  else if (act === 'prev') { showMonth(addMonths(view, -1)); focusDay = grid.querySelector('.dp-d:not(.is-off)')?.dataset.d || focusDay; renderGrid(); }
  else if (act === 'next') { showMonth(addMonths(view, 1)); focusDay = grid.querySelector('.dp-d:not(.is-off)')?.dataset.d || focusDay; renderGrid(); }
  else if (act === 'less') setNights(nights - 1);
  else if (act === 'more') setNights(nights + 1);
});

wrap.addEventListener('keydown', e => {
  if (e.key === 'Escape') { e.stopPropagation(); close(); return; }
  if (e.key !== 'Tab') return;
  const f = [...panel.querySelectorAll('button:not([disabled]),[tabindex="0"]')].filter(n => n.offsetParent !== null && n.tabIndex !== -1);
  const first = f[0], last = f[f.length - 1];
  if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
  else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
});

const open = (p, startOnOut, btn) => {
  pair = p;
  opener = btn;
  const min = p.ci.min || iso(new Date());
  ci = p.ci.value && p.ci.value >= min ? p.ci.value : min;
  const co = p.co.value && p.co.value > ci ? p.co.value : add(ci, 1);
  nights = Math.min(diff(ci, co), MAX);
  pickingOut = false;
  focusDay = startOnOut ? add(ci, nights) : ci;
  wrap.hidden = false;
  document.documentElement.classList.add('dp-open');
  numEl.textContent = '';
  showMonth(monthOf(focusDay));
  if (startOnOut) { pickingOut = true; render(); }
  refreshHint();
  grid.querySelector(`[data-d="${focusDay}"]`)?.focus();
};

// Swap the native inputs for buttons that show a readable date.
document.querySelectorAll('form').forEach(form => {
  const inEl = form.querySelector('input[type=date][name=check_in]');
  const outEl = form.querySelector('input[type=date][name=check_out]');
  if (!inEl || !outEl) return;
  const label = text => { const b = document.createElement('button'); b.type = 'button'; b.className = 'dp-field'; b.dataset.label = text; return b; };
  const btnIn = label('Check in'), btnOut = label('Check out');
  const sync = () => {
    btnIn.textContent = inEl.value ? short(inEl.value) : 'Choose';
    btnOut.textContent = outEl.value ? short(outEl.value) : 'Choose';
    btnIn.setAttribute('aria-label', 'Check in, ' + (inEl.value ? long(inEl.value) : 'not chosen') + '. Change dates');
    btnOut.setAttribute('aria-label', 'Check out, ' + (outEl.value ? long(outEl.value) : 'not chosen') + '. Change dates');
  };
  const p = {
    ci: inEl, co: outEl, sync, exclude: form.dataset.exclude === '1',
    adults: () => form.querySelector('[name=adults]')?.value || form.dataset.adults || '2',
    room: () => form.querySelector('[name=room]')?.value || form.dataset.room || '',
  };
  inEl.after(btnIn);
  outEl.after(btnOut);
  inEl.classList.add('dp-native');
  outEl.classList.add('dp-native');
  btnIn.addEventListener('click', () => open(p, false, btnIn));
  btnOut.addEventListener('click', () => open(p, true, btnOut));
  sync();
});
