(function () {
  var ui = CMS.ui, el = ui.el, S = CMS.structure, api = CMS.api;

  function initList() {
    var body = document.getElementById('userTableBody');
    var wrap = document.getElementById('userTableWrap');
    var empty = document.getElementById('userEmpty');
    var footer = document.getElementById('userFooter');
    var search = document.getElementById('userSearch');
    var roleFilter = document.getElementById('userFilterRole');
    var statusFilter = document.getElementById('userFilterStatus');
    var state = { page: 1, rows: [], request: 0 };
    var pageSize = CMS.config.pageSize.users;

    function render() {
      var pages = Math.max(1, Math.ceil(state.rows.length / pageSize));
      state.page = Math.min(state.page, pages);
      ui.clear(body);
      if (!state.rows.length) {
        wrap.hidden = true;
        footer.hidden = true;
        empty.hidden = false;
        ui.renderEmpty(empty, {
          title: search.value || roleFilter.value || statusFilter.value ? 'No matching users found' : 'No users yet',
          text: search.value || roleFilter.value || statusFilter.value
            ? 'Try a different search or filter.' : 'Accounts you add will appear here.',
          actionLabel: search.value || roleFilter.value || statusFilter.value ? null : '+ Add New User',
          actionHref: 'add-user.html'
        });
        return;
      }

      wrap.hidden = false;
      empty.hidden = true;
      footer.hidden = false;
      state.rows.slice((state.page - 1) * pageSize, state.page * pageSize).forEach(function (user) {
        var tr = el('tr');
        tr.appendChild(el('td', {}, [el('span', { class: 'cms-cell-title', text: user.fullName })]));
        tr.appendChild(el('td', { text: user.email }));
        tr.appendChild(el('td', { text: user.role }));
        tr.appendChild(el('td', {}, [S.statusBadge(user.status)]));
        tr.appendChild(S.actions(user.fullName, 'add-user.html?id=' + encodeURIComponent(user.id),
          function () { showUser(user); }, function () { removeUser(user); }));
        body.appendChild(tr);
      });
      ui.renderFooter(footer, {
        page: state.page, pageSize: pageSize, total: state.rows.length,
        onPage: function (page) { state.page = page; render(); }
      });
    }

    function load() {
      var request = ++state.request;
      return api.query('/users', {
        q: search.value.trim(), role: roleFilter.value, status: statusFilter.value
      }).then(function (rows) {
        if (request !== state.request) return;
        state.rows = rows;
        state.page = 1;
        render();
      }).catch(function (error) {
        if (request !== state.request) return;
        ui.clear(body);
        wrap.hidden = true;
        footer.hidden = true;
        empty.hidden = false;
        ui.renderEmpty(empty, { title: 'Unable to load users', text: error.message });
        ui.toast(error.message, 'error');
      });
    }

    function showUser(user) {
      ui.showDetails({
        title: user.fullName,
        rows: [
          ['Email', user.email], ['Role', user.role], ['Status', S.statusBadge(user.status)]
        ]
      });
    }

    function removeUser(user) {
      ui.confirmDialog({
        title: 'Delete user',
        message: 'Delete \u201C' + user.fullName + '\u201D? This cannot be undone.',
        confirmLabel: 'Delete'
      }).then(function (confirmed) {
        if (!confirmed) return;
        api.remove('/users/' + encodeURIComponent(user.id)).then(function () {
          ui.toast('User deleted.', 'success');
          load();
        }).catch(function (error) { ui.toast(error.message, 'error'); });
      });
    }

    api.get('/user-roles').then(function (roles) {
      ui.setOptions(roleFilter, [{ value: '', label: 'All Roles' }].concat(roles.map(function (role) {
        return { value: role.name, label: role.name };
      })), '');
      roleFilter.addEventListener('change', load);
      statusFilter.addEventListener('change', load);
      search.addEventListener('input', ui.debounce(load, 180));
      return load();
    }).catch(function (error) {
      ui.renderEmpty(empty, { title: 'Unable to load user roles', text: error.message });
      empty.hidden = false;
      wrap.hidden = true;
      ui.toast(error.message, 'error');
    });
  }

  function initForm() {
    var form = document.getElementById('userForm');
    var f = {
      fullName: document.getElementById('userFullName'),
      email: document.getElementById('userEmail'),
      role: document.getElementById('userRole'),
      password: document.getElementById('userPassword'),
      confirm: document.getElementById('userConfirm'),
      status: document.getElementById('userStatus')
    };
    var validator = S.validator(form);
    var id = ui.getParam('id');
    var submit = document.getElementById('userSubmit');

    if (id) {
      document.getElementById('userPageTitle').textContent = 'Edit User';
      document.getElementById('userPageSub').textContent = 'Update this CMS account.';
      document.getElementById('userCrumb').textContent = 'Edit User';
      submit.textContent = 'Save Changes';
      document.getElementById('userPasswordHint').textContent = 'Leave both password fields blank to keep the current password.';
      document.title = 'Edit User | PSU CMS';
      submit.disabled = true;
      api.get('/users/' + encodeURIComponent(id)).then(function (user) {
        f.fullName.value = user.fullName;
        f.email.value = user.email;
        f.role.value = user.role;
        f.status.value = user.status;
        if (f.status._cmsRefresh) f.status._cmsRefresh();
      }).catch(function (error) {
        ui.flash(error.message, 'error');
        window.location.href = 'all-users.html';
      }).finally(function () { submit.disabled = false; });
    }

    form.addEventListener('submit', function (event) {
      event.preventDefault();
      validator.reset();
      var fullName = f.fullName.value.trim();
      var email = f.email.value.trim();
      var role = f.role.value.trim();
      var password = f.password.value;
      var confirmation = f.confirm.value;
      if (!fullName) validator.fail(f.fullName, 'Enter the full name.');
      if (!email || !S.isEmail(email)) validator.fail(f.email, 'Enter a valid email address.');
      if (!role) validator.fail(f.role, 'Enter a role.');
      if (!id && password.length < 8) validator.fail(f.password, 'Use a password with at least 8 characters.');
      if (password && password.length < 8) validator.fail(f.password, 'Use a password with at least 8 characters.');
      if (password && password.length > 72) validator.fail(f.password, 'Use a password no longer than 72 characters.');
      if ((password || confirmation) && password !== confirmation) validator.fail(f.confirm, 'The passwords do not match.');
      if (!id && !password) validator.fail(f.password, 'Enter a password for the new user.');
      if (!validator.ok()) return;

      var data = { fullName: fullName, email: email, role: role, status: f.status.value };
      if (password) data.password = password;
      submit.disabled = true;
      var save = id ? api.put('/users/' + encodeURIComponent(id), data) : api.post('/users', data);
      save.then(function () {
        f.password.value = '';
        f.confirm.value = '';
        ui.flash(id ? 'User updated.' : 'User created.', 'success');
        window.location.href = 'all-users.html';
      }).catch(function (error) {
        ui.toast(error.message, 'error');
      }).finally(function () { submit.disabled = false; });
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    if (document.getElementById('userForm')) initForm();
    else if (document.getElementById('userTableBody')) initList();
  });
})();
