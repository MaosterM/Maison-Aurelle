// Flex carousel: on touch devices the first tap expands a card, the second follows the link.
const rail = document.querySelector('.rail');
if (rail) {
  const cards = [...rail.children];
  const touch = matchMedia('(hover: none)');
  rail.addEventListener('click', e => {
    const a = e.target.closest('a');
    if (!a || !touch.matches || a.classList.contains('is-open')) return;
    e.preventDefault();
    cards.forEach(c => c.classList.toggle('is-open', c === a));
  });
}
