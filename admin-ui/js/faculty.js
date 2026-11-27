(function () {
  var ui = CMS.ui, el = ui.el, api = CMS.api;
  var PAGE_SIZE = 8;
  var state = { q: '', college: '', department: '', status: '', rows: [], page: 1, request: 0 };
  var options = { colleges: [], departments: [] };

  function initials(person) {
    return ((person.firstName || '').trim().charAt(0) + (person.lastName || '').trim().charAt(0)).toUpperCase() || '?';
  }

  function load() {
    var request = ++state.request;
    return Promise.all([
      api.query('/faculty', {
        q: state.q,
        college: state.college,
        department: state.department,
        status: state.status
      }),
      api.get('/options')
    ]).then(function (results) {
      if (request !== state.request) return;
      state.rows = results[0];
      options.colleges = results[1].colleges || [];
      options.departments = results[1].departments || [];
      renderFilters();
      render();
    }).catch(function (error) {
      if (request !== state.request) return;
      renderError(error);
    });
  }

  function renderFilters() {
    ui.setOptions(document.getElementById('facultyCollegeFilter'), [
      { value: '', label: 'All Colleges' }
    ].concat(options.colleges.map(function (college) {
      return { value: String(college.id), label: college.name };
    })), state.college);

    var departments = options.departments.filter(function (department) {
      return !state.college || String(department.collegeId) === state.college;
    });
    ui.setOptions(document.getElementById('facultyDepartmentFilter'), [
      { value: '', label: 'All Departments' }
    ].concat(departments.map(function (department) {
      return { value: String(department.id), label: department.name };
    })), state.department);
  }

  function personPhoto(person) {
    if (person.photoUrl) {
      return el('img', { class: 'cms-thumb res-person-thumb', src: person.photoUrl, alt: '' });
    }
    return el('span', { class: 'cms-thumb res-person-thumb res-person-initials', text: initials(person), 'aria-hidden': 'true' });
  }

  function rowFor(person) {
    var row = el('tr');
    row.appendChild(el('td', {}, [personPhoto(person)]));
    row.appendChild(el('td', { class: 'cms-cell-title', text: person.name || 'Unnamed personnel' }));
    row.appendChild(el('td', { text: person.position || '—' }));
    row.appendChild(el('td', { text: person.college || '—' }));
    row.appendChild(el('td', { text: person.department || 'Unassigned' }));
    row.appendChild(el('td', { text: person.email || '—' }));
    row.appendChild(el('td', {}, [
      ui.badge(person.status === 'active' ? 'published' : 'draft', person.status === 'active' ? 'Active' : 'Inactive')
    ]));
    row.appendChild(el('td', {}, [el('div', { class: 'cms-actions' }, [
      el('button', { type: 'button', class: 'cms-action', text: 'View', 'data-person-view': String(person.id) }),
      el('a', { class: 'cms-action', href: 'personnel-form.html?id=' + encodeURIComponent(person.id), text: 'Edit' }),
      el('button', { type: 'button', class: 'cms-action cms-action-danger', text: 'Delete', 'data-person-delete': String(person.id) })
    ])]));
    return row;
  }

  function render() {
    var body = document.getElementById('facultyTableBody');
    var table = document.getElementById('facultyTableWrap');
    var empty = document.getElementById('facultyEmpty');
    var footer = document.getElementById('facultyFooter');
    var pages = Math.max(1, Math.ceil(state.rows.length / PAGE_SIZE));
    state.page = Math.min(state.page, pages);
    ui.clear(body);
    ui.clear(footer);

    if (!state.rows.length) {
      table.hidden = true;
      empty.hidden = false;
      ui.renderEmpty(empty, {
        title: state.q || state.college || state.department || state.status ? 'No matching personnel found' : 'No personnel records yet',
        text: state.q || state.college || state.department || state.status
          ? 'Try changing your search or filters.'
          : 'Add a faculty or staff record to get started.'
      });
      return;
    }

    table.hidden = false;
    empty.hidden = true;
    state.rows.slice((state.page - 1) * PAGE_SIZE, state.page * PAGE_SIZE).forEach(function (person) {
      body.appendChild(rowFor(person));
    });
    ui.renderFooter(footer, {
      page: state.page,
      pageSize: PAGE_SIZE,
      total: state.rows.length,
      onPage: function (page) { state.page = page; render(); }
    });
  }

  function renderError(error) {
    var table = document.getElementById('facultyTableWrap');
    var empty = document.getElementById('facultyEmpty');
    var footer = document.getElementById('facultyFooter');
    table.hidden = true;
    footer.hidden = true;
    empty.hidden = false;
    ui.renderEmpty(empty, {
      title: 'Unable to load personnel',
      text: error.message,
      actionLabel: 'Try again',
      actionHref: '#'
    });
    var retry = empty.querySelector('a');
    if (retry) retry.addEventListener('click', function (event) {
      event.preventDefault();
      load();
    });
    ui.toast(error.message, 'error');
  }

  function showDetails(person) {
    ui.showDetails({
      title: person.name || 'Personnel record',
      cover: person.photoUrl || null,
      rows: [
        ['Position', person.position],
        ['College', person.college],
        ['Department', person.department],
        ['Campus', person.campus],
        ['Email', person.email],
        ['Contact Number', person.contactNumber],
        ['Educational Background', person.education],
        ['Biography', person.biography],
        ['Status', person.status]
      ]
    });
  }

  function deletePerson(person) {
    ui.confirmDialog({
      title: 'Delete personnel record',
      message: 'Delete "' + person.name + '"? This removes the profile from the public directory.',
      confirmLabel: 'Delete'
    }).then(function (confirmed) {
      if (!confirmed) return;
      return api.remove('/faculty/' + encodeURIComponent(person.id)).then(function () {
        ui.toast('Personnel record deleted.', 'success');
        return load();
      }).catch(function (error) { ui.toast(error.message, 'error'); });
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    if (document.body.getAttribute('data-page') !== 'personnel') return;

    var search = document.getElementById('facultySearch');
    var collegeFilter = document.getElementById('facultyCollegeFilter');
    var departmentFilter = document.getElementById('facultyDepartmentFilter');
    var statusFilter = document.getElementById('facultyStatusFilter');
    var body = document.getElementById('facultyTableBody');

    search.addEventListener('input', ui.debounce(function () {
      state.q = search.value.trim();
      state.page = 1;
      load();
    }, 180));
    collegeFilter.addEventListener('change', function () {
      state.college = collegeFilter.value;
      state.department = '';
      state.page = 1;
      renderFilters();
      load();
    });
    departmentFilter.addEventListener('change', function () {
      state.department = departmentFilter.value;
      state.page = 1;
      load();
    });
    statusFilter.addEventListener('change', function () {
      state.status = statusFilter.value;
      state.page = 1;
      load();
    });
    body.addEventListener('click', function (event) {
      var view = event.target.closest('[data-person-view]');
      var remove = event.target.closest('[data-person-delete]');
      var person;
      if (view) {
        person = state.rows.filter(function (row) { return String(row.id) === view.getAttribute('data-person-view'); })[0];
        if (person) showDetails(person);
      } else if (remove) {
        person = state.rows.filter(function (row) { return String(row.id) === remove.getAttribute('data-person-delete'); })[0];
        if (person) deletePerson(person);
      }
    });
    load();
  });
})();
