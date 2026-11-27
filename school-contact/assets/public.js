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

  const isWordPressEmail = (value) => {
    const email = String(value || '').trim();
    const at = email.indexOf('@');
    if (email.length < 6 || at < 1 || email.indexOf('@', at + 1) !== -1) return false;
    const local = email.slice(0, at); const domain = email.slice(at + 1);
    if (!/^[a-z0-9!#$%&'*+/=?^_`{|}~-]+(?:\.[a-z0-9!#$%&'*+/=?^_`{|}~-]+)*$/i.test(local)) return false;
    if (domain.length < 4 || !domain.includes('.') || domain.trim() !== domain || domain.startsWith('.') || domain.endsWith('.')) return false;
    return domain.split('.').every((part) => /^[a-z0-9-]+$/i.test(part) && !part.startsWith('-') && !part.endsWith('-'));
  };

  document.querySelectorAll('.sc21-form').forEach((form) => {
    const submitButton = form.querySelector('button[name="sc21_public_submit"]');
    const submitLabel = submitButton?.querySelector('.sc21-submit-label');
    const submitSpinner = submitButton?.querySelector('.sc21-submit-spinner');
    const submissionStatus = form.parentElement?.querySelector('.sc21-submission-status');
    const fields = [
      { name: 'sc21_recipient', required: 'Επιλέξτε έναν παραλήπτη.' },
      { name: 'sc21_parent_name', required: 'Συμπληρώστε το ονοματεπώνυμό σας.', limit: 120, length: 'Το ονοματεπώνυμο δεν μπορεί να υπερβαίνει τους 120 χαρακτήρες.' },
      { name: 'sc21_parent_email', required: 'Συμπληρώστε τη διεύθυνση email σας.', invalid: 'Συμπληρώστε μια έγκυρη διεύθυνση email.', limit: 254, length: 'Η διεύθυνση email δεν μπορεί να υπερβαίνει τους 254 χαρακτήρες.' },
      { name: 'sc21_subject', required: 'Συμπληρώστε το θέμα του μηνύματος.', limit: 150, length: 'Το θέμα δεν μπορεί να υπερβαίνει τους 150 χαρακτήρες.' },
      { name: 'sc21_message', required: 'Γράψτε το μήνυμά σας.', limit: 5000, length: 'Το μήνυμα δεν μπορεί να υπερβαίνει τους 5000 χαρακτήρες.' },
    ].map((rule) => ({ ...rule, control: form.elements.namedItem(rule.name) })).filter((rule) => rule.control instanceof HTMLElement);
    if (fields.length !== 5 || !form.id || !submitButton || !submitLabel || !submitSpinner || !submissionStatus) return;

    const errorId = (control) => `${control.id}-validation-error`;
    const descriptions = new Map(fields.map(({ control }) => [control, (control.getAttribute('aria-describedby') || '').split(/\s+/).filter((id) => id && id !== errorId(control)).join(' ')]));
    const summaryId = `${form.id}-validation-summary`;
    const serverErrors = new Map(fields
      .filter(({ control }) => control.getAttribute('aria-invalid') === 'true')
      .map(({ control }) => [control, document.getElementById(errorId(control))?.textContent.trim() || '']));
    const editedServerErrors = new Set();
    let attempted = serverErrors.size > 0;
    let submitting = false;
    let summary = document.getElementById(summaryId);

    const setSubmitting = (next) => {
      submitting = next;
      submitButton.classList.toggle('is-submitting', next);
      submitButton.setAttribute('aria-disabled', String(next));
      submitLabel.textContent = next ? 'Αποστολή σε εξέλιξη…' : 'Αποστολή μηνύματος';
      submitSpinner.hidden = !next;
      submissionStatus.textContent = next ? 'Παρακαλούμε περιμένετε μέχρι να ολοκληρωθεί η αποστολή.' : '';
    };

    const messageFor = (rule) => {
      const value = rule.control.value;
      const trimmed = value.trim();
      if (!trimmed) return rule.required;
      if (rule.limit && trimmed.length > rule.limit) return rule.length;
      if (rule.name === 'sc21_parent_email' && !isWordPressEmail(value)) return rule.invalid;
      return '';
    };
    const errors = () => fields.map((rule) => ({
      rule,
      message: (!editedServerErrors.has(rule.control) && serverErrors.get(rule.control)) || messageFor(rule) || '',
    })).filter(({ message }) => message);
    const renderField = ({ rule, message }) => {
      const { control } = rule;
      const id = errorId(control);
      let error = document.getElementById(id);
      if (message) {
        control.setAttribute('aria-invalid', 'true');
        const describedBy = descriptions.get(control);
        control.setAttribute('aria-describedby', [describedBy, id].filter(Boolean).join(' '));
        if (!error) {
          error = document.createElement('span');
          error.id = id;
          error.className = 'sc21-public-field-error';
          control.insertAdjacentElement('afterend', error);
        }
        if (error.textContent !== message) error.textContent = message;
      } else {
        control.removeAttribute('aria-invalid');
        const describedBy = descriptions.get(control);
        if (describedBy) control.setAttribute('aria-describedby', describedBy);
        else control.removeAttribute('aria-describedby');
        error?.remove();
      }
    };
    const render = (currentErrors) => {
      const messages = new Map(currentErrors.map(({ rule, message }) => [rule.control, message]));
      fields.forEach((rule) => renderField({ rule, message: messages.get(rule.control) || '' }));
      if (!currentErrors.length) {
        summary?.remove();
        summary = null;
        return;
      }
      if (!summary) {
        summary = document.createElement('div');
        summary.id = summaryId;
        summary.className = 'sc21-public-validation-summary';
        summary.setAttribute('role', 'alert');
        summary.setAttribute('aria-atomic', 'true');
        summary.textContent = 'Παρακαλούμε διορθώστε τα επισημασμένα πεδία.';
        form.querySelector('.sc21-form-intro')?.insertAdjacentElement('afterend', summary);
      }
    };
    const revalidate = (changedRule) => {
      if (!attempted) return;
      if (serverErrors.has(changedRule.control)) {
        editedServerErrors.add(changedRule.control);
        if (!messageFor(changedRule)) serverErrors.delete(changedRule.control);
      }
      render(errors());
    };

    fields.forEach((rule) => {
      const { control } = rule;
      control.addEventListener(control instanceof HTMLSelectElement ? 'change' : 'input', () => revalidate(rule));
      if (!(control instanceof HTMLSelectElement)) control.addEventListener('change', () => revalidate(rule));
    });
    submitButton.addEventListener('click', (event) => {
      if (submitting) event.preventDefault();
    });
    form.addEventListener('submit', (event) => {
      if (submitting || event.defaultPrevented) {
        event.preventDefault();
        return;
      }
      const currentErrors = errors();
      if (!currentErrors.length) return;
      event.preventDefault();
      attempted = true;
      render(currentErrors);
      requestAnimationFrame(() => currentErrors[0].rule.control.focus());
    });
    form.addEventListener('submit', (event) => {
      if (event.defaultPrevented || submitting) return;
      setSubmitting(true);
      queueMicrotask(() => {
        if (event.defaultPrevented) setSubmitting(false);
      });
    });
    window.addEventListener('pageshow', () => setSubmitting(false));
    document.documentElement.classList.add('sc21-public-validation-enhanced');
  });
  if (document.querySelector('[data-sc21-tooltip-trigger]')) document.documentElement.classList.add('sc21-tooltip-enhanced');
})();
