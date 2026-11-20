(function () {
  var ui = CMS.ui, el = ui.el;
  var faculty = [], departments = [];
  var state = { q: '', department: '', page: 1 };
  var pageSize = 10;

  function load() {
    return Promise.all([
      CMS.api.query('/faculty', { q: state.q, department: state.department }),
      CMS.api.get('/options')
    ]).then(function (results) {
      faculty = results[0];
      departments = results[1].departments;
      renderDepartmentOptions();
      render();
    });
  }

  function renderDepartmentOptions() {
    var filter = document.getElementById('facultyDepartmentFilter');
    ui.setOptions(filter, [{ value: '', label: 'All Departments' }].concat(departments.map(function (d) {
      return { value: String(d.id), label: d.name };
    })), state.department);
  }

  function rowFor(person) {
    var row = el('tr');
    row.appendChild(el('td', { class: 'cms-cell-title', text: person.name }));
    row.appendChild(el('td', { text: person.position || '—' }));
    row.appendChild(el('td', { text: person.department || 'Unassigned' }));
    row.appendChild(el('td', { text: person.email || '—' }));

    var view = el('button', { type: 'button', class: 'cms-action', text: 'View', 'aria-label': 'View ' + person.name });
    var edit = el('button', { type: 'button', class: 'cms-action', text: 'Edit', 'aria-label': 'Edit ' + person.name });
    var remove = el('button', { type: 'button', class: 'cms-action cms-action-danger', text: 'Delete', 'aria-label': 'Delete ' + person.name });
    view.addEventListener('click', function () { showDetails(person); });
    edit.addEventListener('click', function () { openForm(person); });
    remove.addEventListener('click', function () { deletePerson(person); });
    row.appendChild(el('td', {}, [el('div', { class: 'cms-actions' }, [view, edit, remove])]));
    return row;
  }

  function render() {
    var body = document.getElementById('facultyTableBody');
    var table = document.getElementById('facultyTableWrap');
    var empty = document.getElementById('facultyEmpty');
    var footer = document.getElementById('facultyFooter');
    var pages = Math.max(1, Math.ceil(faculty.length / pageSize));
    state.page = Math.min(state.page, pages);
    ui.clear(body);
    ui.clear(footer);

    if (!faculty.length) {
      table.hidden = true;
      empty.hidden = false;
      ui.renderEmpty(empty, {
        title: state.q || state.department ? 'No matching personnel found' : 'No personnel records yet',
        text: state.q || state.department ? 'Try changing your search or department filter.' : 'Add a faculty or staff record to get started.'
      });
      return;
    }

    table.hidden = false;
    empty.hidden = true;
    faculty.slice((state.page - 1) * pageSize, state.page * pageSize).forEach(function (person) {
      body.appendChild(rowFor(person));
    });
    ui.renderFooter(footer, {
      page: state.page,
      pageSize: pageSize,
      total: faculty.length,
      onPage: function (page) { state.page = page; render(); }
    });
  }

  function showDetails(person) {
    ui.showDetails({
      title: 'Faculty & staff record',
      rows: [
        ['Name', person.name],
        ['Position', person.position],
        ['Department', person.department || 'Unassigned'],
        ['Email', person.email],
        ['Profile', person.summary]
      ]
    });
  }

  function field(label, input, hint) {
    return el('div', { class: 'cms-field' }, [
      el('label', { class: 'cms-label', for: input.id, text: label }),
      input,
      el('p', { class: 'cms-field-error' }),
      hint ? el('p', { class: 'cms-field-hint', text: hint }) : null
    ]);
  }

  function openForm(person) {
    var editing = Boolean(person);
    var name = el('input', { class: 'cms-input', id: 'facultyName', type: 'text', maxlength: '150', required: true, value: person ? person.name : '' });
    var position = el('input', { class: 'cms-input', id: 'facultyPosition', type: 'text', maxlength: '150', value: person ? person.position : '' });
    var department = el('select', { class: 'cms-input', id: 'facultyDepartment' });
    department.appendChild(el('option', { value: '', text: 'Unassigned' }));
    departments.forEach(function (item) {
      department.appendChild(el('option', { value: String(item.id), text: item.name }));
    });
    department.value = person && person.departmentId ? String(person.departmentId) : '';
    var email = el('input', { class: 'cms-input', id: 'facultyEmail', type: 'email', maxlength: '255', value: person ? person.email : '' });
    var summary = el('textarea', { class: 'cms-textarea', id: 'facultySummary', rows: '4', maxlength: '5000' });
    summary.value = person ? person.summary || '' : '';

    var body = [
      field('Name', name),
      field('Position', position),
      field('Department', department),
      field('Email', email),
      field('Profile summary', summary)
    ];
    var cancel = el('button', { type: 'button', class: 'cms-btn cms-btn-outline', 'data-bs-dismiss': 'modal', text: 'Cancel' });
    var save = el('button', { type: 'button', class: 'cms-btn cms-btn-primary', text: editing ? 'Save Changes' : 'Add Personnel' });
    var modalEl = ui.modalShell(editing ? 'Edit Personnel' : 'Add Personnel', body, [cancel, save]);
    var modal = ui.openModal(modalEl);
    modalEl.addEventListener('shown.bs.modal', function () { name.focus(); });

    save.addEventListener('click', function () {
      ui.clearErrors(modalEl);
      if (!name.value.trim()) {
        ui.setError(name, 'Please enter a name.');
        name.focus();
        return;
      }
      if (email.value.trim() && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim())) {
        ui.setError(email, 'Enter a valid email address.');
        email.focus();
        return;
      }
      var payload = {
        name: name.value.trim(),
        position: position.value.trim() || null,
        departmentId: department.value || null,
        email: email.value.trim() || null,
        summary: summary.value.trim() || null
      };
      var request = editing
        ? CMS.api.put('/faculty/' + encodeURIComponent(person.id), payload)
        : CMS.api.post('/faculty', payload);
      request.then(function () {
        state.page = 1;
        return load();
      }).then(function () {
        modal.hide();
        ui.toast(editing ? 'Personnel record updated.' : 'Personnel record added.', 'success');
      }).catch(function (error) {
        ui.toast(error.message, 'error');
      });
    });
  }

  function deletePerson(person) {
    ui.confirmDialog({
      title: 'Delete personnel record',
      message: 'Delete the record for “' + person.name + '”? This cannot be undone.',
      confirmLabel: 'Delete'
    }).then(function (confirmed) {
      if (!confirmed) return;
      return CMS.api.remove('/faculty/' + encodeURIComponent(person.id)).then(function () {
        return load();
      }).then(function () {
        ui.toast('Personnel record deleted.', 'success');
      }).catch(function (error) {
        ui.toast(error.message, 'error');
      });
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    if (document.body.getAttribute('data-page') !== 'faculty') return;
    document.getElementById('facultyAdd').addEventListener('click', function () { openForm(null); });
    document.getElementById('facultySearch').addEventListener('input', ui.debounce(function () {
      state.q = this.value.trim();
      state.page = 1;
      load().catch(function (error) { ui.toast(error.message, 'error'); });
    }, 180));
    document.getElementById('facultyDepartmentFilter').addEventListener('change', function () {
      state.department = this.value;
      state.page = 1;
      load().catch(function (error) { ui.toast(error.message, 'error'); });
    });
    load().catch(function (error) { ui.toast(error.message, 'error'); });
  });
})();
