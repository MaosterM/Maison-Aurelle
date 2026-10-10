// Live price preview on the extras step: instant local estimate, then the server's number replaces it.
const sum = document.getElementById('sum');
const form = document.getElementById('addons-form');
if (sum && form) {
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
      `<li><span>Service charge</span><b>${f(q.service)}</b></li><li><span>Tax</span><b>${f(q.tax)}</b></li>` +
      `<li class="tot"><span>Total</span><b>${f(q.total)}</b></li>`;
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
