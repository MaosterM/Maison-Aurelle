// Leaflet loads only when the map nears the screen. If it cannot load, the address and link inside #map stay visible.
const el = document.getElementById('map');
const BASE = 'https://unpkg.com/leaflet@1.9.4/dist/';

const loadLeaflet = () => new Promise((resolve, reject) => {
  const css = document.createElement('link');
  css.rel = 'stylesheet';
  css.href = BASE + 'leaflet.css';
  css.integrity = 'sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=';
  css.crossOrigin = '';
  document.head.prepend(css);   // first in the cascade, so contact.css can restyle Leaflet's controls

  const js = document.createElement('script');
  js.src = BASE + 'leaflet.js';
  js.integrity = 'sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=';
  js.crossOrigin = '';
  js.onload = resolve;
  js.onerror = reject;
  document.head.append(js);
});

const text = s => { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; };

const draw = () => {
  const L = window.L;
  const { lat, lng, name, address } = el.dataset;
  const here = [+lat, +lng];
  const touch = matchMedia('(hover: none)').matches;

  // The page must keep scrolling: no wheel zoom, and on touch one finger scrolls instead of dragging the map.
  const map = L.map(el, { center: here, zoom: 16, zoomControl: false, scrollWheelZoom: false, dragging: !touch });
  L.control.zoom({ position: 'topright' }).addTo(map);
  map.attributionControl.setPrefix('<a href="https://leafletjs.com">Leaflet</a>');
  L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
  }).addTo(map);

  const icon = L.divIcon({ className: 'pin', html: '<i></i><i></i><b></b>', iconSize: [24, 24], iconAnchor: [12, 12], popupAnchor: [0, -13] });
  L.marker(here, { icon, title: name, alt: name }).addTo(map).bindPopup(`<b>${text(name)}</b>${text(address)}`);

  el.querySelector('.map-fallback')?.remove();
};

if (el) {
  const io = new IntersectionObserver(entries => {
    if (!entries.some(e => e.isIntersecting)) return;
    io.disconnect();
    loadLeaflet().then(draw).catch(() => { /* the fallback address stays on screen */ });
  }, { rootMargin: '400px 0px' });
  io.observe(el);
}
