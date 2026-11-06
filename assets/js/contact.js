import './map.js';

const reduce = matchMedia('(prefers-reduced-motion: reduce)');

// Copy buttons: the clipboard API needs a secure context, so fall back to a hidden textarea.
const status = document.getElementById('copy-status');
const copyText = async value => {
  try {
    await navigator.clipboard.writeText(value);
    return true;
  } catch (err) {
    const t = document.createElement('textarea');
    t.value = value;
    t.setAttribute('readonly', '');
    t.className = 'sr-only';
    document.body.append(t);
    t.select();
    const ok = document.execCommand('copy');
    t.remove();
    return ok;
  }
};
document.querySelectorAll('[data-copy]').forEach(btn => {
  let timer;
  btn.addEventListener('click', async () => {
    const ok = await copyText(btn.dataset.copy);
    btn.textContent = ok ? 'Copied' : 'Failed';
    btn.classList.toggle('is-done', ok);
    status.textContent = ok ? btn.dataset.what + ' copied.' : 'Could not copy. Please select the text instead.';
    clearTimeout(timer);
    timer = setTimeout(() => { btn.textContent = 'Copy'; btn.classList.remove('is-done'); }, 2000);
  });
});

// FAQ: native <details> works alone; this adds the height animation and survives a click mid-animation.
const token = name => getComputedStyle(document.documentElement).getPropertyValue(name).trim();
document.querySelectorAll('.faq details').forEach(item => {
  const summary = item.querySelector('summary');
  let anim = null;
  let wantOpen = item.open;
  summary.addEventListener('click', e => {
    if (reduce.matches) { wantOpen = !item.open; return; }
    e.preventDefault();
    wantOpen = !wantOpen;
    const start = item.getBoundingClientRect().height;
    anim?.cancel();
    item.open = true;
    const end = wantOpen ? item.getBoundingClientRect().height : summary.getBoundingClientRect().height + (item.offsetHeight - item.clientHeight);
    const run = item.animate({ height: [start + 'px', end + 'px'] }, { duration: parseFloat(token('--dur-ui')) || 450, easing: token('--ease-snap') });
    anim = run;
    run.finished.then(() => { if (anim === run) { item.open = wantOpen; anim = null; } }).catch(() => { /* interrupted by another click */ });
  });
});

// "Talk to our concierge" opens the chat widget once it exists. Until then, and without JavaScript, the link goes to the form.
document.querySelector('[data-open-chat]')?.addEventListener('click', e => {
  if (!window.maConcierge) return;
  e.preventDefault();
  window.maConcierge.open();
});

// After sending, move focus to the confirmation so it is announced.
// The browser's jump to #inquiry clears focus during page load, so wait until that has happened.
const sent = document.querySelector('.sent');
if (sent) {
  const focusSent = () => setTimeout(() => sent.focus({ preventScroll: true }), 50);
  if (document.readyState === 'complete') focusSent();
  else addEventListener('load', focusSent, { once: true });
}
