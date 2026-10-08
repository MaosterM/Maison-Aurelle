const menu = document.getElementById('menu');
const btn = document.querySelector('.menu-btn');

if (menu && btn) {
  const root = document.documentElement;
  const close = menu.querySelector('.menu-close');
  const links = [...menu.querySelectorAll('.menu-list a')];
  const preview = menu.querySelector('.menu-preview span');

  const open = o => {
    menu.classList.toggle('is-open', o);
    root.classList.toggle('menu-open', o);
    btn.setAttribute('aria-expanded', o);
    menu.inert = !o;
    (o ? links[0] : btn).focus({ preventScroll: true });
  };

  btn.addEventListener('click', () => open(true));
  close.addEventListener('click', () => open(false));

  addEventListener('keydown', e => {
    if (!menu.classList.contains('is-open')) return;
    if (e.key === 'Escape') open(false);
    if (e.key === 'Tab') {            // keep focus inside the open menu
      const f = [close, ...links], i = f.indexOf(document.activeElement);
      if (e.shiftKey && i <= 0) { e.preventDefault(); f[f.length - 1].focus(); }
      else if (!e.shiftKey && i === f.length - 1) { e.preventDefault(); f[0].focus(); }
    }
  });

  links.forEach(a => {
    const show = () => { preview.textContent = a.dataset.tag; };
    a.addEventListener('mouseenter', show);
    a.addEventListener('focus', show);
    a.addEventListener('click', e => {
      if (new URL(a.href).pathname === location.pathname) { e.preventDefault(); open(false); }
    });
  });
  preview.textContent = links[0].dataset.tag;
}
