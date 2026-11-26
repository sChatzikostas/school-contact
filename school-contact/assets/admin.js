(() => {
  const admin = document.querySelector('.sc21-admin');
  const table = document.querySelector('.sc21-staff-table');
  const search = document.querySelector('#sc21-search');
  const form = document.querySelector('.sc21-staff-filters');
  const category = document.querySelector('#sc21-category-filter');
  const availabilitySelect = document.querySelector('#sc21-availability');
  const count = document.querySelector('.sc21-results-count');
  const clear = document.querySelector('.sc21-clear-filters');
  const editor = document.querySelector('.sc21-staff-editor');
  const stateScope = admin?.dataset.sc21StateScope || '';
  const stateKey = stateScope ? `sc21StaffUiState:v1:${window.location.origin}${window.location.pathname}:${stateScope}` : '';
  const transferAge = 15 * 60 * 1000;
  const sortKeys = ['name', 'category', 'details', 'email', 'availability'];
  const uuidPattern = /^[a-f0-9-]{36}$/i;
  let workflow = null;

  const removeTransfer = () => { if (!stateKey) return; try { sessionStorage.removeItem(stateKey); } catch (_) {} };
  const isStaffUrl = (url) => url.origin === window.location.origin && url.pathname === window.location.pathname && url.searchParams.get('page') === 'sc21-school-communication' && (url.searchParams.get('tab') || 'staff') === 'staff';

  if (table && search && form && category && availabilitySelect) {
    document.documentElement.classList.add('sc21-admin-enhanced');
    const rows = [...table.tBodies[0].querySelectorAll('tr[data-sc21-category]')];
    const region = document.querySelector('.sc21-staff-table-region');
    const categoryOptions = [...category.options].map((option) => option.value);
    const availabilityOptions = [...availabilitySelect.options].map((option) => option.value);
    const initialUrl = new URL(window.location.href);
    const normalize = (value) => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('el').replace(/ς/g, 'σ').trim();
    const validFocus = (focus) => {
      if (!focus || typeof focus !== 'object') return null;
      if (focus.kind === 'add' || focus.kind === 'search') return { kind: focus.kind };
      if (focus.kind === 'sort' && sortKeys.includes(focus.orderby)) return { kind: 'sort', orderby: focus.orderby };
      if (['edit', 'toggle', 'delete'].includes(focus.kind) && typeof focus.staffId === 'string' && uuidPattern.test(focus.staffId)) return { kind: focus.kind, staffId: focus.staffId };
      return null;
    };
    const destinationFromUrl = (url) => ({
      path: url.pathname,
      page: url.searchParams.get('page') || '',
      tab: url.searchParams.get('tab') || 'staff',
      category: categoryOptions.includes(url.searchParams.get('sc21_category')) ? url.searchParams.get('sc21_category') : 'all',
      availability: availabilityOptions.includes(url.searchParams.get('sc21_availability')) ? url.searchParams.get('sc21_availability') : 'all',
      orderby: sortKeys.includes(url.searchParams.get('sc21_orderby')) ? url.searchParams.get('sc21_orderby') : 'name',
      order: url.searchParams.get('sc21_order')?.toUpperCase() === 'DESC' ? 'DESC' : 'ASC',
      action: url.searchParams.get('sc21_action') || '',
      staffId: url.searchParams.get('staff_id') || '',
    });
    const validDestination = (destination) => destination && typeof destination === 'object' && destination.path === window.location.pathname && destination.page === 'sc21-school-communication' && destination.tab === 'staff' && categoryOptions.includes(destination.category) && availabilityOptions.includes(destination.availability) && sortKeys.includes(destination.orderby) && ['ASC', 'DESC'].includes(destination.order) && ['', 'add', 'edit'].includes(destination.action) && (destination.action === 'edit' ? uuidPattern.test(destination.staffId) : destination.staffId === '');
    const sameDestination = (first, second) => ['path', 'page', 'tab', 'category', 'availability', 'orderby', 'order', 'action', 'staffId'].every((key) => first[key] === second[key]);
    const staffDestination = (state) => ({ path: window.location.pathname, page: 'sc21-school-communication', tab: 'staff', category: state.category, availability: state.availability, orderby: state.orderby, order: state.order, action: '', staffId: '' });
    const readTransfer = () => {
      if (!stateKey) return null;
      let raw; let state;
      try { raw = sessionStorage.getItem(stateKey); state = raw ? JSON.parse(raw) : null; } catch (_) { removeTransfer(); return null; }
      if (!state || typeof state !== 'object' || state.version !== 1 || state.scope !== stateScope || !Number.isFinite(state.createdAt) || state.createdAt > Date.now() || Date.now() - state.createdAt > transferAge || typeof state.search !== 'string' || state.search.length > 500 || !categoryOptions.includes(state.category) || !availabilityOptions.includes(state.availability) || !sortKeys.includes(state.orderby) || !['ASC', 'DESC'].includes(state.order) || !Number.isFinite(state.pageY) || state.pageY < 0 || !Number.isFinite(state.tableX) || state.tableX < 0 || !validDestination(state.destination)) { removeTransfer(); return null; }
      const focus = validFocus(state.focus);
      if (state.focus && !focus) { removeTransfer(); return null; }
      return { raw, state: { ...state, search: state.search.trim(), focus } };
    };
    const hasExplicit = (key) => initialUrl.searchParams.has(key);
    const serverOrderby = sortKeys.includes(initialUrl.searchParams.get('sc21_orderby')) ? initialUrl.searchParams.get('sc21_orderby') : 'name';
    const serverOrder = initialUrl.searchParams.get('sc21_order')?.toUpperCase() === 'DESC' ? 'DESC' : 'ASC';
    let transfer = readTransfer();
    if (transfer && (!sameDestination(transfer.state.destination, destinationFromUrl(initialUrl)) || hasExplicit('sc21_search') || (hasExplicit('sc21_category') && transfer.state.category !== category.value) || (hasExplicit('sc21_availability') && transfer.state.availability !== availabilitySelect.value) || (hasExplicit('sc21_orderby') && transfer.state.orderby !== serverOrderby) || (hasExplicit('sc21_order') && transfer.state.order !== serverOrder))) { removeTransfer(); transfer = null; }
    if (transfer && !hasExplicit('sc21_search') && !search.value) search.value = transfer.state.search;
    if (transfer && !hasExplicit('sc21_category')) category.value = transfer.state.category;
    let availability = transfer && !hasExplicit('sc21_availability') ? transfer.state.availability : (availabilitySelect.value || 'all');
    const filters = document.createElement('div');
    filters.className = 'sc21-live-filters';
    filters.innerHTML = '<button type="button" data-filter="all">Όλο το προσωπικό <span></span></button><button type="button" data-filter="available">Διαθέσιμοι <span></span></button><button type="button" data-filter="unavailable">Μη διαθέσιμοι <span></span></button>';
    form.append(filters);
    const submit = form.querySelector('[type="submit"]'); if (submit) submit.hidden = true;
    availabilitySelect.hidden = true;
    let timer;
    let returnFocus = transfer?.state.focus || null;
    const currentState = () => ({ search: search.value.trim(), category: category.value, availability, orderby: serverOrderby, order: serverOrder });
    const writeTransfer = (focus = returnFocus, nextState = {}, destination = null) => {
      if (!stateKey) return;
      const state = { ...currentState(), ...nextState };
      const payload = { version: 1, scope: stateScope, createdAt: Date.now(), ...state, destination: destination || staffDestination(state), pageY: Math.max(0, window.scrollY || 0), tableX: Math.max(0, region?.scrollLeft || 0), focus: validFocus(focus) };
      try { sessionStorage.setItem(stateKey, JSON.stringify(payload)); } catch (_) {}
    };
    const canonicalUrl = (href) => { const url = new URL(href, window.location.origin); url.searchParams.delete('sc21_search'); url.searchParams.set('sc21_category', category.value); url.searchParams.set('sc21_availability', availability); return url; };
    const navigationLinks = [...document.querySelectorAll('.sc21-sort-link, .sc21-staff-actions > a, .sc21-section-heading .button, .sc21-modal-footer-actions a')];
    const updateNavigationLinks = () => navigationLinks.forEach((link) => { link.href = canonicalUrl(link.href).toString(); });
    const focusFromLink = (link) => {
      const url = new URL(link.href, window.location.origin);
      if (link.matches('.sc21-sort-link')) return { kind: 'sort', orderby: url.searchParams.get('sc21_orderby') || 'name' };
      if (link.matches('.sc21-staff-actions > a')) return { kind: 'edit', staffId: url.searchParams.get('staff_id') || '' };
      if (link.matches('.sc21-section-heading .button')) return { kind: 'add' };
      return returnFocus;
    };
    const focusFromMutation = (mutation) => {
      const staffId = mutation.querySelector('input[name="staff_id"]')?.value || '';
      if (mutation.matches('.sc21-quick-action')) return { kind: 'toggle', staffId };
      if (mutation.matches('.sc21-delete')) return { kind: 'delete', staffId };
      return staffId ? { kind: 'edit', staffId } : { kind: 'add' };
    };
    const apply = () => {
      const term = normalize(search.value); let visibleCount = 0;
      rows.forEach((row) => {
        const matches = !term || normalize(row.dataset.sc21Search || '').includes(term);
        const visible = matches && (category.value === 'all' || row.dataset.sc21Category === category.value) && (availability === 'all' || (availability === 'available') === (row.querySelector('.is-available') !== null));
        row.hidden = !visible; if (visible) visibleCount += 1;
      });
      filters.querySelectorAll('button').forEach((button) => { const selected = button.dataset.filter === availability; button.classList.toggle('is-active', selected); button.setAttribute('aria-pressed', String(selected)); button.querySelector('span').textContent = `(${button.dataset.filter === 'all' ? rows.length : rows.filter((row) => (row.querySelector('.is-available') !== null) === (button.dataset.filter === 'available')).length})`; });
      if (count) count.textContent = `Εμφανίζονται ${visibleCount} από ${rows.length} εγγραφές`;
      const empty = table.tBodies[0].querySelector('.sc21-empty-state');
      if (empty) empty.hidden = visibleCount !== 0;
      if (clear) clear.hidden = !(search.value.trim() || category.value !== 'all' || availability !== 'all');
      updateNavigationLinks();
    };
    const isVisible = (element) => Boolean(element && !element.hidden && !element.closest('[hidden]') && element.getClientRects().length);
    const controlForFocus = (focus) => {
      if (!focus) return null;
      if (focus.kind === 'add') return document.querySelector('.sc21-section-heading .button');
      if (focus.kind === 'sort') return navigationLinks.find((link) => link.matches('.sc21-sort-link') && new URL(link.href, window.location.origin).searchParams.get('sc21_orderby') === focus.orderby) || null;
      if (focus.kind === 'edit') return navigationLinks.find((link) => link.matches('.sc21-staff-actions > a') && new URL(link.href, window.location.origin).searchParams.get('staff_id') === focus.staffId) || null;
      if (focus.kind === 'toggle') return [...document.querySelectorAll('.sc21-quick-action')].find((action) => action.querySelector('input[name="staff_id"]')?.value === focus.staffId)?.querySelector('button') || null;
      return null;
    };
    const restoreTransfer = () => {
      if (!transfer) return;
      window.requestAnimationFrame(() => {
        if (region) region.scrollLeft = Math.min(transfer.state.tableX, Math.max(0, region.scrollWidth - region.clientWidth));
        window.scrollTo(0, Math.min(transfer.state.pageY, Math.max(0, document.documentElement.scrollHeight - window.innerHeight)));
        window.requestAnimationFrame(() => {
          if (!editor) {
            const target = controlForFocus(returnFocus);
            const focusTarget = isVisible(target) ? target : (isVisible(search) ? search : null);
            if (focusTarget) { try { focusTarget.focus({ preventScroll: true }); } catch (_) { focusTarget.focus(); } }
          }
          try { if (sessionStorage.getItem(stateKey) === transfer.raw) sessionStorage.removeItem(stateKey); } catch (_) {}
        });
      });
    };
    workflow = {
      writeTransfer,
      returnToStaff: () => { const url = canonicalUrl(window.location.href); url.searchParams.delete('sc21_action'); url.searchParams.delete('staff_id'); return url; },
      returnFocus: () => returnFocus,
    };
    search.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(apply, 250); });
    category.addEventListener('change', apply);
    filters.addEventListener('click', (event) => { const button = event.target.closest('button[data-filter]'); if (!button) return; availability = button.dataset.filter; availabilitySelect.value = availability; apply(); });
    clear?.addEventListener('click', (event) => { event.preventDefault(); search.value = ''; category.value = 'all'; availability = 'all'; availabilitySelect.value = 'all'; removeTransfer(); apply(); });
    form.addEventListener('submit', (event) => { event.preventDefault(); apply(); });
    document.querySelectorAll('.sc21-quick-action, .sc21-delete, .sc21-staff-editor form').forEach((mutation) => mutation.addEventListener('submit', (event) => { if (event.defaultPrevented) return; returnFocus = validFocus(focusFromMutation(mutation)); writeTransfer(returnFocus); mutation.querySelectorAll('input[name="sc21_enhanced"]').forEach((input) => { input.value = '1'; }); mutation.querySelectorAll('input[name="sc21_search"]').forEach((input) => { input.remove(); }); ['sc21_category', 'sc21_availability'].forEach((name) => { const input = mutation.querySelector(`[name="${name}"]`) || document.createElement('input'); input.type = 'hidden'; input.name = name; input.value = name === 'sc21_category' ? category.value : availability; if (!input.parentNode) mutation.append(input); }); }));
    navigationLinks.forEach((link) => link.addEventListener('click', (event) => { const url = canonicalUrl(link.href); returnFocus = validFocus(focusFromLink(link)); writeTransfer(returnFocus, { orderby: sortKeys.includes(url.searchParams.get('sc21_orderby')) ? url.searchParams.get('sc21_orderby') : serverOrderby, order: url.searchParams.get('sc21_order')?.toUpperCase() === 'DESC' ? 'DESC' : 'ASC' }, destinationFromUrl(url)); event.preventDefault(); window.location.href = url.toString(); }));
    apply();
    restoreTransfer();
  }

  document.addEventListener('click', (event) => {
    const link = event.target.closest('a[href]');
    if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    let url; try { url = new URL(link.href, window.location.origin); } catch (_) { return; }
    if (!isStaffUrl(url)) removeTransfer();
  });
  document.querySelectorAll('.sc21-copy-shortcode').forEach((button) => { const feedback = document.querySelector('#sc21-copy-feedback'); button.addEventListener('click', async () => { try { await navigator.clipboard.writeText(button.dataset.sc21Copy || ''); button.textContent = 'Αντιγράφηκε'; if (feedback) feedback.textContent = 'Το shortcode αντιγράφηκε.'; } catch (_) { if (feedback) feedback.textContent = 'Δεν ήταν δυνατή η αντιγραφή. Επιλέξτε και αντιγράψτε το shortcode χειροκίνητα.'; } }); });
  document.querySelectorAll('.sc21-status').forEach((status) => { status.title = status.textContent.trim(); });
  document.querySelectorAll('.sc21-staff-table td:last-child').forEach((cell) => cell.querySelectorAll('a, button').forEach((control) => { const label = control.textContent.trim(); control.title = label; control.classList.remove('button-link', 'button-link-delete'); control.classList.add('sc21-icon-action'); const icon = label.includes('Επεξεργασία') ? 'dashicons-edit' : label.includes('Απενεργοποίηση') ? 'dashicons-remove' : 'dashicons-yes'; control.innerHTML = '<span class="dashicons ' + icon + '" aria-hidden="true"></span><span>' + label + '</span>'; }));
  if (editor && window.HTMLDialogElement) { const dialog = document.createElement('dialog'); dialog.className = 'sc21-staff-dialog'; dialog.setAttribute('aria-labelledby', 'sc21-staff-editor-title'); const close = editor.querySelector('.sc21-dialog-close'); dialog.append(editor); document.body.append(dialog); dialog.showModal(); const first = dialog.querySelector('input:not([type="hidden"]), select'); if (first) first.focus(); if (close) close.addEventListener('click', () => dialog.close()); dialog.addEventListener('close', () => { if (!workflow) return; workflow.writeTransfer(workflow.returnFocus()); window.location.href = workflow.returnToStaff().toString(); }); }
})();
