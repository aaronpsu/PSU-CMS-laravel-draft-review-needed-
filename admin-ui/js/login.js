(function () {
  var form = document.getElementById('cmsLoginForm');
  var email = document.getElementById('username');
  var password = document.getElementById('password');
  var submit = form.querySelector('button[type="submit"]');
  var errorBox = document.getElementById('loginError');

  form.addEventListener('submit', function (event) {
    event.preventDefault();
    errorBox.hidden = true;
    submit.disabled = true;
    submit.textContent = 'Signing in...';
    CMS.api.post('/auth/login', {
      email: email.value.trim(),
      password: password.value
    }).then(function (result) {
      CMS.config.currentUser = result.user;
      window.location.href = '../news/all-news.html';
    }).catch(function (error) {
      errorBox.textContent = error.message;
      errorBox.hidden = false;
      password.value = '';
      password.focus();
    }).then(function () {
      submit.disabled = false;
      submit.textContent = 'Sign in';
    });
  });

})();
