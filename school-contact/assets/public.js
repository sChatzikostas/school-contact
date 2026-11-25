(() => {
  document.querySelectorAll('.sc21-info').forEach((button) => {
    const close = () => { button.classList.remove('is-open'); button.setAttribute('aria-expanded', 'false'); };
    const restore = () => button.classList.remove('is-dismissed');
    button.addEventListener('click', () => { restore(); const open = button.classList.toggle('is-open'); button.setAttribute('aria-expanded', String(open)); });
    button.addEventListener('focus', restore);
    button.addEventListener('mouseenter', restore);
    button.addEventListener('blur', () => { close(); restore(); });
    button.addEventListener('keydown', (event) => { if (event.key === 'Escape') { event.preventDefault(); close(); button.classList.add('is-dismissed'); } });
  });
})();
