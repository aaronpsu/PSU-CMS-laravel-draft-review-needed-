(function () {
  var ui = CMS.ui, el = ui.el, S = CMS.structure, api = CMS.api;

  function fillFilter(select, allLabel, items) {
    ui.setOptions(select, [{ value: '', label: allLabel }].concat(items.map(function (item) {
      return { value: item.value, label: item.label };
    })), '');
  }

  function initList() {
    var body = document.getElementById('programTableBody');
    var wrap = document.getElementById('programTableWrap');
    var empty = document.getElementById('programEmpty');
    var footer = document.getElementById('programFooter');
    var search = document.getElementById('programSearch');
    var collegeFilter = document.getElementById('programFilterCollege');
    var levelFilter = document.getElementById('programFilterLevel');
    var statusFilter = document.getElementById('programFilterStatus');
    var state = { page: 1, rows: [], request: 0 };
    var pageSize = CMS.config.pageSize.programs;

    function render() {
      var pages = Math.max(1, Math.ceil(state.rows.length / pageSize));
      state.page = Math.min(state.page, pages);
      ui.clear(body);
      if (!state.rows.length) {
        wrap.hidden = true;
        footer.hidden = true;
        empty.hidden = false;
        ui.renderEmpty(empty, {
          title: search.value || collegeFilter.value || levelFilter.value || statusFilter.value
            ? 'No matching programs found' : 'No academic programs yet',
          text: search.value || collegeFilter.value || levelFilter.value || statusFilter.value
            ? 'Try a different search or filter.' : 'Programs you add will appear here.',
          actionLabel: search.value || collegeFilter.value || levelFilter.value || statusFilter.value
            ? null : '+ Add Academic Program',
          actionHref: 'program-form.html'
        });
        return;
      }

      wrap.hidden = false;
      empty.hidden = true;
      footer.hidden = false;
      state.rows.slice((state.page - 1) * pageSize, state.page * pageSize).forEach(function (program) {
        var tr = el('tr');
        tr.appendChild(el('td', {}, [el('span', { class: 'cms-cell-title', text: program.name })]));
        tr.appendChild(el('td', { text: program.code }));
        tr.appendChild(el('td', { text: program.degreeLevel }));
        tr.appendChild(el('td', { text: program.collegeName }));
        tr.appendChild(el('td', { text: program.departmentName }));
        tr.appendChild(el('td', { text: program.description || '' }));
        tr.appendChild(el('td', {}, [S.statusBadge(program.status)]));
        tr.appendChild(S.actions(program.name, 'program-form.html?id=' + encodeURIComponent(program.id),
          function () { showProgram(program.id); }, function () { removeProgram(program); }));
        body.appendChild(tr);
      });
      ui.renderFooter(footer, {
        page: state.page, pageSize: pageSize, total: state.rows.length,
        onPage: function (page) { state.page = page; render(); }
      });
    }

    function load() {
      var request = ++state.request;
      return api.query('/programs', {
        q: search.value.trim(), college: collegeFilter.value,
        level: levelFilter.value, status: statusFilter.value
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
        ui.renderEmpty(empty, { title: 'Unable to load programs', text: error.message });
        ui.toast(error.message, 'error');
      });
    }

    function showProgram(id) {
      api.get('/programs/' + encodeURIComponent(id)).then(function (program) {
        ui.showDetails({
          title: program.name,
          rows: [
            ['Code', program.code], ['Degree level', program.degreeLevel],
            ['College', program.collegeName], ['Department', program.departmentName],
            ['Description', program.description], ['Status', S.statusBadge(program.status)]
          ]
        });
      }).catch(function (error) { ui.toast(error.message, 'error'); });
    }

    function removeProgram(program) {
      ui.confirmDialog({
        title: 'Delete program',
        message: 'Delete \u201C' + program.name + '\u201D? This cannot be undone.',
        confirmLabel: 'Delete'
      }).then(function (confirmed) {
        if (!confirmed) return;
        api.remove('/programs/' + encodeURIComponent(program.id)).then(function () {
          ui.toast('Program deleted.', 'success');
          load();
        }).catch(function (error) { ui.toast(error.message, 'error'); });
      });
    }

    api.get('/options').then(function (options) {
      fillFilter(collegeFilter, 'All Colleges', options.colleges.map(function (college) {
        return { value: college.id, label: college.name };
      }));
      fillFilter(levelFilter, 'All Levels', options.degreeLevels.map(function (level) {
        return { value: level, label: level };
      }));
      [collegeFilter, levelFilter, statusFilter].forEach(function (filter) {
        filter.addEventListener('change', load);
      });
      search.addEventListener('input', ui.debounce(load, 180));
      return load();
    }).catch(function (error) {
      ui.renderEmpty(empty, { title: 'Unable to load program filters', text: error.message });
      empty.hidden = false;
      wrap.hidden = true;
      ui.toast(error.message, 'error');
    });
  }

  function initForm() {
    var form = document.getElementById('programForm');
    var f = {
      name: document.getElementById('programName'),
      code: document.getElementById('programCode'),
      level: document.getElementById('programLevel'),
      college: document.getElementById('programCollege'),
      department: document.getElementById('programDepartment'),
      status: document.getElementById('programStatus'),
      description: document.getElementById('programDescription')
    };
    var collegeHint = document.getElementById('programCollegeHint');
    var validator = S.validator(form);
    var id = ui.getParam('id');
    var submit = form.querySelector('button[type="submit"]');
    var departments = [];

    function fillDepartments(collegeId, selectedId) {
      var available = departments.filter(function (department) {
        return String(department.collegeId) === String(collegeId);
      });
      var placeholder = !collegeId ? 'Select a college'
        : available.length ? 'Select a department' : 'No departments in this college';
      ui.setOptions(f.department, [{ value: '', label: placeholder }].concat(available.map(function (department) {
        return { value: department.id, label: department.name };
      })), selectedId || '');
    }

    submit.disabled = true;
    var requests = [api.get('/options')];
    if (id) requests.push(api.get('/programs/' + encodeURIComponent(id)));
    Promise.all(requests).then(function (results) {
      var options = results[0];
      departments = options.departments;
      var program = id ? results[1] : null;
      var degreeLevels = options.degreeLevels.length ? options.degreeLevels : CMS.config.degreeLevels;
      ui.setOptions(f.level, degreeLevels.map(function (level) { return { value: level, label: level }; }), program ? program.degreeLevel : '');
      ui.setOptions(f.college, [{ value: '', label: 'Select a college' }].concat(options.colleges.map(function (college) {
        return { value: college.id, label: college.name };
      })), program ? program.collegeId : '');
      fillDepartments(program ? program.collegeId : '', program ? program.departmentId : '');
      collegeHint.hidden = options.colleges.length > 0;
      if (program) {
        document.title = 'Edit Academic Program | PSU CMS';
        f.name.value = program.name;
        f.code.value = program.code;
        f.description.value = program.description || '';
        f.status.value = program.status;
        if (f.status._cmsRefresh) f.status._cmsRefresh();
      }
    }).catch(function (error) {
      ui.flash(error.message, 'error');
      window.location.href = 'academic-programs.html';
    }).finally(function () { submit.disabled = false; });

    f.college.addEventListener('change', function () { fillDepartments(f.college.value, ''); });
    form.addEventListener('submit', function (event) {
      event.preventDefault();
      validator.reset();
      var name = f.name.value.trim();
      var code = f.code.value.trim();
      if (!name) validator.fail(f.name, 'Enter the program name.');
      if (!code || !/^[A-Za-z0-9 .-]+$/.test(code)) validator.fail(f.code, 'Use letters, numbers, spaces, periods, or hyphens.');
      if (!f.level.value) validator.fail(f.level, 'Choose a degree level.');
      if (!f.college.value) validator.fail(f.college, 'Choose a college.');
      if (!f.department.value) validator.fail(f.department, 'Choose a department.');
      if (!validator.ok()) return;

      var data = {
        name: name, code: code, degreeLevel: f.level.value,
        collegeId: f.college.value, departmentId: f.department.value,
        description: f.description.value.trim(), status: f.status.value
      };
      submit.disabled = true;
      var save = id ? api.put('/programs/' + encodeURIComponent(id), data) : api.post('/programs', data);
      save.then(function () {
        ui.flash(id ? 'Program updated.' : 'Program added.', 'success');
        window.location.href = 'academic-programs.html';
      }).catch(function (error) {
        ui.toast(error.message, 'error');
      }).finally(function () { submit.disabled = false; });
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    if (document.getElementById('programForm')) initForm();
    else if (document.getElementById('programTableBody')) initList();
  });
})();
