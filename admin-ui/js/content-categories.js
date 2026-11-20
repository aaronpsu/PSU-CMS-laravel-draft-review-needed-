(function () {
  var ui = CMS.ui, el = ui.el, api = CMS.api;

  function init() {
    var type = document.getElementById('contentCategoryType');
    var search = document.getElementById('contentCategorySearch');
    var body = document.getElementById('contentCategoryBody');
    var wrap = document.getElementById('contentCategoryWrap');
    var empty = document.getElementById('contentCategoryEmpty');
    var footer = document.getElementById('contentCategoryFooter');
    var state = { rows: [], page: 1, request: 0 };
    var pageSize = 10;

    function endpoint() {
      return type.value === 'announcement' ? '/announcement-categories' : '/news-categories';
    }

    function render() {
      var pages = Math.max(1, Math.ceil(state.rows.length / pageSize));
      state.page = Math.min(state.page, pages);
      ui.clear(body);
      if (!state.rows.length) {
        wrap.hidden = true;
        footer.hidden = true;
        empty.hidden = false;
        ui.renderEmpty(empty, {
          title: search.value ? 'No matching categories found' : 'No categories yet',
          text: search.value ? 'Try another search.' : 'Add a category to make it available in the content editor.',
          actionLabel: search.value ? null : '+ Add Category',
          actionHref: '#'
        });
        var addLink = empty.querySelector('a');
        if (addLink) addLink.addEventListener('click', function (event) {
          event.preventDefault();
          openForm(null);
        });
        return;
      }

      wrap.hidden = false;
      empty.hidden = true;
      footer.hidden = false;
      state.rows.slice((state.page - 1) * pageSize, state.page * pageSize).forEach(function (category) {
        var row = el('tr');
        row.appendChild(el('td', { text: category.name }));
        row.appendChild(el('td', { text: category.description || '\u2014' }));
        row.appendChild(el('td', { text: String(category.contentCount) }));
        row.appendChild(actions(category));
        body.appendChild(row);
      });
      ui.renderFooter(footer, {
        page: state.page, pageSize: pageSize, total: state.rows.length,
        onPage: function (page) { state.page = page; render(); }
      });
    }

    function load() {
      var request = ++state.request;
      api.query(endpoint(), { q: search.value.trim() }).then(function (rows) {
        if (request !== state.request) return;
        state.rows = rows;
        state.page = 1;
        render();
      }).catch(function (error) {
        if (request !== state.request) return;
        wrap.hidden = true;
        footer.hidden = true;
        empty.hidden = false;
        ui.renderEmpty(empty, { title: 'Unable to load categories', text: error.message });
        ui.toast(error.message, 'error');
      });
    }

    function actions(category) {
      var view = el('button', { type: 'button', class: 'cms-action', text: 'View', 'aria-label': 'View ' + category.name });
      var edit = el('button', { type: 'button', class: 'cms-action', text: 'Edit', 'aria-label': 'Edit ' + category.name });
      var remove = el('button', { type: 'button', class: 'cms-action cms-action-danger', text: 'Delete', 'aria-label': 'Delete ' + category.name });
      view.addEventListener('click', function () {
        ui.showDetails({ title: category.name, rows: [
          ['Category type', type.value === 'news' ? 'News' : 'Announcements'],
          ['Description', category.description], ['Linked content', String(category.contentCount)]
        ] });
      });
      edit.addEventListener('click', function () { openForm(category); });
      remove.addEventListener('click', function () { deleteCategory(category); });
      return el('td', {}, [el('div', { class: 'cms-actions' }, [view, edit, remove])]);
    }

    function openForm(category) {
      var name = el('input', { class: 'cms-input', type: 'text', maxlength: '100', placeholder: 'Category name' });
      var description = el('textarea', { class: 'cms-textarea', rows: '3', maxlength: '5000', placeholder: 'Description (optional)' });
      if (category) {
        name.value = category.name;
        description.value = category.description || '';
      }
      var save = el('button', { type: 'button', class: 'cms-btn cms-btn-primary', text: category ? 'Save Changes' : 'Add Category' });
      var modal = ui.openModal(ui.modalShell(category ? 'Edit Category' : 'Add Category', [
        formField('Category Name', name), formField('Description', description)
      ], [
        el('button', { type: 'button', class: 'cms-btn cms-btn-outline', 'data-bs-dismiss': 'modal', text: 'Cancel' }),
        save
      ]));
      var modalEl = document.body.lastElementChild;
      save.addEventListener('click', function () {
        var payload = { name: name.value.trim(), description: description.value.trim() };
        if (!payload.name) {
          ui.toast('Enter a category name.', 'error');
          name.focus();
          return;
        }
        save.disabled = true;
        var request = category
          ? api.put(endpoint() + '/' + encodeURIComponent(category.id), payload)
          : api.post(endpoint(), payload);
        request.then(function () {
          modal.hide();
          ui.toast(category ? 'Category updated.' : 'Category added.', 'success');
          return load();
        }).catch(function (error) { ui.toast(error.message, 'error'); })
          .finally(function () { save.disabled = false; });
      });
      modalEl.addEventListener('shown.bs.modal', function () { name.focus(); });
    }

    function deleteCategory(category) {
      ui.confirmDialog({
        title: 'Delete category',
        message: 'Delete \u201C' + category.name + '\u201D? Categories assigned to content cannot be deleted.',
        confirmLabel: 'Delete'
      }).then(function (confirmed) {
        if (!confirmed) return;
        api.remove(endpoint() + '/' + encodeURIComponent(category.id)).then(function () {
          ui.toast('Category deleted.', 'success');
          return load();
        }).catch(function (error) { ui.toast(error.message, 'error'); });
      });
    }

    document.getElementById('contentCategoryAdd').addEventListener('click', function () { openForm(null); });
    type.addEventListener('change', load);
    search.addEventListener('input', ui.debounce(load, 180));
    load();
  }

  function formField(label, input) {
    return el('div', { class: 'cms-field' }, [el('label', { class: 'cms-label', text: label }), input]);
  }

  document.addEventListener('DOMContentLoaded', function () {
    if (document.getElementById('contentCategoryBody')) init();
  });
})();
