(function () {
  var ui = CMS.ui, api = CMS.api;
  var DOCUMENT_TYPES = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'png', 'jpg', 'jpeg'];
  var body = document.body;

  function readFile(file) {
    return new Promise(function (resolve, reject) {
      var reader = new FileReader();
      reader.onerror = function () { reject(new Error('The selected file could not be read.')); };
      reader.onload = function () { resolve(reader.result); };
      reader.readAsDataURL(file);
    });
  }

  function init() {
    var media = body.getAttribute('data-page') === 'upload-media';
    var resourceId = ui.getParam('id');
    var title = media ? 'Media' : 'Document';
    var form = document.getElementById('resourceUploadForm');
    var fileInput = document.getElementById('resourceFile');
    var dropzone = document.getElementById('resourceDropzone');
    var dropzoneText = document.getElementById('dropzoneText');
    var fileField = document.getElementById(media ? 'mediaFileField' : 'documentFileField');
    var fileError = document.getElementById('resourceFileError');
    var fileName = document.getElementById('resourceFileName');
    var fileType = document.getElementById('resourceFileType');
    var preview = document.getElementById('resourcePreview');
    var previewImage = document.getElementById('resourcePreviewImage');
    var titleInput = document.getElementById('resourceTitleInput');
    var category = document.getElementById('resourceCategory');
    var description = document.getElementById('resourceDescription');
    var altText = document.getElementById('resourceAltText');
    var caption = document.getElementById('resourceCaption');
    var saveButton = document.getElementById('resourceSave');
    var selectedFile = null;
    var selectedImageData = '';
    var fileReadRequest = 0;
    var fileReadPending = false;

    document.title = (resourceId ? 'Edit ' : 'Upload ') + title + ' | PSU CMS';
    if (resourceId) {
      document.getElementById('uploadHeading').textContent = 'Edit ' + title;
      document.getElementById('uploadSub').textContent = 'Update metadata for this stored ' + title.toLowerCase() + '. File replacement is not supported by the current API.';
      document.getElementById('uploadCrumb').textContent = 'Edit ' + title;
      saveButton.textContent = 'Save Changes';
      fileField.hidden = true;
      if (preview) preview.hidden = false;
      saveButton.disabled = true;
    } else if (media) {
      document.getElementById('uploadHeading').textContent = 'Upload Media';
    }

    if (category) {
      ui.setOptions(category, [{ value: '', label: 'Uncategorized' }]);
    }

    if (fileInput) {
      fileInput.addEventListener('change', function () {
        if (fileInput.files && fileInput.files[0]) selectFile(fileInput.files[0]);
      });
      ['dragenter', 'dragover'].forEach(function (name) {
        dropzone.addEventListener(name, function (event) {
          event.preventDefault();
          dropzone.classList.add('is-over');
        });
      });
      ['dragleave', 'drop'].forEach(function (name) {
        dropzone.addEventListener(name, function (event) {
          event.preventDefault();
          dropzone.classList.remove('is-over');
        });
      });
      dropzone.addEventListener('drop', function (event) {
        var files = event.dataTransfer && event.dataTransfer.files;
        if (files && files[0]) selectFile(files[0]);
      });
    }

    function selectFile(file) {
      ui.clearErrors(form);
      fileError.textContent = '';
      var extension = /\.([a-z0-9]+)$/i.exec(file.name);
      extension = extension ? extension[1].toLowerCase() : '';

      if (media) {
        var request = ++fileReadRequest;
        selectedFile = null;
        selectedImageData = '';
        fileReadPending = true;
        saveButton.disabled = true;
        preview.hidden = true;
        ui.readImage(file).then(function (data) {
          if (request !== fileReadRequest) return;
          selectedFile = file;
          selectedImageData = data;
          fileReadPending = false;
          previewImage.src = data;
          previewImage.alt = titleInput.value.trim() || file.name;
          previewImage.hidden = false;
          preview.querySelector('.res-media-empty').hidden = true;
          preview.hidden = false;
          dropzoneText.textContent = file.name;
          saveButton.disabled = false;
        }).catch(function (error) {
          if (request !== fileReadRequest) return;
          selectedFile = null;
          selectedImageData = '';
          fileReadPending = false;
          fileInput.value = '';
          dropzoneText.textContent = 'Drag and drop an image here';
          fileError.textContent = error.message;
          ui.setError(fileInput, error.message);
          saveButton.disabled = false;
        });
        return;
      }

      if (DOCUMENT_TYPES.indexOf(extension) === -1) {
        fileReadRequest++;
        setFileFailure('Choose a PDF, Office document, TXT, PNG or JPEG file. ZIP files are not supported.');
        return;
      }
      if (file.size > CMS.config.documentMaxFileBytes) {
        fileReadRequest++;
        setFileFailure('The selected file is larger than ' + ui.formatSize(CMS.config.documentMaxFileBytes) + '.');
        return;
      }
      fileReadRequest++;
      fileReadPending = false;
      selectedFile = file;
      dropzoneText.textContent = file.name;
      fileName.value = file.name;
      fileType.value = extension.toUpperCase();
    }

    function setFileFailure(message) {
      selectedFile = null;
      selectedImageData = '';
      fileReadPending = false;
      if (preview) preview.hidden = true;
      fileInput.value = '';
      fileError.textContent = message;
      ui.setError(fileInput, message);
    }

    function loadCategories() {
      if (!category) return Promise.resolve();
      return api.get('/document-categories').then(function (rows) {
        var options = [{ value: '', label: 'Uncategorized' }].concat(rows.map(function (row) {
          return { value: String(row.id), label: row.name };
        }));
        ui.setOptions(category, options, category.value);
      });
    }

    function loadRecord() {
      if (!resourceId) return Promise.resolve(null);
      return api.get((media ? '/media/' : '/documents/') + encodeURIComponent(resourceId)).then(function (record) {
        titleInput.value = record.title || record.fileName || '';
        description.value = record.description || '';
        if (media) {
          altText.value = record.altText || '';
          caption.value = record.caption || '';
          if (record.url && record.available) {
            previewImage.src = record.url;
            previewImage.alt = record.altText || '';
            previewImage.hidden = false;
            preview.querySelector('.res-media-empty').hidden = true;
          } else {
            previewImage.hidden = true;
            preview.querySelector('.res-media-empty').hidden = false;
          }
        } else {
          fileName.value = record.fileName || '';
          fileType.value = fileExtension(record.fileName).toUpperCase();
        }
        if (category && record.categoryId) {
          category.value = String(record.categoryId);
          ui.syncSelect(category);
        }
        return record;
      });
    }

    function fileExtension(name) {
      var match = /\.([a-z0-9]+)$/i.exec(name || '');
      return match ? match[1].toLowerCase() : '';
    }

    loadCategories().then(loadRecord).then(function () {
      saveButton.disabled = false;
    }).catch(function (error) {
      ui.toast(error.message, 'error');
      if (resourceId) saveButton.disabled = true;
    });

    form.addEventListener('submit', function (event) {
      event.preventDefault();
      ui.clearErrors(form);
      fileError.textContent = '';
      if (!titleInput.value.trim()) {
        ui.setError(titleInput, title + ' title is required.');
        titleInput.focus();
        return;
      }
      if (!resourceId && !selectedFile) {
        var requiredMessage = fileReadPending ? 'Wait for the selected image to finish processing.' : 'Select a file to upload.';
        fileError.textContent = requiredMessage;
        ui.setError(fileInput, requiredMessage);
        fileInput.focus();
        return;
      }

      saveButton.disabled = true;
      var request;
      var metadata = media ? {
        title: titleInput.value.trim(),
        altText: altText.value.trim() || null,
        caption: caption.value.trim() || null,
        description: description.value.trim() || null
      } : {
        title: titleInput.value.trim(),
        categoryId: category.value ? Number(category.value) : null,
        description: description.value.trim() || null
      };

      if (resourceId) {
        request = api.put((media ? '/media/' : '/documents/') + encodeURIComponent(resourceId), metadata);
      } else if (media) {
        metadata.data = selectedImageData;
        request = api.post('/media', metadata);
      } else {
        request = readFile(selectedFile).then(function (data) {
          metadata.fileName = selectedFile.name;
          metadata.data = data;
          return api.post('/documents', metadata);
        });
      }

      request.then(function () {
        ui.flash(title + (resourceId ? ' updated.' : ' uploaded.'), 'success');
        window.location.href = media ? 'library.html?type=media' : 'library.html?type=documents';
      }).catch(function (error) {
        saveButton.disabled = false;
        ui.toast(error.message, 'error');
      });
    });

    titleInput.addEventListener('input', function () {
      if (previewImage && selectedImageData) previewImage.alt = titleInput.value.trim();
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    if (body && body.getAttribute('data-page') && body.getAttribute('data-page').indexOf('upload-') === 0) init();
  });
})();
