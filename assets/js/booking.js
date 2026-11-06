// Booking steps 2 and 3: a rolling total, a collapsible summary on small screens, and the hold countdown.

// Odometer: digits roll to their new value. Screen readers get the plain amount instead.
const odometer = host => {
  const said = document.createElement('span');
  said.className = 'sr-only';
  const shown = document.createElement('span');
  shown.className = 'odo';
  shown.setAttribute('aria-hidden', 'true');
  let last = '';

  const build = text => {
    shown.replaceChildren(...[...text].map((ch, i) => {
      const cell = document.createElement('span');
      cell.className = 'odo-c';
      if (!/\d/.test(ch)) { cell.textContent = ch; return cell; }
      const strip = document.createElement('span');
      strip.className = 'odo-s';
      strip.style.setProperty('--i', text.length - i);
      strip.append(...'0123456789'.split('').map(n => { const d = document.createElement('i'); d.textContent = n; return d; }));
      cell.append(strip);
      return cell;
    }));
  };
  const roll = text => {
    [...text].forEach((ch, i) => { if (/\d/.test(ch)) shown.children[i].firstChild.style.setProperty('--d', ch); });
  };

  const set = (text, animate = true) => {
    said.textContent = text;
    if (text === last) return;
    if (text.length !== last.length) {
      build(text);
      if (animate) { void shown.offsetWidth; requestAnimationFrame(() => roll(text)); setTimeout(() => roll(text), 60); }
      else roll(text);
    } else {
      roll(text);
    }
    last = text;
  };
  set(host.textContent.trim(), false);
  host.replaceChildren(said, shown);
  return set;
};

const sum = document.getElementById('sum');
if (sum) {
  const total = odometer(document.getElementById('sum-total'));

  // On small screens the price lines fold away so the extras are on the first screen. The total stays visible.
  const details = sum.querySelector('.sum-d');
  const small = matchMedia('(max-width: 900px)');
  const fold = () => { details.open = !small.matches; };
  fold();
  small.addEventListener('change', fold);

  // Live price preview on the extras step: instant local estimate, then the server's number replaces it.
  const form = document.getElementById('addons-form');
  if (form) {
    const body = document.getElementById('sum-body');
    const d = sum.dataset;
    const f = n => d.sym + Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const esc = s => { const x = document.createElement('div'); x.textContent = s; return x.innerHTML; };
    const r2 = n => Math.round(n * 100) / 100;
    const checked = () => [...form.querySelectorAll('input[name="addons[]"]:checked')];

    const draw = q => {
      body.innerHTML =
        `<li><span>Room, ${q.nights} night${q.nights > 1 ? 's' : ''}</span><b>${f(q.room_total)}</b></li>` +
        q.addons.map(a => `<li><span>${esc(a.name)}</span><b>${f(a.amount)}</b></li>`).join('') +
        `<li><span>Service charge</span><b>${f(q.service)}</b></li><li><span>Tax</span><b>${f(q.tax)}</b></li>`;
      total(f(q.total));
    };

    const local = () => {
      const n = +d.nights, g = +d.adults, rt = +d.roomTotal;
      let add = 0;
      const addons = checked().map(c => {
        const m = { per_stay: 1, per_night: n, per_person: g, per_person_per_night: g * n }[c.dataset.type];
        const amount = r2(c.dataset.price * m);
        add += amount;
        return { name: c.dataset.name, amount };
      });
      const sub = rt + add, service = r2(sub * d.svc), tax = r2((sub + service) * d.tax);
      return { nights: n, room_total: rt, addons, service, tax, total: r2(sub + service + tax) };
    };

    let seq = 0;
    form.addEventListener('change', async () => {
      draw(local());
      const mine = ++seq;
      const p = new URLSearchParams({ room: d.room, check_in: d.in, check_out: d.out, adults: d.adults });
      checked().forEach(c => p.append('addons[]', c.value));
      try {
        const r = await fetch('api/quote.php?' + p);
        if (r.ok && mine === seq) draw(await r.json());
      } catch (e) { /* keep the local estimate */ }
    });
  }
}

// Hold countdown. Without JavaScript the page shows the time the selection expires, and the button still works.
const hold = document.getElementById('hold');
if (hold) {
  const label = document.getElementById('hold-label');
  const time = document.getElementById('hold-time');
  const status = document.getElementById('hold-status');
  const holdForm = document.getElementById('hold-form');
  const totalSeconds = +hold.dataset.total;
  let ends = Date.now() + +hold.dataset.left * 1000;
  let warned = false;
  let timer;

  const expire = () => {
    clearInterval(timer);
    hold.classList.add('is-low');
    hold.style.setProperty('--left', 0);
    label.textContent = 'Your selection has timed out.';
    time.textContent = '';
    const a = document.createElement('a');
    a.href = 'booking.php?step=1';
    a.textContent = 'Choose again';
    holdForm.replaceWith(a);
    status.textContent = 'Your selection has timed out. Please choose again.';
  };

  const tick = () => {
    const left = Math.ceil((ends - Date.now()) / 1000);
    if (left <= 0) return expire();
    time.textContent = String(Math.floor(left / 60)).padStart(2, '0') + ':' + String(left % 60).padStart(2, '0');
    hold.style.setProperty('--left', (left / totalSeconds).toFixed(4));
    const low = left <= 120;
    hold.classList.toggle('is-low', low);
    if (low && !warned) {
      warned = true;
      status.textContent = 'Two minutes left to keep your selection. Use the Keep my selection button for more time.';
    }
  };

  label.textContent = 'Your selection is saved for';
  time.setAttribute('role', 'timer');
  tick();
  timer = setInterval(tick, 1000);

  // Asking for more time must not reload the page, or anything typed on it would be lost.
  holdForm.addEventListener('submit', async e => {
    e.preventDefault();
    try {
      const res = await fetch(holdForm.action, { method: 'POST', body: new FormData(holdForm), headers: { Accept: 'application/json' } });
      const data = await res.json();
      if (data.expired) return expire();
      ends = Date.now() + data.left * 1000;
      warned = false;
      tick();
      status.textContent = 'Your selection is saved for another ' + Math.round(data.left / 60) + ' minutes.';
    } catch (err) {
      status.textContent = 'That did not work. Please try again.';
    }
  });
}
