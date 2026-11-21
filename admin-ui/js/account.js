(function () {
  var ui = CMS.ui;
  var form = document.getElementById('accountForm');
  var name = document.getElementById('accountName');
  var email = document.getElementById('accountEmail');
  var submit = form.querySelector('button[type="submit"]');
  var loadedName = '';
  var loadedEmail = '';

  CMS.api.get('/auth/me').then(function (result) {
    name.value = result.user.name || '';
    email.value = result.user.email || '';
    loadedName = name.value;
    loadedEmail = email.value;
  }).catch(function (error) {
    ui.toast(error.message, 'error');
  });

  form.addEventListener('submit', function (event) {
    event.preventDefault();
    ui.clearErrors(form);
    if (name.value.trim().length < 2) {
      ui.setError(name, 'Enter your full name.');
      name.focus();
      return;
    }
    submit.disabled = true;
    CMS.api.put('/auth/account', {
      fullName: name.value.trim(),
      email: email.value.trim()
    }).then(function (result) {
      var accountName = document.querySelector('.cms-user-name');
      if (accountName) accountName.textContent = result.user.name;
      CMS.config.currentUser = result.user;
      ui.toast('Profile updated.', 'success');
    }).catch(function (error) {
      ui.toast(error.message, 'error');
    }).then(function () {
      submit.disabled = false;
    });
  });

  form.addEventListener('reset', function (event) {
    event.preventDefault();
    name.value = loadedName;
    email.value = loadedEmail;
    ui.toast('Changes reset.', 'info');
  });
})();
