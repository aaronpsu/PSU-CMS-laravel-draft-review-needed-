(function () {
  var ui = CMS.ui, el = ui.el;
  var form = document.getElementById('securityForm');
  var current = document.getElementById('securityCurrent');
  var password = document.getElementById('securityNew');
  var confirmation = document.getElementById('securityConfirm');
  var submit = form.querySelector('button[type="submit"]');
  var sessions = document.getElementById('securitySessions');
  var strength = document.getElementById('securityStrength');

  ui.clear(sessions);
  sessions.appendChild(el('div', { class: 'security-session', text: 'Current browser session' }));

  var unavailable = document.getElementById('securityTwoFactor');
  if (unavailable) {
    unavailable.disabled = true;
    unavailable.setAttribute('aria-checked', 'false');
    unavailable.lastElementChild.textContent = 'Unavailable';
  }

  password.addEventListener('input', function () {
    var value = password.value;
    strength.textContent = value.length < 12
      ? 'Use at least 12 characters.'
      : 'Password length is acceptable.';
  });

  form.addEventListener('submit', function (event) {
    event.preventDefault();
    ui.clearErrors(form);
    var bad = null;
    function fail(input, message) {
      ui.setError(input, message);
      if (!bad) bad = input;
    }
    if (!current.value) fail(current, 'Enter your current password.');
    if (password.value.length < 12) fail(password, 'Use at least 12 characters.');
    if (password.value !== confirmation.value) fail(confirmation, 'The passwords do not match.');
    if (bad) {
      bad.focus();
      ui.toast('Please fix the highlighted fields.', 'error');
      return;
    }

    submit.disabled = true;
    CMS.api.put('/auth/password', {
      currentPassword: current.value,
      newPassword: password.value
    }).then(function () {
      current.value = '';
      password.value = '';
      confirmation.value = '';
      ui.toast('Password changed. Please use the new password on your next sign-in.', 'success');
    }).catch(function (error) {
      ui.toast(error.message, 'error');
    }).then(function () {
      submit.disabled = false;
    });
  });
})();
