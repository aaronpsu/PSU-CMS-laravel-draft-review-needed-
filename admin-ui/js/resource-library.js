(function () {
  var ui = CMS.ui, el = ui.el, api = CMS.api;
  var PAGE_SIZE = 8;
  var DOCUMENT_TYPES = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'png', 'jpg', 'jpeg'];

  function fileType(file) {
    var match = /\.([a-z0-9]+)$/i.exec(file.fileName || '');
    return match ? match[1].toLowerCase() : String(file.mimeType || '').split('/').pop().toLowerCase();
  }

  function formattedDate(value) {
    return ui.formatDate(value) || '—';
  }

  function actionLink(label, href, accessibleName) {
    return el('a', { class: 'cms-action', href: href, text: label, 'aria-label': accessibleName });
  }

  function init() {
    var type = new URLSearchParams(window.location.search).get('type') || 'documents';
    var categoriesPage = type === 'categories';
    var media = type === 'media';
    var title = categoriesPage ? 'Document Categories' : (media ? 'Media Library' : 'Document Library');
    var pageSize = PAGE_SIZE;
    var state = { rows: [], categories: [], page: 1, request: 0 };
    var search = document.getElementById('resourceSearch');
    var categoryFilter = document.getElementById('resourceCategoryFilter');
    var typeFilter = document.getElementById('resourceTypeFilter');
    var viewFilter = document.getElementById('resourceViewFilter');
    var categoryFilterWrap = document.getElementById('resourceCategoryFilterWrap');
    var typeFilterWrap = document.getElementById('resourceTypeFilterWrap');
    var viewFilterWrap = document.getElementById('resourceViewFilterWrap');
    var upload = document.getElementById('resourceUpload');
    var categoryLink = document.getElementById('resourceCategories');
    var head = document.getElementById('resourceHead');
    var body = document.getElementById('resourceBody');
    var table = document.getElementById('resourceTableWrap');
    var grid = document.getElementById('resourceMediaGrid');
    var empty = document.getElementById('resourceEmpty');
    var footer = document.getElementById('resourceFooter');

    document.title = title + ' | PSU CMS';
    document.getElementById('resourceTitle').textContent = title;
    document.getElementById('resourceCrumb').textContent = title;
    document.getElementById('resourceSubtitle').textContent = categoriesPage
      ? 'Organize documents using the categories stored in the CMS database.'
      : (media
        ? 'Manage images and their metadata stored in the CMS media table.'
        : 'Manage files, categories, and descriptions stored in the CMS documents table.');

    if (categoriesPage) {
      upload.hidden = true;
      categoryLink.hidden = true;
      categoryFilterWrap.hidden = true;
      typeFilterWrap.hidden = true;
      viewFilterWrap.hidden = true;
      var addCategoryButton = el('button', {
        type: 'button',
        class: 'cms-btn cms-btn-primary',
        text: '+ Add Category',
        'data-category-add': ''
      });
      document.getElementById('resourceFilters').appendChild(addCategoryButton);
      renderHead(['Category Name', 'Description', 'Documents', 'Actions']);
      addCategoryButton.addEventListener('click', function () { openCategoryForm(null); });
      search.addEventListener('input', ui.debounce(function () {
        state.page = 1;
        renderCategories();
      }, 180));
      body.addEventListener('click', handleCategoryAction);
      loadCategories();
      return;
    }

    upload.href = media ? 'upload-media.html' : 'upload-document.html';
    categoryLink.hidden = media;
    categoryFilterWrap.hidden = media;
    typeFilterWrap.hidden = false;
    viewFilterWrap.hidden = !media;
    if (media) {
      ui.setOptions(typeFilter, [{ value: '', label: 'All File Types' }].concat(
        ['jpg', 'png', 'gif', 'webp'].map(function (extension) {
          return { value: extension, label: extension.toUpperCase() };
        })
      ));
      renderHead(['Preview', 'Name', 'Type', 'Dimensions', 'File Size', 'Uploaded', 'Actions']);
    } else {
      ui.setOptions(typeFilter, [{ value: '', label: 'All File Types' }].concat(
        DOCUMENT_TYPES.map(function (extension) { return { value: extension, label: extension.toUpperCase() }; })
      ));
      renderHead(['Title', 'Type', 'Category', 'File Size', 'Uploaded', 'By', 'Actions']);
      ui.setOptions(categoryFilter, [{ value: '', label: 'All Categories' }]);
    }

    var initialCategory = new URLSearchParams(window.location.search).get('category') || '';
    search.addEventListener('input', ui.debounce(function () {
      state.page = 1;
      loadLibrary();
    }, 180));
    typeFilter.addEventListener('change', function () { state.page = 1; renderLibrary(); });
    if (media) {
      viewFilter.addEventListener('change', function () { state.page = 1; renderLibrary(); });
    } else {
      categoryFilter.addEventListener('change', function () { state.page = 1; renderLibrary(); });
    }
    loadLibrary().then(function () {
      if (initialCategory) {
        categoryFilter.value = initialCategory;
        ui.syncSelect(categoryFilter);
        renderLibrary();
      }
    });

    function renderHead(labels) {
      ui.clear(head);
      labels.forEach(function (label) { head.appendChild(el('th', { scope: 'col', text: label })); });
    }

    function loadLibrary() {
      var request = ++state.request;
      var operation = media
        ? api.query('/media', { q: search.value.trim() }).then(function (rows) {
          return { rows: rows, categories: [] };
        })
        : Promise.all([
          api.query('/documents', { q: search.value.trim() }),
          api.get('/document-categories')
        ]).then(function (result) {
          return { rows: result[0], categories: result[1] };
        });

      return operation.then(function (result) {
        if (request !== state.request) return;
        state.rows = result.rows;
        state.categories = result.categories;
        state.page = 1;
        if (!media) {
          var options = [{ value: '', label: 'All Categories' }].concat(state.categories.map(function (category) {
            return { value: String(category.id), label: category.name };
          }));
          ui.setOptions(categoryFilter, options, categoryFilter.value);
        }
        renderLibrary();
      }).catch(function (error) {
        if (request !== state.request) return;
        renderError(error, 'Unable to load ' + title.toLowerCase());
      });
    }

    function filteredRows() {
      return state.rows.filter(function (file) {
        if (!media && categoryFilter.value && String(file.categoryId) !== categoryFilter.value) return false;
        if (typeFilter.value && fileType(file) !== typeFilter.value) return false;
        return true;
      });
    }

    function renderLibrary() {
      var rows = filteredRows();
      var pages = Math.max(1, Math.ceil(rows.length / pageSize));
      state.page = Math.min(state.page, pages);
      var pageRows = rows.slice((state.page - 1) * pageSize, state.page * pageSize);
      ui.clear(body);
      ui.clear(grid);
      ui.clear(footer);

      if (!pageRows.length) {
        table.hidden = true;
        grid.hidden = true;
        empty.hidden = false;
        ui.renderEmpty(empty, {
          title: rows.length || search.value.trim() ? 'No matching files found' : 'No files yet',
          text: rows.length || search.value.trim() ? 'Try changing your search or filters.' : 'Files you upload will appear here.',
          actionLabel: rows.length || search.value.trim() ? null : '+ Upload',
          actionHref: upload.href
        });
        return;
      }

      empty.hidden = true;
      if (media && viewFilter.value === 'grid') {
        table.hidden = true;
        grid.hidden = false;
        pageRows.forEach(function (file) { grid.appendChild(mediaCard(file)); });
      } else {
        table.hidden = false;
        grid.hidden = true;
        pageRows.forEach(function (file) { body.appendChild(media ? mediaRow(file) : documentRow(file)); });
      }
      ui.renderFooter(footer, {
        page: state.page,
        pageSize: pageSize,
        total: rows.length,
        onPage: function (page) { state.page = page; renderLibrary(); }
      });
    }

    function documentRow(file) {
      var row = el('tr');
      row.appendChild(el('td', { class: 'cms-cell-title', text: file.title || file.fileName }));
      row.appendChild(el('td', { text: fileType(file).toUpperCase() }));
      row.appendChild(el('td', { text: file.categoryName || 'Uncategorized' }));
      row.appendChild(el('td', { text: ui.formatSize(file.fileSize) || '—' }));
      row.appendChild(el('td', { text: formattedDate(file.createdAt) }));
      row.appendChild(el('td', { text: file.uploadedBy || '—' }));
      row.appendChild(resourceActions(file, false));
      return row;
    }

    function mediaRow(file) {
      var row = el('tr');
      var preview = file.url && file.available
        ? el('img', { class: 'cms-thumb', src: file.url, alt: file.altText || '' })
        : el('span', { class: 'cms-thumb', 'aria-hidden': 'true' });
      var dimensions = el('td', { text: '—' });
      function setDimensions() {
        if (preview.naturalWidth && preview.naturalHeight) {
          dimensions.textContent = preview.naturalWidth + ' \u00D7 ' + preview.naturalHeight;
        }
      }
      if (preview.tagName === 'IMG') {
        preview.addEventListener('load', setDimensions);
        if (preview.complete) setDimensions();
      }
      row.appendChild(el('td', {}, [preview]));
      row.appendChild(el('td', { class: 'cms-cell-title', text: file.title || file.fileName }));
      row.appendChild(el('td', { text: fileType(file).toUpperCase() }));
      row.appendChild(dimensions);
      row.appendChild(el('td', { text: ui.formatSize(file.fileSize) || '—' }));
      row.appendChild(el('td', { text: formattedDate(file.createdAt) }));
      row.appendChild(resourceActions(file, true));
      return row;
    }

    function resourceActions(file, isMedia) {
      var editHref = isMedia ? 'upload-media.html?id=' : 'upload-document.html?id=';
      var actions = [
        actionLink('Edit', editHref + encodeURIComponent(file.id), 'Edit ' + (file.title || file.fileName))
      ];
      if (file.available && file.url) {
        var viewUrl = isMedia ? file.url : file.url + '?download=0';
        actions.unshift(actionLink('View', viewUrl, 'View ' + file.fileName));
        actions[0].target = '_blank';
        actions[0].rel = 'noopener';
        if (!isMedia) {
          actions.splice(1, 0, actionLink('Download', file.url, 'Download ' + file.fileName));
        }
      } else {
        actions.unshift(el('span', { class: 'cms-action', text: 'Missing file', 'aria-disabled': 'true' }));
      }
      actions.push(el('button', {
        type: 'button',
        class: 'cms-action cms-action-danger',
        text: 'Delete',
        'data-resource-delete': String(file.id),
        'data-resource-name': file.title || file.fileName
      }));
      var cell = el('td', {}, [el('div', { class: 'cms-actions' }, actions)]);
      return cell;
    }

    function mediaCard(file) {
      var thumbnail = file.url && file.available
        ? el('img', { src: file.url, alt: file.altText || '' })
        : el('span', { text: 'Preview unavailable' });
      return el('article', { class: 'cms-card res-media-card' }, [
        el('div', { class: 'res-media-thumb' }, [thumbnail]),
        el('h2', { class: 'res-media-name', text: file.title || file.fileName }),
        el('p', { class: 'res-media-meta', text: [
          fileType(file).toUpperCase(), formattedDate(file.createdAt), ui.formatSize(file.fileSize) || '—'
        ].join(' · ') }),
        el('div', { class: 'cms-actions' }, [
          actionLink('Edit', 'upload-media.html?id=' + encodeURIComponent(file.id), 'Edit ' + file.fileName),
          el('button', {
            type: 'button',
            class: 'cms-action cms-action-danger',
            text: 'Delete',
            'data-resource-delete': String(file.id),
            'data-resource-name': file.title || file.fileName
          })
        ])
      ]);
    }

    document.addEventListener('click', function (event) {
      var button = event.target.closest('[data-resource-delete]');
      if (!button) return;
      var id = button.getAttribute('data-resource-delete');
      var fileName = button.getAttribute('data-resource-name');
      ui.confirmDialog({
        title: 'Delete resource',
        message: 'Delete "' + fileName + '"? Resources used by published content cannot be deleted.',
        confirmLabel: 'Delete'
      }).then(function (confirmed) {
        if (!confirmed) return;
        return api.remove((media ? '/media/' : '/documents/') + encodeURIComponent(id)).then(function () {
          ui.toast('Resource deleted.', 'success');
          return loadLibrary();
        }).catch(function (error) { ui.toast(error.message, 'error'); });
      });
    });

    function renderError(error, heading) {
      state.rows = [];
      ui.clear(body);
      ui.clear(grid);
      table.hidden = true;
      grid.hidden = true;
      footer.hidden = true;
      empty.hidden = false;
      ui.renderEmpty(empty, { title: heading, text: error.message, actionLabel: 'Try again', actionHref: '#' });
      var retry = empty.querySelector('a');
      if (retry) retry.addEventListener('click', function (event) {
        event.preventDefault();
        empty.hidden = true;
        loadLibrary();
      });
      ui.toast(error.message, 'error');
    }

    function loadCategories() {
      api.get('/document-categories').then(function (rows) {
        state.rows = rows;
        renderCategories();
      }).catch(function (error) { renderError(error, 'Unable to load document categories'); });
    }

    function renderCategories() {
      var query = search.value.trim().toLowerCase();
      var rows = state.rows.filter(function (category) {
        return !query || [category.name, category.description].join(' ').toLowerCase().indexOf(query) !== -1;
      });
      var pages = Math.max(1, Math.ceil(rows.length / pageSize));
      state.page = Math.min(state.page, pages);
      var pageRows = rows.slice((state.page - 1) * pageSize, state.page * pageSize);
      ui.clear(body);
      ui.clear(footer);
      if (!pageRows.length) {
        table.hidden = true;
        empty.hidden = false;
        ui.renderEmpty(empty, {
          title: query ? 'No categories match your search' : 'No categories available',
          text: query ? 'Try another search.' : 'Create a category to organize documents.',
          actionLabel: query ? null : '+ Add Category',
          actionHref: '#'
        });
        var add = empty.querySelector('a');
        if (add) add.addEventListener('click', function (event) {
          event.preventDefault();
          openCategoryForm(null);
        });
        return;
      }
      empty.hidden = true;
      table.hidden = false;
      pageRows.forEach(function (category) {
        var row = el('tr');
        row.appendChild(el('td', { class: 'cms-cell-title', text: category.name }));
        row.appendChild(el('td', { text: category.description || '—' }));
        row.appendChild(el('td', { text: String(category.documentCount || 0) }));
        row.appendChild(el('td', {}, [el('div', { class: 'cms-actions' }, [
          actionLink('View', 'library.html?type=documents&category=' + encodeURIComponent(category.id), 'View documents in ' + category.name),
          el('button', { type: 'button', class: 'cms-action', text: 'Edit', 'data-category-edit': String(category.id) }),
          el('button', {
            type: 'button',
            class: 'cms-action cms-action-danger',
            text: 'Delete',
            'data-category-delete': String(category.id),
            'data-category-name': category.name
          })
        ])]));
        body.appendChild(row);
      });
      ui.renderFooter(footer, {
        page: state.page,
        pageSize: pageSize,
        total: rows.length,
        onPage: function (page) { state.page = page; renderCategories(); }
      });
    }

    function field(label, input) {
      return el('div', { class: 'cms-field' }, [
        el('label', { class: 'cms-label', for: input.id, text: label }),
        input,
        el('p', { class: 'cms-field-error' })
      ]);
    }

    function openCategoryForm(category) {
      var editing = Boolean(category);
      var name = el('input', { id: 'resourceCategoryName', class: 'cms-input', type: 'text', maxlength: '80', required: true });
      var description = el('textarea', { id: 'resourceCategoryDescription', class: 'cms-textarea', rows: '3', maxlength: '255' });
      name.value = category ? category.name : '';
      description.value = category ? category.description || '' : '';
      var save = el('button', { type: 'button', class: 'cms-btn cms-btn-primary', text: editing ? 'Save Changes' : 'Add Category' });
      var modalElement = ui.modalShell(editing ? 'Edit Document Category' : 'Add Document Category', [
        field('Category Name', name),
        field('Description', description)
      ], [
        el('button', { type: 'button', class: 'cms-btn cms-btn-outline', 'data-bs-dismiss': 'modal', text: 'Cancel' }),
        save
      ]);
      var modal = ui.openModal(modalElement);
      save.addEventListener('click', function () {
        ui.clearErrors(modalElement);
        if (!name.value.trim()) {
          ui.setError(name, 'Category name is required.');
          name.focus();
          return;
        }
        save.disabled = true;
        var payload = { name: name.value.trim(), description: description.value.trim() || null };
        var request = editing
          ? api.put('/document-categories/' + encodeURIComponent(category.id), payload)
          : api.post('/document-categories', payload);
        request.then(function () {
          modal.hide();
          ui.toast(editing ? 'Category updated.' : 'Category added.', 'success');
          return loadCategories();
        }).catch(function (error) {
          ui.toast(error.message, 'error');
        }).then(function () { save.disabled = false; });
      });
    }

    document.getElementById('resourceFilters').addEventListener('submit', function (event) {
      event.preventDefault();
    });
    search.addEventListener('input', ui.debounce(function () {
      state.page = 1;
      if (categoriesPage) renderCategories();
    }, 180));
    function handleCategoryAction(event) {
      var edit = event.target.closest('[data-category-edit]');
      if (edit) {
        var item = state.rows.filter(function (category) {
          return String(category.id) === edit.getAttribute('data-category-edit');
        })[0];
        if (item) openCategoryForm(item);
        return;
      }
      var remove = event.target.closest('[data-category-delete]');
      if (!remove) return;
      var id = remove.getAttribute('data-category-delete');
      var name = remove.getAttribute('data-category-name');
      ui.confirmDialog({
        title: 'Delete category',
        message: 'Delete "' + name + '"? Categories with documents cannot be deleted.',
        confirmLabel: 'Delete'
      }).then(function (confirmed) {
        if (!confirmed) return;
        return api.remove('/document-categories/' + encodeURIComponent(id)).then(function () {
          ui.toast('Category deleted.', 'success');
          return loadCategories();
        }).catch(function (error) { ui.toast(error.message, 'error'); });
      });
    }
  }

  function renderError(error, heading) {
    var empty = document.getElementById('resourceEmpty');
    document.getElementById('resourceTableWrap').hidden = true;
    document.getElementById('resourceMediaGrid').hidden = true;
    document.getElementById('resourceFooter').hidden = true;
    empty.hidden = false;
    CMS.ui.renderEmpty(empty, { title: heading, text: error.message });
    CMS.ui.toast(error.message, 'error');
  }

  document.addEventListener('DOMContentLoaded', function () {
    if (document.getElementById('resourceBody')) init();
  });
})();
