(() => {
  document.querySelectorAll('[data-sc21-tooltip-trigger]').forEach((button) => {
    const tooltip = document.getElementById(button.getAttribute('aria-describedby') || '');
    const field = button.closest('.sc21-recipient-field');
    const select = field?.querySelector('select');
    if (!tooltip || !field || !select) return;

    let closeTimer;
    let pointerActivation = false;
    let pointerType = '';
    let openedByActivation = false;
    const inside = (element) => Boolean(element && (button.contains(element) || tooltip.contains(element)));
    const position = () => {
      tooltip.classList.remove('is-below');
      if (tooltip.getBoundingClientRect().top < 8) tooltip.classList.add('is-below');
    };
    const setOpen = (open, activated = false) => {
      clearTimeout(closeTimer);
      tooltip.hidden = !open;
      button.setAttribute('aria-expanded', String(open));
      openedByActivation = open && activated;
      if (open) position();
    };
    const close = () => setOpen(false);
    const deferClose = (relatedTarget) => {
      if (inside(relatedTarget)) return;
      clearTimeout(closeTimer);
      closeTimer = window.setTimeout(() => {
        if (!inside(document.activeElement) && !tooltip.matches(':hover') && !button.matches(':hover')) close();
      }, 80);
    };

    button.addEventListener('pointerdown', (event) => {
      pointerActivation = true;
      pointerType = event.pointerType;
    });
    button.addEventListener('pointercancel', () => {
      pointerActivation = false;
      pointerType = '';
    });
    button.addEventListener('click', (event) => {
      event.preventDefault();
      setOpen(tooltip.hidden || !openedByActivation, true);
      pointerActivation = false;
      pointerType = '';
    });
    button.addEventListener('mouseenter', () => {
      if (pointerType !== 'touch' && tooltip.hidden) setOpen(true);
    });
    tooltip.addEventListener('mouseenter', () => { if (tooltip.hidden) setOpen(true); });
    button.addEventListener('mouseleave', (event) => deferClose(event.relatedTarget));
    tooltip.addEventListener('mouseleave', (event) => deferClose(event.relatedTarget));
    button.addEventListener('focus', () => { if (!pointerActivation && tooltip.hidden) setOpen(true); });
    button.addEventListener('blur', (event) => deferClose(event.relatedTarget));
    button.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') { event.preventDefault(); close(); }
    });
    select.addEventListener('focus', close);
    select.addEventListener('pointerdown', close);
    document.addEventListener('pointerdown', (event) => { if (!inside(event.target)) close(); });
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && !tooltip.hidden) close(); });
    window.addEventListener('resize', () => { if (!tooltip.hidden) position(); });
  });
  if (document.querySelector('[data-sc21-tooltip-trigger]')) document.documentElement.classList.add('sc21-tooltip-enhanced');
})();
