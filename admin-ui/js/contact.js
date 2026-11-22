/* Public Contact & Feedback form backed by the local Laravel API. */
(function () {
  var cfg = CMS.config, ui = CMS.ui, el = ui.el;

  var form = document.getElementById('contactForm');
  var receipt = document.getElementById('contactReceipt');
  var banner = document.getElementById('contactBanner');
  var f = {
    name: document.getElementById('contactName'),
    email: document.getElementById('contactEmail'),
    subject: document.getElementById('contactSubject'),
    campus: document.getElementById('contactCampus'),
    type: document.getElementById('contactType'),
    rating: document.getElementById('contactRating'),
    category: document.getElementById('contactCategory'),
    message: document.getElementById('contactMessage'),
    trap: document.getElementById('contactWebsite')
  };
  var feedbackFields = document.getElementById('contactFeedbackFields');
  var counter = document.getElementById('contactCounter');
  var touched = {};

  /* ---------- fill the dropdowns from config ---------- */
  function fillSelect(select, placeholder, items) {
    ui.clear(select);
    select.appendChild(el('option', { value: '', text: placeholder }));
    items.forEach(function (item) {
      var value = typeof item === 'object' ? item.value : item;
      var label = typeof item === 'object' ? item.label : item;
      select.appendChild(el('option', { value: value, text: label }));
    });
  }
  fillSelect(f.rating, 'Select a rating', ['5', '4', '3', '2', '1']);
  [].forEach.call(f.rating.options, function (o) { if (o.value) o.textContent = o.value + ' \u2605 ' + ({ 5: 'Excellent', 4: 'Good', 3: 'Fair', 2: 'Poor', 1: 'Very poor' })[o.value]; });
  fillSelect(f.category, 'Select a category', cfg.feedbackCategories);
  if (!cfg.features.campus) { var cf = f.campus.closest('.cms-field'); if (cf) cf.remove(); f.campus = null; }
  var submitButton = form.querySelector('[type="submit"]');
  var campusOptionsLoaded = !f.campus;
  if (f.campus) submitButton.disabled = true;
  var optionsReady = f.campus
    ? CMS.api.get('/contact/options').then(function (result) {
      fillSelect(f.campus, 'Select a campus', (result.campuses || []).map(function (campus) {
        return { value: campus.id, label: campus.name };
      }).concat([{ value: 'other', label: 'Other / Not sure' }]));
      campusOptionsLoaded = true;
      return true;
    }).catch(function (error) {
      showBanner('Could not load campus options: ' + error.message, 'error');
      submitButton.disabled = true;
      return false;
    })
    : Promise.resolve(true);

  /* ---------- validation ---------- */
  var EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
  var rules = {
    name: function (v) { return v.length < 2 ? 'Enter your full name.' : v.length > 120 ? 'Keep your name under 120 characters.' : ''; },
    email: function (v) { return !v ? 'Enter your email address.' : (!EMAIL.test(v) || v.length > 150) ? 'Enter a valid email address, like name@example.com.' : ''; },
    subject: function (v) { return v.length < 3 ? 'Enter a subject (at least 3 characters).' : v.length > 220 ? 'Keep the subject under 220 characters.' : ''; },
    campus: function (v) { return v ? '' : 'Select a campus or department.'; },
    message: function (v) { return v.length < 10 ? 'Write a message of at least 10 characters.' : v.length > 5000 ? 'Keep the message under 5000 characters.' : ''; },
    rating: function (v) { return isFeedback() && !v ? 'Select a rating.' : ''; },
    category: function (v) { return isFeedback() && !v ? 'Select a category.' : ''; }
  };
  function isFeedback() { return f.type.value === 'feedback'; }

  function validateField(key) {
    var input = f[key];
    if (!input || !rules[key]) return true;
    var field = input.closest('.cms-field');
    var message = rules[key](input.value.trim());
    if (field) { field.classList.remove('has-error'); input.classList.remove('is-invalid'); input.removeAttribute('aria-invalid'); }
    if (message) ui.setError(input, message);
    return !message;
  }
  function validateAll() {
    var firstBad = null;
    Object.keys(rules).forEach(function (key) {
      touched[key] = true;
      if (!validateField(key) && !firstBad) firstBad = f[key];
    });
    return firstBad;
  }

  Object.keys(rules).forEach(function (key) {
    if (!f[key]) return;
    f[key].addEventListener('blur', function () { touched[key] = true; validateField(key); });
    f[key].addEventListener(f[key].tagName === 'SELECT' ? 'change' : 'input', function () { if (touched[key]) validateField(key); });
  });
  f.message.addEventListener('input', function () { counter.textContent = f.message.value.length + ' / 5000'; });
  f.type.addEventListener('change', function () {
    feedbackFields.hidden = !isFeedback();
    if (!isFeedback()) { f.rating.value = ''; f.category.value = ''; validateField('rating'); validateField('category'); }
  });

  /* ---------- banner + receipt ---------- */
  function showBanner(text, type) {
    ui.clear(banner);
    banner.className = 'contact-banner is-' + type;
    banner.hidden = false;
    banner.appendChild(ui.icon(type === 'error' ? 'exclamation-circle-fill' : 'info-circle-fill'));
    banner.appendChild(el('span', { text: text }));
  }

  function showReceipt(rec, notice) {
    ui.clear(receipt);
    var first = rec.fullName.split(/\s+/)[0];
    var rows = [['Reference', rec.ref], ['Name', rec.fullName], ['Email', rec.email], ['Subject', rec.subject], ['Submitted', rec.submittedAt]];
    var list = el('dl', { class: 'cms-detail-list contact-receipt-list' });
    rows.forEach(function (r) { list.appendChild(el('dt', { text: r[0] })); list.appendChild(el('dd', { text: r[1] })); });
    var again = el('button', { type: 'button', class: 'cms-btn cms-btn-outline', text: 'Send another message' });
    again.addEventListener('click', function () { receipt.hidden = true; form.hidden = false; banner.hidden = true; f.name.focus(); });
    [
      el('div', { class: 'contact-success-icon', 'aria-hidden': 'true' }, [ui.icon('check-lg')]),
      el('h2', { class: 'contact-receipt-title', text: 'Thank you, ' + first + '!' }),
      el('p', { class: 'cms-page-sub', text: 'Your message was saved and now appears in the admin inbox.' }),
      el('div', { class: 'contact-receipt' }, [
        el('div', { class: 'contact-receipt-head', text: 'Submission receipt' }), list,
        notice ? el('p', { class: 'cms-field-hint', text: notice }) : null
      ]),
      el('div', { class: 'contact-receipt-actions' }, [again, el('a', { class: 'cms-btn cms-btn-primary', href: '../messages/all-messages.html', text: 'View in admin inbox' })])
    ].forEach(function (n) { receipt.appendChild(n); });
    form.hidden = true; receipt.hidden = false; banner.hidden = true;
    receipt.setAttribute('tabindex', '-1'); receipt.focus();
  }

  /* ---------- submit ---------- */
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    banner.hidden = true;

    var bad = validateAll();
    if (bad) { bad.focus(); showBanner('Please fix the highlighted fields and try again.', 'error'); return; }
    submitButton.disabled = true;
    optionsReady.then(function () {
      if (!campusOptionsLoaded) throw new Error('Campus options are unavailable. Reload the page and try again.');
      return CMS.api.post('/contact-submissions', {
        fullName: f.name.value.trim(),
        email: f.email.value.trim(),
        subject: f.subject.value.trim(),
        campusId: f.campus && f.campus.value !== 'other' ? Number(f.campus.value) : null,
        type: f.type.value,
        rating: isFeedback() ? Number(f.rating.value) : null,
        category: isFeedback() ? f.category.value : null,
        message: f.message.value.trim(),
        website: f.trap.value
      });
    }).then(function (result) {
      var rec = {
        ref: result.reference || 'Received',
        fullName: f.name.value.trim(),
        email: f.email.value.trim(),
        subject: f.subject.value.trim(),
        submittedAt: new Date().toLocaleString()
      };
      form.reset(); touched = {}; feedbackFields.hidden = true; counter.textContent = '0 / 5000';
      showReceipt(rec);
    }).catch(function (error) {
      showBanner(error.message, 'error');
      submitButton.disabled = false;
    });
  });

  form.addEventListener('reset', function () {
    ui.clearErrors(form); touched = {}; feedbackFields.hidden = true; counter.textContent = '0 / 5000';
    if (submitButton) submitButton.disabled = !campusOptionsLoaded;
  });
  optionsReady.then(function () { if (submitButton) submitButton.disabled = !campusOptionsLoaded; });
  ui.applyFeatures();
})();
