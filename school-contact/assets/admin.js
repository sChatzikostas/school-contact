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
  let editorProtection = null;
  const isWordPressEmail = (value) => {
    const email = String(value || '').trim();
    const at = email.indexOf('@');
    if (email.length < 6 || at < 1 || email.indexOf('@', at + 1) !== -1) return false;
    const local = email.slice(0, at); const domain = email.slice(at + 1);
    if (!/^[a-z0-9!#$%&'*+/=?^_`{|}~-]+(?:\.[a-z0-9!#$%&'*+/=?^_`{|}~-]+)*$/i.test(local)) return false;
    if (domain.length < 4 || !domain.includes('.') || domain.trim() !== domain || domain.startsWith('.') || domain.endsWith('.')) return false;
    return domain.split('.').every((subdomain) => /^[a-z0-9-]+$/i.test(subdomain) && !subdomain.startsWith('-') && !subdomain.endsWith('-'));
  };

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
    const editorForm = editor?.querySelector('form');
    if (editorForm) {
      const summary = editorForm.querySelector('#sc21-editor-validation-summary');
      const fields = {
        full_name: { control: editorForm.querySelector('#sc21-full-name'), message: 'Συμπληρώστε το ονοματεπώνυμο.' },
        category: { control: editorForm.querySelector('#sc21-category'), message: 'Επιλέξτε έγκυρη κατηγορία.' },
        specialty: { control: editorForm.querySelector('#sc21-specialty'), message: 'Συμπληρώστε την ειδικότητα.' },
        official_email: { control: editorForm.querySelector('#sc21-official-email'), message: 'Εισαγάγετε μια έγκυρη διεύθυνση email.' },
        alternative_email: { control: editorForm.querySelector('#sc21-alternative-email'), message: 'Εισαγάγετε μια έγκυρη διεύθυνση email.' },
      };
      let records = []; let warningsActive = false;
      try { const parsed = JSON.parse(editorForm.dataset.sc21StaffRecords || '[]'); records = Array.isArray(parsed) ? parsed.filter((record) => record && typeof record === 'object') : []; } catch (_) {}
      const collapse = (value) => String(value || '').trim().replace(/\s+/g, ' ');
      const normalizeName = (value) => normalize(collapse(value));
      const normalizeEmail = (value) => String(value || '').trim().toLocaleLowerCase('en-US');
      const currentId = editorForm.querySelector('[name="staff_id"]')?.value || '';
      const syncDescriptions = (field) => {
        const ids = [field.matches('#sc21-official-email, #sc21-alternative-email') ? 'sc21-contact-email-help' : '', `${field.id}-error`, `${field.id}-warning`].filter((id) => id && editorForm.querySelector(`#${id}`));
        if (ids.length) field.setAttribute('aria-describedby', ids.join(' ')); else field.removeAttribute('aria-describedby');
      };
      const clearField = (key) => {
        const field = fields[key]?.control; if (!field) return;
        field.removeAttribute('aria-invalid');
        editorForm.querySelector(`#${field.id}-error`)?.remove();
        syncDescriptions(field);
      };
      const showField = (key, message) => {
        const field = fields[key]?.control; if (!field) return;
        const id = `${field.id}-error`; let error = editorForm.querySelector(`#${id}`);
        if (!error) { error = document.createElement('span'); error.id = id; error.className = 'sc21-field-error'; field.insertAdjacentElement('afterend', error); }
        error.textContent = message; field.setAttribute('aria-invalid', 'true'); syncDescriptions(field);
      };
      const clearWarnings = () => Object.keys(fields).forEach((key) => {
        const field = fields[key].control; editorForm.querySelector(`#${field.id}-warning`)?.remove(); syncDescriptions(field);
      });
      const showWarning = (key, message) => {
        const field = fields[key]?.control; if (!field) return;
        const id = `${field.id}-warning`; let warning = editorForm.querySelector(`#${id}`);
        if (!warning) { warning = document.createElement('span'); warning.id = id; warning.className = 'sc21-field-warning'; field.insertAdjacentElement('afterend', warning); }
        warning.textContent = message; syncDescriptions(field);
      };
      const recordValue = (record, key) => String(record[key] || '');
      const currentRecord = () => ({
        full_name: fields.full_name.control.value,
        category: fields.category.control.value,
        specialty: fields.specialty.control.value,
        classroom: editorForm.querySelector('#sc21-classroom')?.value || '',
        official_email: fields.official_email.control.value,
        alternative_email: fields.alternative_email.control.value,
        active: Boolean(editorForm.querySelector('[name="active"]')?.checked),
      });
      const sameRecord = (current, record) => normalizeName(current.full_name) === normalizeName(recordValue(record, 'full_name'))
        && current.category === recordValue(record, 'category')
        && collapse(current.specialty) === collapse(recordValue(record, 'specialty'))
        && collapse(current.classroom) === collapse(recordValue(record, 'classroom'))
        && normalizeEmail(current.official_email) === normalizeEmail(recordValue(record, 'official_email'))
        && normalizeEmail(current.alternative_email) === normalizeEmail(recordValue(record, 'alternative_email'))
        && current.active === Boolean(record.active);
      const updateWarnings = () => {
        clearWarnings();
        const current = currentRecord(); const otherRecords = records.filter((record) => recordValue(record, 'id') !== currentId);
        const official = normalizeEmail(current.official_email); const alternative = normalizeEmail(current.alternative_email);
        const identical = otherRecords.some((record) => sameRecord(current, record));
        if (normalizeName(current.full_name) && identical) {
          showWarning('full_name', 'Υπάρχει ήδη καταχώριση με τα ίδια στοιχεία. Ελέγξτε μήπως πρόκειται για διπλή καταχώριση.');
        } else if (normalizeName(current.full_name) && otherRecords.some((record) => normalizeName(recordValue(record, 'full_name')) === normalizeName(current.full_name))) {
          showWarning('full_name', 'Υπάρχει ήδη μέλος προσωπικού με το ίδιο ονοματεπώνυμο.');
        }
        if (official && alternative && official === alternative) {
          showWarning('official_email', 'Έχετε δηλώσει την ίδια διεύθυνση και στα δύο πεδία. Το μήνυμα θα αποσταλεί μία φορά.');
        } else {
          if (official && otherRecords.some((record) => [normalizeEmail(recordValue(record, 'official_email')), normalizeEmail(recordValue(record, 'alternative_email'))].includes(official))) showWarning('official_email', 'Η διεύθυνση χρησιμοποιείται ήδη από άλλο μέλος προσωπικού.');
          if (alternative && otherRecords.some((record) => [normalizeEmail(recordValue(record, 'official_email')), normalizeEmail(recordValue(record, 'alternative_email'))].includes(alternative))) showWarning('alternative_email', 'Η διεύθυνση χρησιμοποιείται ήδη από άλλο μέλος προσωπικού.');
        }
      };
      const validateEditor = (moveFocus) => {
        const errors = new Map();
        const name = fields.full_name.control; const specialty = fields.specialty.control; const categoryControl = fields.category.control;
        const official = fields.official_email.control; const alternative = fields.alternative_email.control;
        if (!name.value.trim()) errors.set('full_name', fields.full_name.message);
        if (!specialty.value.trim()) errors.set('specialty', fields.specialty.message);
        if (![...categoryControl.options].some((option) => option.value === categoryControl.value)) errors.set('category', fields.category.message);
        if (official.value.trim() && !isWordPressEmail(official.value)) errors.set('official_email', fields.official_email.message);
        if (alternative.value.trim() && !isWordPressEmail(alternative.value)) errors.set('alternative_email', fields.alternative_email.message);
        if (editorForm.querySelector('[name="active"]')?.checked && !official.value.trim() && !alternative.value.trim()) errors.set('official_email', 'Ένα διαθέσιμο μέλος προσωπικού χρειάζεται τουλάχιστον μία έγκυρη διεύθυνση email.');
        Object.keys(fields).forEach(clearField); errors.forEach((message, key) => showField(key, message));
        if (summary) summary.hidden = errors.size === 0;
        editorForm.dataset.sc21Validated = errors.size ? 'true' : '';
        if (warningsActive || moveFocus) updateWarnings();
        if (errors.size && moveFocus) {
          const first = fields[errors.keys().next().value]?.control;
          if (first) { first.scrollIntoView({ block: 'nearest' }); first.focus({ preventScroll: true }); }
        }
        return errors.size === 0;
      };
      editorForm.addEventListener('submit', (event) => { warningsActive = true; if (!validateEditor(true)) event.preventDefault(); });
      editorForm.addEventListener('input', () => { if (editorForm.dataset.sc21Validated === 'true' || (summary && !summary.hidden)) validateEditor(false); else if (warningsActive) updateWarnings(); });
      editorForm.addEventListener('change', () => { if (editorForm.dataset.sc21Validated === 'true' || (summary && !summary.hidden)) validateEditor(false); else if (warningsActive) updateWarnings(); });
      editorForm.addEventListener('focusout', (event) => { if (Object.values(fields).some(({ control }) => control === event.target)) { warningsActive = true; updateWarnings(); } });
      const editable = [fields.full_name.control, fields.category.control, fields.specialty.control, editorForm.querySelector('#sc21-classroom'), fields.official_email.control, fields.alternative_email.control, editorForm.querySelector('[name="active"]')].filter(Boolean);
      const snapshot = () => editable.map((control) => control.type === 'checkbox' ? control.checked : control.value);
      const initialSnapshot = snapshot();
      let submissionAccepted = false;
      let beforeUnloadAttached = false;
      const dirty = () => snapshot().some((value, index) => value !== initialSnapshot[index]);
      const beforeUnload = (event) => { event.preventDefault(); event.returnValue = ''; };
      const syncProtection = () => {
        const shouldProtect = !submissionAccepted && dirty();
        if (shouldProtect && !beforeUnloadAttached) { window.addEventListener('beforeunload', beforeUnload); beforeUnloadAttached = true; }
        if (!shouldProtect && beforeUnloadAttached) { window.removeEventListener('beforeunload', beforeUnload); beforeUnloadAttached = false; }
        return shouldProtect;
      };
      editable.forEach((control) => { control.addEventListener('input', syncProtection); control.addEventListener('change', syncProtection); });
      editorForm.addEventListener('submit', (event) => {
        if (event.defaultPrevented) { syncProtection(); return; }
        submissionAccepted = true;
        syncProtection();
      });
      editorProtection = {
        isDirty: () => syncProtection(),
        allowNavigation: () => { submissionAccepted = true; syncProtection(); },
      };
    }
    document.querySelectorAll('.sc21-quick-action, .sc21-delete, .sc21-staff-editor form').forEach((mutation) => mutation.addEventListener('submit', (event) => { if (event.defaultPrevented) return; returnFocus = validFocus(focusFromMutation(mutation)); writeTransfer(returnFocus); mutation.querySelectorAll('input[name="sc21_enhanced"]').forEach((input) => { input.value = '1'; }); mutation.querySelectorAll('input[name="sc21_search"]').forEach((input) => { input.remove(); }); ['sc21_category', 'sc21_availability'].forEach((name) => { const input = mutation.querySelector(`[name="${name}"]`) || document.createElement('input'); input.type = 'hidden'; input.name = name; input.value = name === 'sc21_category' ? category.value : availability; if (!input.parentNode) mutation.append(input); }); }));
    navigationLinks.forEach((link) => link.addEventListener('click', (event) => { const url = canonicalUrl(link.href); returnFocus = validFocus(focusFromLink(link)); writeTransfer(returnFocus, { orderby: sortKeys.includes(url.searchParams.get('sc21_orderby')) ? url.searchParams.get('sc21_orderby') : serverOrderby, order: url.searchParams.get('sc21_order')?.toUpperCase() === 'DESC' ? 'DESC' : 'ASC' }, destinationFromUrl(url)); event.preventDefault(); window.location.href = url.toString(); }));
    apply();
    restoreTransfer();
  }

  const settingsForm = document.querySelector('#sc21-general-settings-form');
  if (settingsForm) {
    const summary = settingsForm.querySelector('#sc21-settings-validation-summary');
    const fields = {
      school_name: { control: settingsForm.querySelector('#sc21-school-name'), message: 'Συμπληρώστε την ονομασία της σχολικής μονάδας.', maxLength: 150 },
      principal_name: { control: settingsForm.querySelector('#sc21-principal-name'), message: 'Το ονοματεπώνυμο διευθυντή / διευθύντριας είναι πολύ μεγάλο.', maxLength: 120 },
      admin_email: { control: settingsForm.querySelector('#sc21-admin-email'), message: 'Εισαγάγετε μια έγκυρη διεύθυνση email.', maxLength: 254 },
    };
    const baseDescriptions = new Map(Object.values(fields).map(({ control }) => [control, (control.getAttribute('aria-describedby') || '').split(/\s+/).filter((id) => id && id !== `${control.id}-error`)]));
    const syncDescriptions = (field) => {
      const ids = [...(baseDescriptions.get(field) || [])];
      if (settingsForm.querySelector(`#${field.id}-error`)) ids.push(`${field.id}-error`);
      if (ids.length) field.setAttribute('aria-describedby', ids.join(' ')); else field.removeAttribute('aria-describedby');
    };
    const clearField = (key) => {
      const field = fields[key]?.control; if (!field) return;
      field.removeAttribute('aria-invalid');
      settingsForm.querySelector(`#${field.id}-error`)?.remove();
      syncDescriptions(field);
    };
    const showField = (key, message) => {
      const field = fields[key]?.control; if (!field) return;
      const id = `${field.id}-error`; let error = settingsForm.querySelector(`#${id}`);
      if (!error) { error = document.createElement('span'); error.id = id; error.className = 'sc21-field-error'; field.insertAdjacentElement('afterend', error); }
      error.textContent = message; field.setAttribute('aria-invalid', 'true'); syncDescriptions(field);
    };
    const textLength = (value) => Array.from(String(value || '')).length;
    const validateSettings = (moveFocus) => {
      const errors = new Map();
      const school = fields.school_name.control; const principal = fields.principal_name.control; const email = fields.admin_email.control;
      if (!school.value.trim()) errors.set('school_name', fields.school_name.message);
		else if (textLength(school.value) > fields.school_name.maxLength) errors.set('school_name', 'Η ονομασία της σχολικής μονάδας δεν μπορεί να υπερβαίνει τους 150 χαρακτήρες.');
      if (textLength(principal.value) > fields.principal_name.maxLength) errors.set('principal_name', fields.principal_name.message);
		if (!email.value.trim()) errors.set('admin_email', 'Συμπληρώστε το email διεύθυνσης της σχολικής μονάδας.');
      else if (textLength(email.value) > fields.admin_email.maxLength) errors.set('admin_email', 'Η διεύθυνση email δεν μπορεί να υπερβαίνει τους 254 χαρακτήρες.');
      else if (email.value !== email.value.trim() || !isWordPressEmail(email.value)) errors.set('admin_email', fields.admin_email.message);
      Object.keys(fields).forEach(clearField); errors.forEach((message, key) => showField(key, message));
      if (summary) summary.hidden = errors.size === 0;
      settingsForm.dataset.sc21Validated = errors.size ? 'true' : '';
      if (errors.size && moveFocus) {
        const first = fields[errors.keys().next().value]?.control;
        if (first) { first.scrollIntoView({ block: 'nearest' }); first.focus({ preventScroll: true }); }
      }
      return errors.size === 0;
    };
    const editable = Object.values(fields).map(({ control }) => control).filter(Boolean);
    const snapshot = () => editable.map((control) => control.value);
    const initialSnapshot = snapshot();
    let submissionAccepted = false;
    let beforeUnloadAttached = false;
    const beforeUnload = (event) => { event.preventDefault(); event.returnValue = ''; };
    const syncProtection = () => {
      const dirty = snapshot().some((value, index) => value !== initialSnapshot[index]);
      const shouldProtect = !submissionAccepted && dirty;
      if (shouldProtect && !beforeUnloadAttached) { window.addEventListener('beforeunload', beforeUnload); beforeUnloadAttached = true; }
      if (!shouldProtect && beforeUnloadAttached) { window.removeEventListener('beforeunload', beforeUnload); beforeUnloadAttached = false; }
      return shouldProtect;
    };
    settingsForm.noValidate = true;
    settingsForm.addEventListener('submit', (event) => {
      if (!validateSettings(true)) { event.preventDefault(); syncProtection(); return; }
      submissionAccepted = true;
      syncProtection();
    });
    editable.forEach((control) => {
      const update = () => { if (settingsForm.dataset.sc21Validated === 'true' || (summary && !summary.hidden)) validateSettings(false); syncProtection(); };
      control.addEventListener('input', update);
      control.addEventListener('change', update);
    });
  }

  document.addEventListener('click', (event) => {
    const link = event.target.closest('a[href]');
    if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    let url; try { url = new URL(link.href, window.location.origin); } catch (_) { return; }
    if (!isStaffUrl(url)) removeTransfer();
  });
  const openHelpAnchor = () => {
    if (!window.location.hash) return;
    let target; try { target = document.querySelector(window.location.hash); } catch (_) { return; }
    if (target instanceof HTMLDetailsElement) target.open = true;
  };
  openHelpAnchor();
  window.addEventListener('hashchange', openHelpAnchor);
  document.querySelectorAll('.sc21-copy-shortcode').forEach((button) => { const feedback = document.getElementById(button.getAttribute('aria-describedby') || '') || document.querySelector('#sc21-copy-feedback'); const originalLabel = button.textContent.trim(); button.addEventListener('click', async () => { try { await navigator.clipboard.writeText(button.dataset.sc21Copy || ''); button.textContent = 'Αντιγράφηκε'; if (feedback) { feedback.textContent = 'Το shortcode αντιγράφηκε.'; feedback.classList.remove('is-error'); } } catch (_) { button.textContent = originalLabel; if (feedback) { feedback.textContent = 'Δεν ήταν δυνατή η αντιγραφή. Μπορείτε να αντιγράψετε τον σύντομο κωδικό χειροκίνητα.'; feedback.classList.add('is-error'); } } }); });
  document.querySelectorAll('.sc21-status').forEach((status) => { status.title = status.textContent.trim(); });
  document.querySelectorAll('.sc21-staff-table td:last-child').forEach((cell) => cell.querySelectorAll('a, button').forEach((control) => { const label = control.textContent.trim(); control.title = label; control.classList.remove('button-link', 'button-link-delete'); control.classList.add('sc21-icon-action'); const icon = label.includes('Επεξεργασία') ? 'dashicons-edit' : label.includes('Απενεργοποίηση') ? 'dashicons-remove' : 'dashicons-yes'; control.innerHTML = '<span class="dashicons ' + icon + '" aria-hidden="true"></span><span>' + label + '</span>'; }));
  if (editor && window.HTMLDialogElement) {
    const dialog = document.createElement('dialog');
    dialog.className = 'sc21-staff-dialog';
    dialog.setAttribute('aria-labelledby', 'sc21-staff-editor-title');
    const close = editor.querySelector('.sc21-dialog-close');
    const cancel = editor.querySelector('.sc21-modal-footer-actions a');
		const deleteForm = editor.querySelector('.sc21-delete');
		const deleteConfirmation = editor.querySelector('.sc21-delete-confirmation');
		const deleteTitle = editor.querySelector('.sc21-delete-confirmation-title');
		const deleteName = deleteConfirmation?.querySelector('.sc21-delete-confirmation-name');
		const deleteCancel = deleteConfirmation?.querySelector('.sc21-delete-cancel');
		const deleteConfirm = deleteConfirmation?.querySelector('.sc21-delete-confirm');
    const confirmation = document.createElement('dialog');
    confirmation.className = 'sc21-unsaved-dialog';
    confirmation.setAttribute('aria-labelledby', 'sc21-unsaved-title');
    confirmation.setAttribute('aria-describedby', 'sc21-unsaved-message');
    confirmation.innerHTML = '<div class="sc21-unsaved-dialog-content"><h2 id="sc21-unsaved-title">Μη αποθηκευμένες αλλαγές</h2><p id="sc21-unsaved-message">Έχετε πραγματοποιήσει αλλαγές που δεν έχουν αποθηκευτεί. Αν κλείσετε τη φόρμα, οι αλλαγές αυτές θα χαθούν.</p></div><div class="sc21-unsaved-dialog-actions"><button type="button" class="button button-primary sc21-unsaved-continue">Συνέχεια επεξεργασίας</button><button type="button" class="button sc21-unsaved-discard">Απόρριψη αλλαγών</button></div>';
    const continueEditing = confirmation.querySelector('.sc21-unsaved-continue');
    const discardChanges = confirmation.querySelector('.sc21-unsaved-discard');
    let confirmationOpen = false;
    let dismissalFocus = null;
		let deleteConfirmationOpen = false;
		let deleteFocus = null;
		let deleteSubmissionConfirmed = false;
    const isVisible = (element) => Boolean(element && !element.hidden && !element.closest('[hidden]') && element.getClientRects().length);
    const trapFocus = (container) => container.addEventListener('keydown', (event) => {
      if (event.key !== 'Tab') return;
      const controls = [...container.querySelectorAll('a[href], button:not([disabled]), input:not([type="hidden"]):not([disabled]), select:not([disabled]), textarea:not([disabled])')].filter(isVisible);
      const index = controls.indexOf(document.activeElement);
      if (event.shiftKey && index === 0) { event.preventDefault(); controls[controls.length - 1]?.focus(); }
      if (!event.shiftKey && index === controls.length - 1) { event.preventDefault(); controls[0]?.focus(); }
    });
    const closeEditor = () => { editorProtection?.allowNavigation(); dialog.close(); };
		const closeDeleteConfirmation = () => {
			if (!deleteConfirmationOpen) return;
			deleteConfirmationOpen = false;
			deleteConfirmation.hidden = true;
			if (deleteTitle) deleteTitle.hidden = true;
			editor.classList.remove('is-delete-confirming');
			dialog.setAttribute('aria-labelledby', 'sc21-staff-editor-title');
			if (isVisible(deleteFocus)) { try { deleteFocus.focus({ preventScroll: true }); } catch (_) { deleteFocus.focus(); } }
		};
		const requestDelete = () => {
			if (!deleteConfirmation || deleteConfirmationOpen) return;
			deleteConfirmationOpen = true;
			deleteFocus = deleteForm?.querySelector('button') || document.activeElement;
			if (deleteName) deleteName.textContent = deleteConfirmation.dataset.sc21DeleteName || '';
			if (deleteTitle) deleteTitle.hidden = false;
			deleteConfirmation.hidden = false;
			editor.classList.add('is-delete-confirming');
			dialog.setAttribute('aria-labelledby', 'sc21-delete-confirmation-title');
			deleteCancel?.focus();
		};
    const requestDismissal = () => {
      if (!editorProtection?.isDirty()) { closeEditor(); return; }
      if (confirmationOpen) return;
      confirmationOpen = true;
      dismissalFocus = document.activeElement;
      confirmation.showModal();
      continueEditing?.focus();
    };
    dialog.append(editor);
    document.body.append(dialog, confirmation);
    trapFocus(dialog);
    trapFocus(confirmation);
    dialog.showModal();
    const first = dialog.querySelector('input:not([type="hidden"]), select'); if (first) first.focus();
    if (close) close.addEventListener('click', () => {
      if (deleteConfirmationOpen) { closeDeleteConfirmation(); return; }
      requestDismissal();
    });
    if (cancel) cancel.addEventListener('click', (event) => { event.preventDefault(); event.stopImmediatePropagation(); requestDismissal(); }, true);
		dialog.addEventListener('cancel', (event) => { if (deleteConfirmationOpen) { event.preventDefault(); closeDeleteConfirmation(); return; } if (editorProtection?.isDirty()) { event.preventDefault(); requestDismissal(); } });
    confirmation.addEventListener('cancel', (event) => { event.preventDefault(); window.requestAnimationFrame(() => confirmation.close('continue')); });
    continueEditing?.addEventListener('click', () => confirmation.close('continue'));
    discardChanges?.addEventListener('click', () => confirmation.close('discard'));
    confirmation.addEventListener('close', () => {
      confirmationOpen = false;
      editorProtection?.isDirty();
      if (confirmation.returnValue === 'discard') { closeEditor(); return; }
      if (isVisible(dismissalFocus)) { try { dismissalFocus.focus({ preventScroll: true }); } catch (_) { dismissalFocus.focus(); } }
    });
		if (deleteForm) {
			deleteForm.onsubmit = null;
			deleteForm.addEventListener('submit', (event) => { event.preventDefault(); requestDelete(); });
		}
		deleteCancel?.addEventListener('click', closeDeleteConfirmation);
		deleteConfirm?.addEventListener('click', () => {
			if (!deleteForm || !deleteConfirmationOpen || deleteSubmissionConfirmed) return;
			deleteSubmissionConfirmed = true;
			deleteConfirm.disabled = true;
			editorProtection?.allowNavigation();
			deleteForm.submit();
		});
    dialog.addEventListener('close', () => { if (!workflow) return; editorProtection?.allowNavigation(); workflow.writeTransfer(workflow.returnFocus()); window.location.href = workflow.returnToStaff().toString(); });
  }
})();
