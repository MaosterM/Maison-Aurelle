// Floating concierge. All text is inserted with textContent, so nothing from the model or the guest can become HTML.
const root = document.getElementById('chat');
if (root) {
  const KEY = 'ma-chat';
  const html = document.documentElement;
  const fab = document.getElementById('chat-open');
  const panel = document.getElementById('chat-panel');
  const log = document.getElementById('chat-log');
  const chipsEl = document.getElementById('chat-chips');
  const status = document.getElementById('chat-status');
  const form = document.getElementById('chat-form');
  const input = document.getElementById('chat-input');
  const send = form.querySelector('.chat-send');
  const live = root.dataset.live === '1';

  const START_CHIPS = ['Show me your rooms', 'Spa and gym hours', 'How do I cancel?'];
  const GREETING = live
    ? 'Welcome to Maison Aurelle. Tell me your dates and I can check rooms and prices, or ask me anything about your stay.'
    : 'Welcome to Maison Aurelle. I can answer questions about rooms, check-in, payment and the spa.';

  let items = [];   // what is on screen: { role: 'bot'|'me', text, cards }
  let chips = START_CHIPS;
  let pending = false;

  const el = (tag, className, text) => {
    const n = document.createElement(tag);
    if (className) n.className = className;
    if (text !== undefined) n.textContent = text;
    return n;
  };

  const save = () => {
    try { sessionStorage.setItem(KEY, JSON.stringify({ items: items.slice(-40), chips })); } catch (e) { /* private mode: history lasts for this page */ }
  };

  const toBottom = () => { log.scrollTop = log.scrollHeight; };

  const roomsCard = c => {
    const wrap = el('div', 'cards');
    if (c.title) wrap.append(el('p', 'cards-title', c.title));
    c.rooms.forEach(r => {
      const a = el('a', 'rc');
      a.href = r.url;
      const img = el('img');
      img.src = r.image;
      img.alt = '';
      img.loading = 'lazy';
      const d = el('div');
      d.append(el('b', '', r.name), el('small', '', r.meta), el('span', 'p', r.price));
      if (r.total) d.append(el('small', '', r.total));
      if (r.left) d.append(el('small', '', r.left));
      a.append(img, d);
      wrap.append(a);
    });
    return wrap;
  };

  const quoteCard = c => {
    const q = el('div', 'qc');
    q.append(el('h3', '', c.title), el('p', '', c.sub));
    const ul = el('ul');
    (c.lines || []).forEach(([label, amount]) => {
      const li = el('li');
      li.append(el('span', '', label), el('span', '', amount));
      ul.append(li);
    });
    (c.extras || []).forEach(name => {
      const li = el('li');
      li.append(el('span', '', name), el('span', '', 'Added'));
      ul.append(li);
    });
    if (c.total) {
      const li = el('li', 'tot');
      li.append(el('span', '', 'Total'), el('span', '', c.total));
      ul.append(li);
    }
    if (ul.children.length) q.append(ul);
    const a = el('a', 'btn btn--sm', c.cta);
    a.href = c.url;
    q.append(a);
    return q;
  };

  const draw = (item, animate) => {
    const b = el('div', 'bub ' + (item.role === 'me' ? 'bub--me' : 'bub--bot'), item.text);
    if (animate) b.classList.add('is-new');
    log.append(b);
    (item.cards || []).forEach(c => {
      const n = c.type === 'rooms' ? roomsCard(c) : quoteCard(c);
      if (animate) n.classList.add('is-new');
      log.append(n);
    });
    return b;
  };

  const drawChips = () => {
    chipsEl.replaceChildren(...chips.map(t => {
      const b = el('button', 'chip', t);
      b.type = 'button';
      b.addEventListener('click', () => ask(t));
      return b;
    }));
  };

  const start = () => {
    items = [{ role: 'bot', text: GREETING }];
    chips = START_CHIPS;
    log.replaceChildren();
    draw(items[0], false);
    drawChips();
    save();
  };

  const restore = () => {
    try {
      const s = JSON.parse(sessionStorage.getItem(KEY));
      if (s && Array.isArray(s.items) && s.items.length) {
        items = s.items;
        chips = Array.isArray(s.chips) ? s.chips : [];
        items.forEach(i => draw(i, false));
        drawChips();
        return;
      }
    } catch (e) { /* unreadable history: start over */ }
    start();
  };

  const post = (fields, signal) => fetch('api/chat.php', {
    method: 'POST',
    body: new URLSearchParams({ csrf: root.dataset.csrf, ...fields }),
    signal,
  });

  async function ask(text) {
    const message = text.trim();
    if (!message || pending) return;
    pending = true;
    send.setAttribute('aria-disabled', 'true');
    input.value = '';
    fit();
    const mine = { role: 'me', text: message };
    items.push(mine);
    draw(mine, true);
    chips = [];
    drawChips();
    save();

    const typing = el('div', 'typing');
    typing.setAttribute('aria-hidden', 'true');
    typing.append(el('i'), el('i'), el('i'));
    log.append(typing);
    status.textContent = 'Concierge is typing.';
    toBottom();

    let replyStart = null;
    const stop = new AbortController();
    const timer = setTimeout(() => stop.abort(), 60000);
    try {
      const res = await post({ message }, stop.signal);
      const data = await res.json();
      if (!res.ok) throw Object.assign(new Error(data.error), { fromServer: true });
      const bot = { role: 'bot', text: data.reply, cards: data.cards || [] };
      typing.remove();
      items.push(bot);
      const shown = draw(bot, true);
      chips = data.chips || [];
      drawChips();
      save();
      replyStart = shown;
    } catch (err) {
      typing.remove();
      // A known message from the server is shown as is. The guest's words go back in the box so they can retry.
      log.append(el('div', 'bub bub--note is-new', err.fromServer ? err.message : 'I could not reach the front desk just now. Please try again, or call us.'));
      items.pop();
      save();
      input.value = message;
      fit();
    } finally {
      clearTimeout(timer);
      status.textContent = '';
      pending = false;
      send.removeAttribute('aria-disabled');
      // Start reading at the top of the answer; a list of room cards can be taller than the panel.
      if (replyStart) log.scrollTop = replyStart.offsetTop - log.offsetTop - 12;
      else toBottom();
    }
  }

  // Grow with the message, up to the limit set in CSS.
  const fit = () => {
    input.style.height = 'auto';
    input.style.height = input.scrollHeight + input.offsetHeight - input.clientHeight + 'px';
  };
  input.addEventListener('input', fit);

  form.addEventListener('submit', e => {
    e.preventDefault();
    ask(input.value);
  });
  input.addEventListener('keydown', e => {
    if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) {
      e.preventDefault();
      ask(input.value);
    }
  });

  const setOpen = o => {
    root.classList.toggle('is-open', o);
    html.classList.toggle('chat-open', o);
    fab.setAttribute('aria-expanded', o);
    panel.inert = !o;
    if (o) {
      toBottom();
      // On touch, focusing the box would throw up the keyboard over the conversation.
      (matchMedia('(hover: none)').matches ? log : input).focus({ preventScroll: true });
    } else {
      fab.focus({ preventScroll: true });
    }
  };

  fab.addEventListener('click', () => setOpen(true));
  document.getElementById('chat-close').addEventListener('click', () => setOpen(false));
  document.getElementById('chat-reset').addEventListener('click', async () => {
    if (pending) return;
    start();
    input.focus();
    try { await post({ do: 'reset' }); } catch (e) { /* the server history simply ages out */ }
  });

  // Keep focus inside while open; Esc closes.
  addEventListener('keydown', e => {
    if (!root.classList.contains('is-open')) return;
    if (e.key === 'Escape') { setOpen(false); return; }
    if (e.key !== 'Tab') return;
    const f = [...panel.querySelectorAll('a[href],button,textarea,[tabindex="0"]')].filter(n => n.offsetParent !== null);
    const first = f[0], last = f[f.length - 1];
    if (e.shiftKey && (document.activeElement === first || !panel.contains(document.activeElement))) { e.preventDefault(); last.focus(); }
    else if (!e.shiftKey && (document.activeElement === last || !panel.contains(document.activeElement))) { e.preventDefault(); first.focus(); }
  });

  // Lift the button so it never sits on the booking bar or the footer controls.
  const blockers = () => [...document.querySelectorAll('.booking-bar,.site-footer')];
  let queued = false;
  const lift = () => {
    queued = false;
    const gap = parseFloat(getComputedStyle(fab).bottom) || 16;
    const restTop = innerHeight - gap - fab.offsetHeight, restLeft = innerWidth - parseFloat(getComputedStyle(fab).right) - fab.offsetWidth;
    let raise = 0;
    blockers().forEach(b => {
      const r = b.getBoundingClientRect();
      if (r.width && r.top < innerHeight - gap && r.bottom > restTop && r.right > restLeft) raise = Math.max(raise, innerHeight - gap - r.top + 12);
    });
    fab.style.setProperty('--chat-lift', Math.round(raise) + 'px');
  };
  const queueLift = () => { if (!queued) { queued = true; requestAnimationFrame(lift); } };
  addEventListener('scroll', queueLift, { passive: true });
  addEventListener('resize', queueLift);
  document.addEventListener('animationend', lift);   // the booking bar snaps up after load
  addEventListener('load', lift);
  document.fonts?.ready.then(lift);   // web fonts can move the bar, and reduced motion has no animationend
  lift();

  // Lets the Contact page's "Talk to our concierge" button open the widget.
  window.maConcierge = { open: () => setOpen(true), close: () => setOpen(false) };

  restore();
}
