(function () {
  var ui = CMS.ui, el = ui.el, api = CMS.api;

  function init() {
    var type = new URLSearchParams(window.location.search).get('type') === 'media' ? 'media' : 'documents';
    var media = type === 'media';
    var endpoint = media ? '/media' : '/documents';
    var title = media ? 'Media Library' : 'Document Library';
    var state = { q: '', rows: [], page: 1, request: 0 };
    var body = document.getElementById('resourceBody');
    var wrap = document.getElementById('resourceTableWrap');
    var empty = document.getElementById('resourceEmpty');
    var footer = document.getElementById('resourceFooter');
    var search = document.getElementById('resourceSearch');
    var pageSize = 10;

    document.title = title + ' | PSU CMS';
    document.getElementById('resourceTitle').textContent = title;
    document.getElementById('resourceSubtitle').textContent = media
      ? 'Upload and manage images stored in the CMS media table.'
      : 'Upload and manage files stored in the CMS documents table.';
    document.getElementById('resourceCrumb').textContent = title;
    ui.clear(document.getElementById('resourceHead'));
    (media ? ['Preview', 'File name', 'Alt text', 'File size', 'Status', 'Actions']
      : ['Title', 'File name', 'Type', 'File size', 'Status', 'Actions']).forEach(function (label) {
      document.getElementById('resourceHead').appendChild(el('th', { scope: 'col', text: label }));
    });

    function render() {
      var pages = Math.max(1, Math.ceil(state.rows.length / pageSize));
      state.page = Math.min(state.page, pages);
      ui.clear(body);
      if (!state.rows.length) {
        wrap.hidden = true;
        footer.hidden = true;
        empty.hidden = false;
        ui.renderEmpty(empty, {
          title: state.q ? 'No matching files found' : 'No files yet',
          text: state.q ? 'Try another search.' : 'Files you upload will appear here.',
          actionLabel: state.q ? null : '+ Upload',
          actionHref: '#'
        });
        var emptyUpload = empty.querySelector('a');
        if (emptyUpload) emptyUpload.addEventListener('click', function (event) {
          event.preventDefault();
          openUpload();
        });
        return;
      }

      wrap.hidden = false;
      empty.hidden = true;
      footer.hidden = false;
      state.rows.slice((state.page - 1) * pageSize, state.page * pageSize).forEach(function (file) {
        var row = el('tr');
        if (media) {
          row.appendChild(el('td', {}, [file.url
            ? el('img', { class: 'cms-thumb', src: file.url, alt: file.altText || '' })
            : el('span', { class: 'cms-thumb', 'aria-hidden': 'true' })]));
          row.appendChild(el('td', { text: file.fileName }));
          row.appendChild(el('td', { text: file.altText || '\u2014' }));
        } else {
          row.appendChild(el('td', { text: file.title || '\u2014' }));
          row.appendChild(el('td', { text: file.fileName }));
          row.appendChild(el('td', { text: file.mimeType || '\u2014' }));
        }
        row.appendChild(el('td', { text: ui.formatSize(file.fileSize) || '\u2014' }));
        row.appendChild(el('td', { text: file.available ? 'Available' : 'File missing' }));
        row.appendChild(actions(file));
        body.appendChild(row);
      });
      ui.renderFooter(footer, {
        page: state.page, pageSize: pageSize, total: state.rows.length,
        onPage: function (page) { state.page = page; render(); }
      });
    }

    function actions(file) {
      var view = el('button', { type: 'button', class: 'cms-action', text: 'View', 'aria-label': 'View ' + file.fileName });
      var edit = el('button', { type: 'button', class: 'cms-action', text: 'Edit', 'aria-label': 'Edit ' + file.fileName });
      var remove = el('button', { type: 'button', class: 'cms-action cms-action-danger', text: 'Delete', 'aria-label': 'Delete ' + file.fileName });
      view.disabled = !file.available;
      view.addEventListener('click', function () { showFile(file); });
      edit.addEventListener('click', function () { openEdit(file); });
      remove.addEventListener('click', function () { deleteFile(file); });
      return el('td', {}, [el('div', { class: 'cms-actions' }, [view, edit, remove])]);
    }

    function load() {
      var request = ++state.request;
      return api.query(endpoint, { q: search.value.trim() }).then(function (rows) {
        if (request !== state.request) return;
        state.rows = rows;
        state.q = search.value.trim();
        state.page = 1;
        render();
      }).catch(function (error) {
        if (request !== state.request) return;
        ui.clear(body);
        wrap.hidden = true;
        footer.hidden = true;
        empty.hidden = false;
        ui.renderEmpty(empty, { title: 'Unable to load ' + title.toLowerCase(), text: error.message });
        ui.toast(error.message, 'error');
      });
    }

    function showFile(file) {
      var link = el('a', { href: file.url, target: '_blank', rel: 'noopener', text: media ? 'Open image' : 'Download file' });
      ui.showDetails({
        title: file.title || file.fileName,
        rows: [
          ['File name', file.fileName], ['Type', file.mimeType], ['File size', ui.formatSize(file.fileSize)],
          [media ? 'Alt text' : 'Title', media ? file.altText : file.title],
          ['Uploaded', file.createdAt], ['File', link]
        ]
      });
    }

    function openUpload() {
      var fileInput = el('input', { class: 'cms-input', type: 'file', accept: media ? 'image/png,image/jpeg,image/gif,image/webp' : '.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.png,.jpg,.jpeg' });
      var titleInput = media ? null : el('input', { class: 'cms-input', type: 'text', maxlength: '255', placeholder: 'Optional document title' });
      var altInput = media ? el('input', { class: 'cms-input', type: 'text', maxlength: '255', placeholder: 'Describe the image' }) : null;
      var hint = el('p', { class: 'cms-field-hint', text: media ? 'Images up to 5 MB are supported.' : 'Documents up to 300 KB are supported.' });
      var content = [field('File', fileInput)];
      if (titleInput) content.push(field('Title', titleInput));
      if (altInput) content.push(field('Alt text', altInput));
      content.push(hint);
      var save = el('button', { type: 'button', class: 'cms-btn cms-btn-primary', text: 'Upload' });
      var modalEl = ui.modalShell(media ? 'Upload Image' : 'Upload Document', content, [
        el('button', { type: 'button', class: 'cms-btn cms-btn-outline', 'data-bs-dismiss': 'modal', text: 'Cancel' }),
        save
      ]);
      var modal = ui.openModal(modalEl);
      save.addEventListener('click', function () {
        var file = fileInput.files && fileInput.files[0];
        if (!file) {
          ui.toast('Choose a file to upload.', 'error');
          return;
        }
        var maxSize = media ? 5 * 1024 * 1024 : 300 * 1024;
        if (file.size > maxSize) {
          ui.toast(media ? 'Images over 5 MB are not supported.' : 'Documents over 300 KB are not supported.', 'error');
          return;
        }
        save.disabled = true;
        readDataUrl(file).then(function (data) {
          var payload = media
            ? { data: data, altText: altInput.value.trim() }
            : { data: data, fileName: file.name, title: titleInput.value.trim() };
          return api.post(endpoint, payload);
        }).then(function () {
          modal.hide();
          ui.toast('File uploaded.', 'success');
          return load();
        }).catch(function (error) {
          ui.toast(error.message, 'error');
        }).finally(function () { save.disabled = false; });
      });
    }

    function openEdit(file) {
      var input = el('input', { class: 'cms-input', type: 'text', maxlength: '255' });
      input.value = media ? file.altText || '' : file.title || '';
      var modalEl = ui.modalShell(media ? 'Edit Alt Text' : 'Edit Document Title', [
        field(media ? 'Alt text' : 'Title', input)
      ], [
        el('button', { type: 'button', class: 'cms-btn cms-btn-outline', 'data-bs-dismiss': 'modal', text: 'Cancel' }),
        el('button', { type: 'button', class: 'cms-btn cms-btn-primary', text: 'Save Changes' })
      ]);
      var modal = ui.openModal(modalEl);
      modalEl.querySelector('.modal-footer .cms-btn-primary').addEventListener('click', function () {
        var payload = media ? { altText: input.value.trim() } : { title: input.value.trim() };
        api.put(endpoint + '/' + encodeURIComponent(file.id), payload).then(function () {
          modal.hide();
          ui.toast('File details updated.', 'success');
          return load();
        }).catch(function (error) { ui.toast(error.message, 'error'); });
      });
    }

    function deleteFile(file) {
      ui.confirmDialog({
        title: 'Delete file',
        message: 'Delete \u201C' + file.fileName + '\u201D? Files used by content cannot be deleted.',
        confirmLabel: 'Delete'
      }).then(function (confirmed) {
        if (!confirmed) return;
        api.remove(endpoint + '/' + encodeURIComponent(file.id)).then(function () {
          ui.toast('File deleted.', 'success');
          return load();
        }).catch(function (error) { ui.toast(error.message, 'error'); });
      });
    }

    document.getElementById('resourceUpload').addEventListener('click', openUpload);
    search.addEventListener('input', ui.debounce(load, 180));
    load();
  }

  function field(label, input) {
    return el('div', { class: 'cms-field' }, [
      el('label', { class: 'cms-label', text: label }), input
    ]);
  }

  function readDataUrl(file) {
    return new Promise(function (resolve, reject) {
      var reader = new FileReader();
      reader.onload = function () {
        if (typeof reader.result === 'string') resolve(reader.result);
        else reject(new Error('The selected file could not be read.'));
      };
      reader.onerror = function () { reject(new Error('The selected file could not be read.')); };
      reader.readAsDataURL(file);
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    if (document.getElementById('resourceBody')) init();
  });
})();
