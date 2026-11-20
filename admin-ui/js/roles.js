(function () {
  var ui = CMS.ui, el = ui.el, api = CMS.api;

  function init() {
    var grid = document.getElementById('roleGrid');
    function render() {
      return api.get('/user-roles').then(function (roles) {
      ui.clear(grid);
      if (!roles.length) {
        ui.renderEmpty(grid, {
          title: 'No user roles yet',
          text: 'Role names appear here after users have been assigned a role.'
        });
        return;
      }

      roles.forEach(function (role) {
        var userCount = Number(role.userCount);
        var rename = el('button', { type: 'button', class: 'cms-btn cms-btn-soft role-edit', text: 'Rename Role' });
        rename.addEventListener('click', function () { renameRole(role); });
        grid.appendChild(el('article', { class: 'cms-card role-card' }, [
          el('h2', { class: 'role-name', text: role.name }),
          el('p', { class: 'role-desc', text: userCount + (userCount === 1 ? ' user' : ' users') }),
          rename
        ]));
      });
    }).catch(function (error) {
      ui.renderEmpty(grid, { title: 'Unable to load roles', text: error.message });
      ui.toast(error.message, 'error');
    });
    }

    function renameRole(role) {
      var name = el('input', { class: 'cms-input', type: 'text', maxlength: '50' });
      name.value = role.name;
      var save = el('button', { type: 'button', class: 'cms-btn cms-btn-primary', text: 'Save Changes' });
      var modalEl = ui.modalShell('Rename Role', [
        el('div', { class: 'cms-field' }, [
          el('label', { class: 'cms-label', text: 'Role Name' }),
          name,
          el('p', { class: 'cms-field-hint', text: 'This updates the role label for all ' + role.userCount + ' assigned users.' })
        ])
      ], [
        el('button', { type: 'button', class: 'cms-btn cms-btn-outline', 'data-bs-dismiss': 'modal', text: 'Cancel' }),
        save
      ]);
      var modal = ui.openModal(modalEl);
      save.addEventListener('click', function () {
        var next = name.value.trim();
        if (!next) {
          ui.toast('Enter a role name.', 'error');
          name.focus();
          return;
        }
        save.disabled = true;
        api.put('/user-roles', { currentName: role.name, name: next }).then(function () {
          modal.hide();
          ui.toast('Role renamed.', 'success');
          return render();
        }).catch(function (error) { ui.toast(error.message, 'error'); })
          .finally(function () { save.disabled = false; });
      });
      modalEl.addEventListener('shown.bs.modal', function () { name.focus(); name.select(); });
    }

    render();
  }

  document.addEventListener('DOMContentLoaded', function () {
    if (document.getElementById('roleGrid')) init();
  });
})();
