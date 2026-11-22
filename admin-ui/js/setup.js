(function () {
  var form = document.getElementById('cmsSetupForm');
  var errorBox = document.getElementById('setupError');
  var statusBox = document.getElementById('setupStatus');
  var submit = form.querySelector('button[type="submit"]');

  CMS.api.get('/auth/setup/status').then(function (result) {
    if (!result.setupRequired) {
      form.hidden = true;
      statusBox.textContent = 'Initial setup is complete. Use the sign-in page.';
      statusBox.className = 'setup-status';
      var link = document.createElement('a');
      link.className = 'setup-login-link';
      link.href = '../login/login-sc.html';
      link.textContent = 'Go to Sign in';
      statusBox.appendChild(document.createElement('br'));
      statusBox.appendChild(link);
      return;
    }

    form.hidden = false;
  }).catch(function (error) {
    form.hidden = true;
    statusBox.textContent = error.message;
  });

  form.addEventListener('submit', function (event) {
    event.preventDefault();
    errorBox.hidden = true;
    submit.disabled = true;
    submit.textContent = 'Creating...';

    CMS.api.post('/auth/setup', {
      fullName: document.getElementById('setupName').value.trim(),
      email: document.getElementById('setupEmail').value.trim(),
      password: document.getElementById('setupPassword').value
    }).then(function (result) {
      CMS.config.currentUser = result.user;
      window.location.href = '../news/all-news.html';
    }).catch(function (error) {
      errorBox.textContent = error.message;
      errorBox.hidden = false;
    }).then(function () {
      submit.disabled = false;
      submit.textContent = 'Create Administrator';
    });
  });
})();
