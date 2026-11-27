(function () {
  var ui = CMS.ui, api = CMS.api;
  var form = document.getElementById('personnelForm');
  if (!form) return;

  var id = new URLSearchParams(window.location.search).get('id');
  var record = null;
  var lists = { campuses: [], colleges: [], departments: [] };
  var selectedPhotoData = '';
  var photoMediaId = null;
  var photoUrl = '';
  var campus = document.getElementById('personCampus');
  var college = document.getElementById('personCollege');
  var department = document.getElementById('personDepartment');
  var photoInput = document.getElementById('personPhoto');
  var preview = document.getElementById('photoPreview');
  var saveButton = document.getElementById('personSave');
  var photoError = document.getElementById('photoError');

  function setPhotoError(message) {
    photoError.textContent = message || '';
    photoError.classList.toggle('is-visible', Boolean(message));
  }

  function renderPhoto() {
    ui.clear(preview);
    if (selectedPhotoData || photoUrl) {
      preview.appendChild(ui.el('img', {
        src: selectedPhotoData || photoUrl,
        alt: 'Personnel profile preview'
      }));
      return;
    }
    preview.appendChild(ui.icon('person'));
  }

  function renderColleges(selectedValue) {
    var campusId = campus.value;
    var filtered = lists.colleges.filter(function (item) {
      return !campusId || String(item.campusId) === campusId;
    });
    ui.setOptions(college, [{ value: '', label: 'Select college' }].concat(filtered.map(function (item) {
      return { value: String(item.id), label: item.name };
    })), selectedValue || '');
    renderDepartments(department.value);
  }

  function renderDepartments(selectedValue) {
    var campusId = campus.value;
    var collegeId = college.value;
    var filtered = lists.departments.filter(function (item) {
      return (!campusId || !item.campusId || String(item.campusId) === campusId)
        && (!collegeId || String(item.collegeId) === collegeId);
    });
    ui.setOptions(department, [{ value: '', label: 'Select department' }].concat(filtered.map(function (item) {
      return { value: String(item.id), label: item.name };
    })), selectedValue || '');
  }

  function populateForm() {
    if (!record) return;
    document.getElementById('personnelHeading').textContent = 'Edit Personnel';
    document.getElementById('personnelCrumb').textContent = 'Edit Personnel';
    document.title = 'Edit Personnel | PSU CMS';
    document.getElementById('personFirstName').value = record.firstName || '';
    document.getElementById('personMiddleName').value = record.middleName || '';
    document.getElementById('personLastName').value = record.lastName || '';
    document.getElementById('personPosition').value = record.position || '';
    document.getElementById('personEmail').value = record.email || '';
    document.getElementById('personPhone').value = record.contactNumber || '';
    document.getElementById('personEducation').value = record.education || '';
    document.getElementById('personBiography').value = record.biography || '';
    document.getElementById('personStatus').value = record.status || 'active';
    photoMediaId = record.photoMediaId || null;
    photoUrl = record.photoUrl || '';
    campus.value = record.campusId ? String(record.campusId) : '';
    ui.syncSelect(campus);
    renderColleges(record.collegeId ? String(record.collegeId) : '');
    renderDepartments(record.departmentId ? String(record.departmentId) : '');
    renderPhoto();
  }

  function readFile(file) {
    return new Promise(function (resolve, reject) {
      var reader = new FileReader();
      reader.onload = function () {
        if (typeof reader.result === 'string') resolve(reader.result);
        else reject(new Error('The image could not be read.'));
      };
      reader.onerror = function () { reject(new Error('The image could not be read.')); };
      reader.readAsDataURL(file);
    });
  }

  function load() {
    var requests = [api.get('/options')];
    if (id) requests.push(api.get('/faculty/' + encodeURIComponent(id)));
    return Promise.all(requests).then(function (results) {
      lists.campuses = results[0].campuses || [];
      lists.colleges = results[0].colleges || [];
      lists.departments = results[0].departments || [];
      record = id ? results[1] : null;
      ui.setOptions(campus, [{ value: '', label: 'Select campus' }].concat(lists.campuses.map(function (item) {
        return { value: String(item.id), label: item.name };
      })), record ? String(record.campusId || '') : '');
      ui.setOptions(college, [{ value: '', label: 'Select college' }]);
      ui.setOptions(department, [{ value: '', label: 'Select department' }]);
      if (record) populateForm();
    }).catch(function (error) {
      saveButton.disabled = true;
      ui.toast(error.message, 'error');
    });
  }

  campus.addEventListener('change', function () {
    renderColleges('');
  });
  college.addEventListener('change', function () {
    renderDepartments('');
  });
  photoInput.addEventListener('change', function () {
    var file = photoInput.files && photoInput.files[0];
    if (!file) return;
    if (!/^image\/(jpeg|png|gif|webp)$/i.test(file.type)) {
      photoInput.value = '';
      setPhotoError('Choose a JPEG, PNG, GIF or WEBP image.');
      return;
    }
    if (file.size > 5 * 1024 * 1024) {
      photoInput.value = '';
      setPhotoError('The image is larger than 5 MB.');
      return;
    }
    setPhotoError('');
    readFile(file).then(function (data) {
      selectedPhotoData = data;
      photoUrl = '';
      renderPhoto();
    }).catch(function (error) {
      photoInput.value = '';
      setPhotoError(error.message);
    });
  });
  document.getElementById('removePhoto').addEventListener('click', function () {
    selectedPhotoData = '';
    photoUrl = '';
    photoMediaId = null;
    photoInput.value = '';
    setPhotoError('');
    renderPhoto();
  });

  form.addEventListener('submit', function (event) {
    event.preventDefault();
    ui.clearErrors(form);
    setPhotoError('');
    var firstName = document.getElementById('personFirstName');
    var lastName = document.getElementById('personLastName');
    var position = document.getElementById('personPosition');
    var required = [firstName, lastName, position, campus];
    var invalid = false;
    required.forEach(function (input) {
      if (!input.value.trim()) {
        ui.setError(input, 'This field is required.');
        invalid = true;
      }
    });
    if (invalid) {
      var firstInvalid = form.querySelector('.has-error input, .has-error select');
      if (firstInvalid) firstInvalid.focus();
      return;
    }

    var payload = {
      firstName: firstName.value.trim(),
      middleName: document.getElementById('personMiddleName').value.trim() || null,
      lastName: lastName.value.trim(),
      position: position.value.trim(),
      campusId: campus.value,
      departmentId: department.value || null,
      email: document.getElementById('personEmail').value.trim() || null,
      contactNumber: document.getElementById('personPhone').value.trim() || null,
      education: document.getElementById('personEducation').value.trim() || null,
      biography: document.getElementById('personBiography').value.trim() || null,
      status: document.getElementById('personStatus').value
    };
    if (selectedPhotoData) payload.photoData = selectedPhotoData;
    else payload.photoMediaId = photoMediaId;

    saveButton.disabled = true;
    var request = id
      ? api.put('/faculty/' + encodeURIComponent(id), payload)
      : api.post('/faculty', payload);
    request.then(function () {
      ui.flash(id ? 'Personnel record updated.' : 'Personnel record added.', 'success');
      window.location.href = 'index.html';
    }).catch(function (error) {
      saveButton.disabled = false;
      ui.toast(error.message, 'error');
    });
  });

  load();
})();
