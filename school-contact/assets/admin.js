(() => {
  document.documentElement.classList.add('sc21-admin-enhanced');
  const normalize = (value) => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('el');
  const table = document.querySelector('.sc21-staff-table');
  const search = document.querySelector('#sc21-search');
  const legacyFilters = document.querySelector('.sc21-staff-filters');
  if (table && search && legacyFilters) {
    const rows = [...table.tBodies[0].rows].filter((row) => row.cells.length > 1);
    const filters = document.createElement('div');
    filters.className = 'sc21-live-filters';
    filters.innerHTML = '<button type="button" data-filter="all" class="is-active">Όλο το προσωπικό <span></span></button><button type="button" data-filter="available">Διαθέσιμοι <span></span></button><button type="button" data-filter="unavailable">Μη διαθέσιμοι <span></span></button>';
    legacyFilters.append(filters);
    const submit = legacyFilters.querySelector('[type="submit"]');
    const select = legacyFilters.querySelector('select');
    if (submit) submit.hidden = true;
    if (select) select.hidden = true;
    let availability = 'all';
    let timer;
    const apply = () => {
      const term = normalize(search.value.trim());
      const counts = { all: 0, available: 0, unavailable: 0 };
      rows.forEach((row) => {
        const available = row.querySelector('.is-available') !== null;
        const category = available ? 'available' : 'unavailable';
        counts.all += 1; counts[category] += 1;
        const visible = (!term || normalize(row.cells[0].textContent + ' ' + row.cells[2].textContent).includes(term)) && (availability === 'all' || availability === category);
        row.hidden = !visible;
      });
      filters.querySelectorAll('button').forEach((button) => { button.classList.toggle('is-active', button.dataset.filter === availability); button.querySelector('span').textContent = `(${counts[button.dataset.filter]})`; });
      let empty = table.tBodies[0].querySelector('.sc21-live-empty');
      if (!rows.some((row) => !row.hidden)) { if (!empty) { empty = document.createElement('tr'); empty.className = 'sc21-live-empty'; empty.innerHTML = '<td colspan="6">Δεν βρέθηκαν αποτελέσματα.</td>'; table.tBodies[0].append(empty); } } else if (empty) empty.remove();
    };
    search.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(apply, 250); });
    filters.addEventListener('click', (event) => { const button = event.target.closest('button[data-filter]'); if (!button) return; availability = button.dataset.filter; apply(); });
    apply();
  }
  const editor = document.querySelector('.sc21-staff-editor');
  document.querySelectorAll('.sc21-copy-shortcode').forEach((button) => {
    const feedback = document.querySelector('#sc21-copy-feedback');
    const copied = 'Το shortcode αντιγράφηκε.';
    const failed = 'Δεν ήταν δυνατή η αντιγραφή. Επιλέξτε και αντιγράψτε το shortcode χειροκίνητα.';
    button.addEventListener('click', async () => {
      try {
        await navigator.clipboard.writeText(button.dataset.sc21Copy || '');
        button.textContent = 'Αντιγράφηκε';
        if (feedback) feedback.textContent = copied;
      } catch (error) {
        if (feedback) feedback.textContent = failed;
      }
    });
  });
  document.querySelectorAll('.sc21-status').forEach((status) => { status.title = status.textContent.trim(); });
  document.querySelectorAll('.sc21-staff-table td:last-child').forEach((cell) => {
    cell.querySelectorAll('a, button').forEach((control) => {
      const label = control.textContent.trim();
      control.title = label;
      control.classList.remove('button-link', 'button-link-delete');
      control.classList.add('sc21-icon-action');
      const icon = label.includes('Επεξεργασία') ? 'dashicons-edit' : label.includes('Απενεργοποίηση') ? 'dashicons-remove' : 'dashicons-yes';
      control.innerHTML = '<span class="dashicons ' + icon + '" aria-hidden="true"></span><span>' + label + '</span>';
    });
  });
  if (editor && window.HTMLDialogElement) {
    const dialog = document.createElement('dialog');
    dialog.className = 'sc21-staff-dialog';
    dialog.setAttribute('aria-labelledby', 'sc21-staff-editor-title');
    const close = editor.querySelector('.sc21-dialog-close');
    dialog.append(editor); document.body.append(dialog);
    dialog.showModal();
    const first = dialog.querySelector('input:not([type="hidden"]), select'); if (first) first.focus();
    if (close) close.addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => { window.location.href = new URL(window.location.href).origin + new URL(window.location.href).pathname + '?page=sc21-school-communication&tab=staff'; });
  }
})();
