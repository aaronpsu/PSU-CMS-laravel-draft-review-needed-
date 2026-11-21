(function () {
  var ui = CMS.ui;
  var form = document.querySelector('.website-information-form');
  if (!form) return;

  var fields = [].slice.call(form.querySelectorAll('[name]'));
  var logoInput = form.querySelector('#psu-logo');
  var logoPreview = form.querySelector('.logo-preview');
  var initialValues = {};
  var savedLogoId = null;
  var submit = form.querySelector('[type="submit"]');

  function updateLogoPreview() {
    if (!logoPreview) return;
    logoPreview.src = savedLogoId
      ? new URL('/api/media/' + encodeURIComponent(savedLogoId), CMS.config.apiBase).href
      : '../assets/images/psu-logo.png';
  }

  function applySettings(settings) {
    fields.forEach(function (field) {
      if (field.type === 'file') return;
      if (Object.prototype.hasOwnProperty.call(settings, field.name) && settings[field.name] !== null) {
        field.value = settings[field.name];
      }
      initialValues[field.name] = field.value;
      if (field.tagName === 'SELECT') ui.syncSelect(field);
    });

    savedLogoId = settings.logo_media_id || null;
    updateLogoPreview();
  }

  function readForm() {
    var settings = {};
    fields.forEach(function (field) {
      if (field.type !== 'file') settings[field.name] = field.value.trim();
    });
    if (savedLogoId) settings.logo_media_id = savedLogoId;
    return settings;
  }

  CMS.api.get('/site-settings').then(function (result) {
    if (!result || !result.data || typeof result.data !== 'object') {
      throw new Error('The server returned invalid site settings.');
    }
    applySettings(result.data);
  }).catch(function (error) {
    ui.toast('Could not load site settings: ' + error.message, 'error');
  });

  if (logoInput && logoPreview) {
    logoInput.addEventListener('change', function () {
      var file = logoInput.files && logoInput.files[0];
      if (!file) return;
      ui.readImage(file).then(function (data) {
        logoPreview.src = data;
      }).catch(function (error) {
        logoInput.value = '';
        ui.toast(error.message, 'error');
      });
    });
  }

  form.addEventListener('reset', function () {
    window.setTimeout(function () {
      fields.forEach(function (field) {
        if (field.type !== 'file') {
          field.value = initialValues[field.name] || '';
          if (field.tagName === 'SELECT') ui.syncSelect(field);
        }
      });
      if (logoInput) logoInput.value = '';
      updateLogoPreview();
    }, 0);
  });

  form.addEventListener('submit', function (event) {
    event.preventDefault();
    ui.clearErrors(form);
    if (!form.reportValidity()) return;
    submit.disabled = true;

    var previousLogoId = savedLogoId;
    var upload = Promise.resolve(null);
    var file = logoInput && logoInput.files && logoInput.files[0];
    if (file) {
      upload = ui.readImage(file).then(function (data) {
        return CMS.api.post('/media', { data: data, altText: 'PSU logo' });
      });
    }

    upload.then(function (media) {
      if (media) savedLogoId = media.id;
      var settings = readForm();
      if (savedLogoId) settings.logo_media_id = savedLogoId;
      return CMS.api.put('/site-settings', { settings: settings });
    }).then(function (result) {
      applySettings(result.data || {});
      if (logoInput) logoInput.value = '';
      ui.toast('Site settings saved.', 'success');
    }).catch(function (error) {
      savedLogoId = previousLogoId;
      updateLogoPreview();
      ui.toast('Could not save site settings: ' + error.message, 'error');
    }).then(function () {
      submit.disabled = false;
    });
  });
})();
