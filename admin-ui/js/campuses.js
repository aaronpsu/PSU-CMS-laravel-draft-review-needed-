(function () {
  var ui = CMS.ui, el = ui.el, api = CMS.api;

  function init() {
    var body = document.getElementById('campusBody');
    var wrap = document.getElementById('campusTableWrap');
    var empty = document.getElementById('campusEmpty');
    var footer = document.getElementById('campusFooter');
    var search = document.getElementById('campusSearch');
    var status = document.getElementById('campusFilterStatus');
    var state = { rows: [], page: 1, request: 0 };
    var pageSize = 10;

    function render() {
      var pages = Math.max(1, Math.ceil(state.rows.length / pageSize));
      state.page = Math.min(state.page, pages);
      ui.clear(body);
      if (!state.rows.length) {
        wrap.hidden = true;
        footer.hidden = true;
        empty.hidden = false;
        ui.renderEmpty(empty, {
          title: search.value || status.value ? 'No matching campuses found' : 'No campuses yet',
          text: search.value || status.value ? 'Try another search or filter.' : 'Add a campus to use it in News and Announcements.',
          actionLabel: search.value || status.value ? null : '+ Add Campus',
          actionHref: '#'
        });
        var add = empty.querySelector('a');
        if (add) add.addEventListener('click', function (event) { event.preventDefault(); openForm(null); });
        return;
      }
      wrap.hidden = false;
      empty.hidden = true;
      footer.hidden = false;
      state.rows.slice((state.page - 1) * pageSize, state.page * pageSize).forEach(function (campus) {
        var row = el('tr');
        row.appendChild(el('td', { text: campus.name }));
        row.appendChild(el('td', { text: campus.code || '\u2014' }));
        row.appendChild(el('td', { text: campus.address || '\u2014' }));
        row.appendChild(el('td', { text: String(campus.newsCount) }));
        row.appendChild(el('td', { text: String(campus.announcementCount) }));
        row.appendChild(el('td', {}, [el('span', {
          class: 'cms-status cms-status-' + (campus.status === 'active' ? 'green' : 'gray'),
          text: campus.status === 'active' ? 'Active' : 'Inactive'
        })]));
        row.appendChild(rowActions(campus));
        body.appendChild(row);
      });
      ui.renderFooter(footer, {
        page: state.page, pageSize: pageSize, total: state.rows.length,
        onPage: function (page) { state.page = page; render(); }
      });
    }

    function load() {
      var request = ++state.request;
      return api.query('/campuses', { q: search.value.trim(), status: status.value }).then(function (rows) {
        if (request !== state.request) return;
        state.rows = rows;
        state.page = 1;
        render();
      }).catch(function (error) {
        if (request !== state.request) return;
        wrap.hidden = true;
        footer.hidden = true;
        empty.hidden = false;
        ui.renderEmpty(empty, { title: 'Unable to load campuses', text: error.message });
        ui.toast(error.message, 'error');
      });
    }

    function rowActions(campus) {
      var view = el('button', { type: 'button', class: 'cms-action', text: 'View', 'aria-label': 'View ' + campus.name });
      var edit = el('button', { type: 'button', class: 'cms-action', text: 'Edit', 'aria-label': 'Edit ' + campus.name });
      var remove = el('button', { type: 'button', class: 'cms-action cms-action-danger', text: 'Delete', 'aria-label': 'Delete ' + campus.name });
      view.addEventListener('click', function () {
        ui.showDetails({ title: campus.name, rows: [
          ['Code', campus.code], ['Address', campus.address], ['News', String(campus.newsCount)],
          ['Announcements', String(campus.announcementCount)],
          ['Status', el('span', {
            class: 'cms-status cms-status-' + (campus.status === 'active' ? 'green' : 'gray'),
            text: campus.status === 'active' ? 'Active' : 'Inactive'
          })]
        ] });
      });
      edit.addEventListener('click', function () { openForm(campus); });
      remove.addEventListener('click', function () { removeCampus(campus); });
      return el('td', {}, [el('div', { class: 'cms-actions' }, [view, edit, remove])]);
    }

    function openForm(campus) {
      var name = el('input', { class: 'cms-input', type: 'text', maxlength: '150', placeholder: 'Campus name' });
      var code = el('input', { class: 'cms-input', type: 'text', maxlength: '30', placeholder: 'Optional campus code' });
      var address = el('input', { class: 'cms-input', type: 'text', maxlength: '255', placeholder: 'Address' });
      var stateSelect = el('select', { class: 'cms-input' }, [
        el('option', { value: 'active', text: 'Active' }),
        el('option', { value: 'inactive', text: 'Inactive' })
      ]);
      if (campus) {
        name.value = campus.name;
        code.value = campus.code || '';
        address.value = campus.address || '';
        stateSelect.value = campus.status;
      }
      var save = el('button', { type: 'button', class: 'cms-btn cms-btn-primary', text: campus ? 'Save Changes' : 'Add Campus' });
      var modalEl = ui.modalShell(campus ? 'Edit Campus' : 'Add Campus', [
        formField('Campus Name', name), formField('Code', code), formField('Address', address), formField('Status', stateSelect)
      ], [
        el('button', { type: 'button', class: 'cms-btn cms-btn-outline', 'data-bs-dismiss': 'modal', text: 'Cancel' }),
        save
      ]);
      var modal = ui.openModal(modalEl);
      save.addEventListener('click', function () {
        var payload = { name: name.value.trim(), code: code.value.trim(), address: address.value.trim(), status: stateSelect.value };
        if (!payload.name) {
          ui.toast('Enter a campus name.', 'error');
          name.focus();
          return;
        }
        save.disabled = true;
        var request = campus
          ? api.put('/campuses/' + encodeURIComponent(campus.id), payload)
          : api.post('/campuses', payload);
        request.then(function () {
          modal.hide();
          ui.toast(campus ? 'Campus updated.' : 'Campus added.', 'success');
          return load();
        }).catch(function (error) { ui.toast(error.message, 'error'); })
          .finally(function () { save.disabled = false; });
      });
    }

    function removeCampus(campus) {
      ui.confirmDialog({
        title: 'Delete campus',
        message: 'Delete \u201C' + campus.name + '\u201D? A campus assigned to news or announcements cannot be deleted.',
        confirmLabel: 'Delete'
      }).then(function (confirmed) {
        if (!confirmed) return;
        api.remove('/campuses/' + encodeURIComponent(campus.id)).then(function () {
          ui.toast('Campus deleted.', 'success');
          return load();
        }).catch(function (error) { ui.toast(error.message, 'error'); });
      });
    }

    document.getElementById('campusAdd').addEventListener('click', function () { openForm(null); });
    search.addEventListener('input', ui.debounce(load, 180));
    status.addEventListener('change', load);
    load();
  }

  function formField(label, input) {
    return el('div', { class: 'cms-field' }, [el('label', { class: 'cms-label', text: label }), input]);
  }

  document.addEventListener('DOMContentLoaded', function () {
    if (document.getElementById('campusBody')) init();
  });
})();
