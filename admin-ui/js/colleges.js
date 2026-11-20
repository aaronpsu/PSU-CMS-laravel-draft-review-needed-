(function () {
  var ui = CMS.ui, el = ui.el, S = CMS.structure, api = CMS.api;

  function initList() {
    var body = document.getElementById('collegeTableBody');
    var wrap = document.getElementById('collegeTableWrap');
    var empty = document.getElementById('collegeEmpty');
    var footer = document.getElementById('collegeFooter');
    var search = document.getElementById('collegeSearch');
    var status = document.getElementById('collegeFilterStatus');
    var state = { page: 1, rows: [], request: 0 };
    var pageSize = CMS.config.pageSize.colleges;

    function render() {
      var pages = Math.max(1, Math.ceil(state.rows.length / pageSize));
      state.page = Math.min(state.page, pages);
      ui.clear(body);
      if (!state.rows.length) {
        wrap.hidden = true;
        footer.hidden = true;
        empty.hidden = false;
        ui.renderEmpty(empty, {
          title: search.value || status.value ? 'No matching colleges found' : 'No colleges yet',
          text: search.value || status.value ? 'Try a different search or filter.' : 'Colleges you add will appear here.',
          actionLabel: search.value || status.value ? null : '+ Add College',
          actionHref: 'college-form.html'
        });
        return;
      }

      wrap.hidden = false;
      empty.hidden = true;
      footer.hidden = false;
      state.rows.slice((state.page - 1) * pageSize, state.page * pageSize).forEach(function (college) {
        var tr = el('tr');
        tr.appendChild(el('td', {}, [college.image
          ? el('img', { class: 'cms-thumb', src: college.image, alt: '' })
          : el('span', { class: 'cms-thumb', 'aria-hidden': 'true' })]));
        tr.appendChild(el('td', {}, [el('span', { class: 'cms-cell-title', text: college.name })]));
        tr.appendChild(el('td', { text: college.code }));
        tr.appendChild(el('td', { text: college.deanName || '\u2014' }));
        tr.appendChild(el('td', { text: String(college.departmentCount) }));
        tr.appendChild(el('td', {}, [S.statusBadge(college.status)]));
        tr.appendChild(S.actions(college.name, 'college-form.html?id=' + encodeURIComponent(college.id),
          function () { showCollege(college.id); }, function () { removeCollege(college); }));
        body.appendChild(tr);
      });
      ui.renderFooter(footer, {
        page: state.page, pageSize: pageSize, total: state.rows.length,
        onPage: function (page) { state.page = page; render(); }
      });
    }

    function load() {
      var request = ++state.request;
      return api.query('/colleges', { q: search.value.trim(), status: status.value })
        .then(function (rows) {
          if (request !== state.request) return;
          state.rows = rows;
          state.page = 1;
          render();
        })
        .catch(function (error) {
          if (request !== state.request) return;
          ui.clear(body);
          wrap.hidden = true;
          footer.hidden = true;
          empty.hidden = false;
          ui.renderEmpty(empty, { title: 'Unable to load colleges', text: error.message });
          ui.toast(error.message, 'error');
        });
    }

    function showCollege(id) {
      api.get('/colleges/' + encodeURIComponent(id)).then(function (college) {
        ui.showDetails({
          title: college.name,
          cover: college.image || null,
          rows: [
            ['Code', college.code], ['Dean', college.deanName], ['Contact email', college.contactEmail],
            ['Description', college.description], ['Status', S.statusBadge(college.status)],
            ['Departments', S.listOf(college.departments.map(function (d) { return d.name + ' (' + d.code + ')'; }), 'No departments yet')],
            ['Programs', S.listOf(college.programs.map(function (p) { return p.name + ' (' + p.code + ')'; }), 'No programs yet')]
          ]
        });
      }).catch(function (error) { ui.toast(error.message, 'error'); });
    }

    function removeCollege(college) {
      ui.confirmDialog({
        title: 'Delete college',
        message: 'Delete \u201C' + college.name + '\u201D? Colleges with departments or programs cannot be deleted.',
        confirmLabel: 'Delete'
      }).then(function (confirmed) {
        if (!confirmed) return;
        api.remove('/colleges/' + encodeURIComponent(college.id)).then(function () {
          ui.toast('College deleted.', 'success');
          load();
        }).catch(function (error) { ui.toast(error.message, 'error'); });
      });
    }

    search.addEventListener('input', ui.debounce(load, 180));
    status.addEventListener('change', load);
    load();
  }

  function initForm() {
    var form = document.getElementById('collegeForm');
    var f = {
      name: document.getElementById('collegeName'),
      code: document.getElementById('collegeCode'),
      description: document.getElementById('collegeDescription'),
      dean: document.getElementById('collegeDean'),
      email: document.getElementById('collegeEmail'),
      status: document.getElementById('collegeStatus')
    };
    var image = S.imageField({
      preview: document.getElementById('collegeImagePreview'), button: document.getElementById('collegeImageBtn'),
      remove: document.getElementById('collegeImageRemove'), input: document.getElementById('collegeImageInput'),
      placeholder: 'College Image', alt: 'College image preview', maxWidth: CMS.config.collegeImageMaxWidth
    });
    var validator = S.validator(form);
    var id = ui.getParam('id');
    var submit = form.querySelector('button[type="submit"]');

    function setForm(college) {
      f.name.value = college.name;
      f.code.value = college.code;
      f.description.value = college.description || '';
      f.dean.value = college.deanName || '';
      f.email.value = college.contactEmail || '';
      f.status.value = college.status;
      image.set(college.image);
      if (f.status._cmsRefresh) f.status._cmsRefresh();
    }

    if (id) {
      submit.disabled = true;
      api.get('/colleges/' + encodeURIComponent(id)).then(setForm).catch(function (error) {
        ui.flash(error.message, 'error');
        window.location.href = 'colleges.html';
      }).finally(function () { submit.disabled = false; });
      document.title = 'Edit College | PSU CMS';
    }

    form.addEventListener('submit', function (event) {
      event.preventDefault();
      validator.reset();
      var name = f.name.value.trim();
      var code = f.code.value.trim().toUpperCase();
      var email = f.email.value.trim();
      if (!name) validator.fail(f.name, 'Enter the college name.');
      if (!/^[A-Z0-9-]+$/.test(code)) validator.fail(f.code, 'Use letters, numbers, or hyphens.');
      if (email && !S.isEmail(email)) validator.fail(f.email, 'Enter a valid email address.');
      if (!validator.ok()) return;

      var data = {
        name: name, code: code, description: f.description.value.trim(),
        deanName: f.dean.value.trim(), contactEmail: email,
        image: image.get(), status: f.status.value
      };
      submit.disabled = true;
      var save = id ? api.put('/colleges/' + encodeURIComponent(id), data) : api.post('/colleges', data);
      save.then(function () {
        ui.flash(id ? 'College updated.' : 'College added.', 'success');
        window.location.href = 'colleges.html';
      }).catch(function (error) {
        ui.toast(error.message, 'error');
      }).finally(function () { submit.disabled = false; });
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    if (document.getElementById('collegeForm')) initForm();
    else if (document.getElementById('collegeTableBody')) initList();
  });
})();
