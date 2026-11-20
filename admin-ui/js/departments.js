(function () {
  var ui = CMS.ui, el = ui.el;
  var departments = [], colleges = [], faculty = [];
  var state = { q: '', college: '', status: '', page: 1 };
  var pageSize = 10;

  function fetchDepartments() {
    return CMS.api.query('/departments', {
      q: state.q,
      college: state.college,
      status: state.status
    }).then(function (rows) {
      departments = rows;
      renderList();
    });
  }

  function renderList() {
    var body = document.getElementById('departmentTableBody');
    var table = document.getElementById('departmentTableWrap');
    var empty = document.getElementById('departmentEmpty');
    var footer = document.getElementById('departmentFooter');
    var pages = Math.max(1, Math.ceil(departments.length / pageSize));
    state.page = Math.min(state.page, pages);
    ui.clear(body);
    ui.clear(footer);

    if (!departments.length) {
      table.hidden = true;
      empty.hidden = false;
      ui.renderEmpty(empty, {
        title: state.q || state.college || state.status ? 'No matching departments found' : 'No departments yet',
        text: state.q || state.college || state.status ? 'Try a different search or filter.' : 'Departments you add will appear here.'
      });
      return;
    }

    table.hidden = false;
    empty.hidden = true;
    departments.slice((state.page - 1) * pageSize, state.page * pageSize).forEach(function (department) {
      body.appendChild(buildRow(department));
    });
    ui.renderFooter(footer, {
      page: state.page,
      pageSize: pageSize,
      total: departments.length,
      onPage: function (page) { state.page = page; renderList(); }
    });
  }

  function buildRow(department) {
    var row = el('tr');
    row.appendChild(el('td', {}, [el('span', { class: 'cms-cell-title', text: department.name })]));
    row.appendChild(el('td', { text: department.code }));
    row.appendChild(el('td', { text: department.collegeName + ' (' + department.collegeCode + ')' }));
    row.appendChild(el('td', { text: department.headName || '—' }));
    row.appendChild(el('td', { text: department.description || '' }));
    row.appendChild(el('td', {}, [el('span', {
      class: 'cms-status cms-status-' + (department.status === 'active' ? 'green' : 'gray'),
      text: department.status === 'active' ? 'Active' : 'Inactive'
    })]));

    var view = el('button', { type: 'button', class: 'cms-action', text: 'View', 'aria-label': 'View ' + department.name });
    var edit = el('a', { class: 'cms-action', href: 'department-form.html?id=' + encodeURIComponent(department.id), text: 'Edit', 'aria-label': 'Edit ' + department.name });
    var remove = el('button', { type: 'button', class: 'cms-action cms-action-danger', text: 'Delete', 'aria-label': 'Delete ' + department.name });
    view.addEventListener('click', function () { showDetails(department); });
    remove.addEventListener('click', function () { deleteDepartment(department); });
    row.appendChild(el('td', {}, [el('div', { class: 'cms-actions' }, [view, edit, remove])]));
    return row;
  }

  function showDetails(department) {
    CMS.api.get('/departments/' + encodeURIComponent(department.id)).then(function (record) {
      ui.showDetails({
        title: record.name,
        rows: [
          ['Code', record.code],
          ['College', record.collegeName + ' (' + record.collegeCode + ')'],
          ['Department head', record.headName || 'Unassigned'],
          ['Description', record.description],
          ['Status', record.status === 'active' ? 'Active' : 'Inactive'],
          ['Academic programs', detailList(record.programs, function (program) { return program.name + ' (' + program.code + ')'; }, 'No programs yet')],
          ['Staff', detailList(record.staff, function (person) {
            return person.name + (person.position ? ' — ' + person.position : '');
          }, 'No staff listed')]
        ]
      });
    }).catch(function (error) { ui.toast(error.message, 'error'); });
  }

  function detailList(items, format, emptyText) {
    if (!items || !items.length) return el('span', { class: 'structure-none', text: emptyText });
    return el('ul', { class: 'structure-list' }, items.map(function (item) {
      return el('li', { text: format(item) });
    }));
  }

  function deleteDepartment(department) {
    if (Number(department.programCount) > 0) {
      ui.toast('Move or delete this department’s academic programs first.', 'error');
      return;
    }
    ui.confirmDialog({
      title: 'Delete department',
      message: 'Delete “' + department.name + '”? This cannot be undone.',
      confirmLabel: 'Delete'
    }).then(function (confirmed) {
      if (!confirmed) return;
      return CMS.api.remove('/departments/' + encodeURIComponent(department.id))
        .then(function () { return fetchDepartments(); })
        .then(function () { ui.toast('Department deleted.', 'success'); })
        .catch(function (error) { ui.toast(error.message, 'error'); });
    });
  }

  function initList() {
    var collegeFilter = document.getElementById('departmentFilterCollege');
    var statusFilter = document.getElementById('departmentFilterStatus');
    var search = document.getElementById('departmentSearch');

    CMS.api.get('/options').then(function (options) {
      colleges = options.colleges;
      ui.setOptions(collegeFilter, [{ value: '', label: 'All Colleges' }].concat(colleges.map(function (college) {
        return { value: String(college.id), label: college.name };
      })), state.college);
      return fetchDepartments();
    }).catch(function (error) { ui.toast(error.message, 'error'); });

    search.addEventListener('input', ui.debounce(function () {
      state.q = search.value.trim();
      state.page = 1;
      fetchDepartments().catch(function (error) { ui.toast(error.message, 'error'); });
    }, 180));
    collegeFilter.addEventListener('change', function () {
      state.college = collegeFilter.value;
      state.page = 1;
      fetchDepartments().catch(function (error) { ui.toast(error.message, 'error'); });
    });
    statusFilter.addEventListener('change', function () {
      state.status = statusFilter.value;
      state.page = 1;
      fetchDepartments().catch(function (error) { ui.toast(error.message, 'error'); });
    });
  }

  function initForm() {
    var form = document.getElementById('departmentForm');
    var saveButton = form.querySelector('button[type="submit"]');
    var fields = {
      name: document.getElementById('departmentName'),
      code: document.getElementById('departmentCode'),
      college: document.getElementById('departmentCollege'),
      head: document.getElementById('departmentHead'),
      status: document.getElementById('departmentStatus'),
      description: document.getElementById('departmentDescription')
    };
    var collegeHint = document.getElementById('departmentCollegeHint');
    var editId = ui.getParam('id');
    var existing = null;
    var headPicker = null;
    saveButton.disabled = true;

    Promise.all([
      CMS.api.get('/options'),
      CMS.api.get('/faculty'),
      editId ? CMS.api.get('/departments/' + encodeURIComponent(editId)) : Promise.resolve(null)
    ]).then(function (results) {
      var options = results[0];
      faculty = results[1];
      existing = results[2];
      colleges = options.colleges;
      if (editId && !existing) {
        ui.flash('That department was not found.', 'error');
        window.location.href = 'departments.html';
        return;
      }

      ui.setOptions(fields.college, [{ value: '', label: 'Select a college' }].concat(colleges.map(function (college) {
        return { value: String(college.id), label: college.name + ' (' + college.code + ')' };
      })), existing ? String(existing.collegeId) : '');
      fields.college.disabled = colleges.length === 0;
      collegeHint.hidden = colleges.length > 0;
      headPicker = CMS.combobox.create(document.getElementById('departmentHeadBox'), {
        items: function () {
          return faculty.map(function (person) {
            return {
              id: person.id,
              label: person.name,
              sub: [person.position, person.department].filter(Boolean).join(' · ')
            };
          });
        },
        emptyText: 'No matching faculty',
        clearLabel: 'Clear department head'
      });
      saveButton.disabled = colleges.length === 0;

      if (existing) {
        fields.name.value = existing.name;
        fields.code.value = existing.code;
        fields.status.value = existing.status;
        fields.description.value = existing.description || '';
        if (existing.headId) headPicker.setValue({ id: existing.headId, label: existing.headName });
        document.querySelector('.cms-page-title').textContent = 'Edit Department';
        document.title = 'Edit Department | PSU CMS';
      }
    }).catch(function (error) {
      ui.toast(error.message, 'error');
    });

    form.addEventListener('submit', function (event) {
      event.preventDefault();
      ui.clearErrors(form);
      var name = fields.name.value.trim();
      var code = fields.code.value.trim().toUpperCase();
      var collegeId = fields.college.value;
      var valid = true;

      if (!name) {
        ui.setError(fields.name, 'Enter the department name.');
        valid = false;
      }
      if (!/^[A-Z0-9-]{1,30}$/.test(code)) {
        ui.setError(fields.code, 'Use letters, numbers, or hyphens (maximum 30 characters).');
        valid = false;
      }
      if (!collegeId) {
        ui.setError(fields.college, 'Choose a college from the database.');
        valid = false;
      }
      if (!valid) {
        ui.toast('Please fix the highlighted fields.', 'error');
        return;
      }

      var selectedHead = headPicker ? headPicker.getValue() : null;
      var payload = {
        name: name,
        code: code,
        collegeId: collegeId,
        headFacultyId: selectedHead ? selectedHead.id : null,
        description: fields.description.value.trim() || null,
        status: fields.status.value
      };
      var request = existing
        ? CMS.api.put('/departments/' + encodeURIComponent(existing.id), payload)
        : CMS.api.post('/departments', payload);
      request.then(function () {
        ui.flash(existing ? 'Department updated.' : 'Department added.', 'success');
        window.location.href = 'departments.html';
      }).catch(function (error) {
        ui.toast(error.message, 'error');
      });
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    if (document.getElementById('departmentForm')) initForm();
    else if (document.getElementById('departmentTableBody')) initList();
  });
})();
