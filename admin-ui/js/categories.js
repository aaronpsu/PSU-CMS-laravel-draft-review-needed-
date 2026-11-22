/* ==========================================================================
   categories.js — Event Categories: list, search, add / edit (modal),
   view (modal) and delete. Names must be unique (case-insensitive) and a
   category that still has events cannot be deleted, so every event keeps
   a valid category. Event counts are calculated from the events data.
   ========================================================================== */
(function () {
  var cfg = CMS.config, ui = CMS.ui, el = ui.el;
  var data = { categories: [], filteredCategories: null, events: [] };

  function eventsIn(catId) { return data.events.filter(function (e) { return String(e.categoryId) === String(catId); }); }

  function init() {
    var state = { q: '', page: 1 };
    var searchRequest = 0;
    var body = document.getElementById('categoryTableBody');
    var tableWrap = document.getElementById('categoryTableWrap');
    var emptyBox = document.getElementById('categoryEmpty');
    var footer = document.getElementById('categoryFooter');
    var searchInput = document.getElementById('categorySearch');

    function matches(c) {
      if (!state.q) return true;
      return (c.name + ' ' + (c.description || '')).toLowerCase().indexOf(state.q.toLowerCase()) !== -1;
    }

    function buildRow(c) {
      var tr = el('tr');
      tr.appendChild(el('td', { class: 'cms-cell-title', text: c.name }));
      tr.appendChild(el('td', { text: c.description || '' }));
      tr.appendChild(el('td', { text: String(eventsIn(c.id).length) }));
      var view = el('button', { type: 'button', class: 'cms-action', 'aria-label': 'View ' + c.name, text: 'View' });
      var edit = el('button', { type: 'button', class: 'cms-action', 'aria-label': 'Edit ' + c.name, text: 'Edit' });
      var del = el('button', { type: 'button', class: 'cms-action cms-action-danger', 'aria-label': 'Delete ' + c.name, text: 'Delete' });
      view.addEventListener('click', function () { showCategory(c); });
      edit.addEventListener('click', function () { openForm(c); });
      del.addEventListener('click', function () { removeCategory(c); });
      tr.appendChild(el('td', {}, [el('div', { class: 'cms-actions' }, [view, edit, del])]));
      return tr;
    }

    function render() {
      var all = data.filteredCategories || data.categories;
      var rows = all.filter(matches);
      var size = cfg.pageSize.categories;
      var pages = Math.max(1, Math.ceil(rows.length / size));
      state.page = Math.min(state.page, pages);
      ui.clear(body); ui.clear(footer);
      if (!rows.length) {
        tableWrap.hidden = true; emptyBox.hidden = false;
        if (!state.q && !data.categories.length) ui.renderEmpty(emptyBox, { title: 'No categories yet', text: 'Items you add will appear here.' });
        else ui.renderEmpty(emptyBox, { title: 'No matching categories found', text: 'Try a different search.' });
        return;
      }
      tableWrap.hidden = false; emptyBox.hidden = true;
      rows.slice((state.page - 1) * size, state.page * size).forEach(function (c) { body.appendChild(buildRow(c)); });
      ui.renderFooter(footer, { page: state.page, pageSize: size, total: rows.length, onPage: function (p) { state.page = p; render(); } });
    }

    function searchCategories() {
      state.q = searchInput.value.trim();
      state.page = 1;
      data.filteredCategories = null;
      render();
      var request = ++searchRequest;
      CMS.api.query('/event-categories', { q: state.q }).then(function (categories) {
        if (request !== searchRequest) return;
        data.filteredCategories = categories;
        render();
      }).catch(function (error) {
        if (request === searchRequest) ui.toast(error.message, 'error');
      });
    }

    function showCategory(c) {
      var list = eventsIn(c.id);
      var titles = list.slice(0, 5).map(function (e) { return e.title; }).join(', ') + (list.length > 5 ? ' and ' + (list.length - 5) + ' more' : '');
      ui.showDetails({ title: 'Event category', rows: [['Name', c.name], ['Description', c.description], ['Number of events', String(list.length)], ['Events', titles]] });
    }

    /* Add (c = null) or edit */
    function openForm(c) {
      var nameInput = el('input', { class: 'cms-input', id: 'categoryName', type: 'text', maxlength: '60', placeholder: 'Enter category name', value: c ? c.name : null });
      var descInput = el('textarea', { class: 'cms-textarea', id: 'categoryDescription', rows: '3', maxlength: '150', placeholder: 'Enter a short description' });
      if (c) descInput.value = c.description || '';
      var nameField = el('div', { class: 'cms-field' }, [el('label', { class: 'cms-label', for: 'categoryName', text: 'Category Name' }), nameInput, el('p', { class: 'cms-field-error' })]);
      var descField = el('div', { class: 'cms-field' }, [el('label', { class: 'cms-label', for: 'categoryDescription', text: 'Description' }), descInput]);
      var cancel = el('button', { type: 'button', class: 'cms-btn cms-btn-outline', 'data-bs-dismiss': 'modal', text: 'Cancel' });
      var saveBtn = el('button', { type: 'button', class: 'cms-btn cms-btn-primary', text: c ? 'Save Changes' : 'Add Category' });
      var modalEl = ui.modalShell(c ? 'Edit Category' : 'Add Category', [nameField, descField], [cancel, saveBtn]);
      var modal = ui.openModal(modalEl);
      modalEl.addEventListener('shown.bs.modal', function () { nameInput.focus(); });
      nameInput.addEventListener('input', function () { nameField.classList.remove('has-error'); nameInput.classList.remove('is-invalid'); });
      var saving = false;

      function submit() {
        if (saving) return;
        ui.clearErrors(modalEl);
        var name = nameInput.value.trim().replace(/\s+/g, ' ');
        if (!name) { ui.setError(nameInput, 'Please enter a category name.'); nameInput.focus(); return; }
        var duplicate = data.categories.some(function (x) { return (!c || String(x.id) !== String(c.id)) && x.name.trim().toLowerCase() === name.toLowerCase(); });
        if (duplicate) { ui.setError(nameInput, 'A category with this name already exists.'); nameInput.focus(); return; }
        var record = { name: name, description: descInput.value.trim() };
        var save = c
          ? CMS.api.put('/event-categories/' + encodeURIComponent(c.id), record)
          : CMS.api.post('/event-categories', record);
        saving = true;
        saveBtn.disabled = true;
        save.then(function () { return refreshData(); }).then(function () {
          modal.hide();
          ui.toast(c ? 'Category updated.' : 'Category added.', 'success');
          render();
        }).catch(function (error) { ui.toast(error.message, 'error'); }).then(function () {
          saving = false;
          saveBtn.disabled = false;
        });
      }
      saveBtn.addEventListener('click', submit);
      nameInput.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); submit(); } });
    }

    function removeCategory(c) {
      var inUse = eventsIn(c.id).length;
      if (inUse) {
        var close = el('button', { type: 'button', class: 'cms-btn cms-btn-primary', 'data-bs-dismiss': 'modal', text: 'OK' });
        ui.openModal(ui.modalShell('Category in use', [el('p', { text: '\u201C' + c.name + '\u201D is used by ' + inUse + (inUse === 1 ? ' event' : ' events') +
          '. Move those events to another category or delete them first.' })], [close]));
        return;
      }
      ui.confirmDialog({ title: 'Delete category', message: 'Delete the category \u201C' + c.name + '\u201D?', confirmLabel: 'Delete' }).then(function (yes) {
        if (!yes) return;
        CMS.api.remove('/event-categories/' + encodeURIComponent(c.id)).then(function () {
          return refreshData();
        }).then(function () { ui.toast('Category deleted.', 'success'); render(); })
          .catch(function (error) { ui.toast(error.message, 'error'); });
      });
    }

    document.getElementById('categoryAdd').addEventListener('click', function () { openForm(null); });
    searchInput.addEventListener('input', ui.debounce(searchCategories, 180));
    refreshData().then(render).catch(function (error) { ui.toast(error.message, 'error'); });
  }

  function refreshData() {
    return Promise.all([CMS.api.get('/event-categories'), CMS.api.get('/events')]).then(function (results) {
      data.categories = results[0];
      data.filteredCategories = null;
      data.events = results[1];
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    if (document.body.getAttribute('data-page') === 'event-categories') init();
  });
})();
