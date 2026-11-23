/* Data access and display helpers for Contact & Feedback. */
window.CMS = window.CMS || {};

(function () {
  var cfg = CMS.config;
  var rows = [];

  cfg.pageSize.messages = 4;
  cfg.pageSize.unread = 2;
  cfg.pageSize.feedback = 3;
  cfg.messageStatuses = [
    { value: 'unread', label: 'Unread' },
    { value: 'read', label: 'Read' },
    { value: 'responded', label: 'Responded' }
  ];
  var STATUS_COLOR = { unread: 'amber', read: 'green', responded: 'blue' };
  function str(value) { return value === null || value === undefined ? '' : String(value); }

  function normalize(message) {
    var rating = parseInt(message.rating, 10);
    var reply = message.reply && typeof message.reply === 'object' ? message.reply : null;
    return {
      id: str(message.id),
      fullName: str(message.fullName),
      email: str(message.email),
      subject: str(message.subject),
      campusDepartment: str(message.campusDepartment),
      message: str(message.message),
      status: ['unread', 'read', 'responded'].indexOf(message.status) !== -1 ? message.status : 'unread',
      type: message.type === 'feedback' ? 'feedback' : 'inquiry',
      rating: rating >= 1 && rating <= 5 ? rating : null,
      category: str(message.category),
      submittedAt: str(message.submittedAt),
      reply: reply ? { subject: str(reply.subject), body: str(reply.body), sentAt: str(reply.sentAt) } : null
    };
  }

  function updateCache(message) {
    var updated = normalize(message);
    var index = rows.findIndex(function (row) { return row.id === updated.id; });
    if (index === -1) rows.push(updated);
    else rows[index] = updated;
    rows.sort(function (a, b) { return b.submittedAt.localeCompare(a.submittedAt); });
    return updated;
  }

  function all() { return rows.slice(); }
  function load() {
    return CMS.api.get('/messages').then(function (result) {
      if (!result || !Array.isArray(result.data)) throw new Error('The server returned an invalid messages response.');
      rows = result.data.map(normalize).sort(function (a, b) { return b.submittedAt.localeCompare(a.submittedAt); });
      return all();
    });
  }
  function get(id) {
    return CMS.api.get('/messages/' + encodeURIComponent(id)).then(function (message) {
      return updateCache(message);
    });
  }
  function counts() {
    var count = { total: 0, unread: 0, read: 0, responded: 0 };
    rows.forEach(function (message) { count.total++; count[message.status]++; });
    return count;
  }
  function setStatus(id, status) {
    return CMS.api.put('/messages/' + encodeURIComponent(id), { status: status }).then(function (message) {
      return updateCache(message);
    });
  }
  function saveReply(id, subject, body) {
    return CMS.api.put('/messages/' + encodeURIComponent(id), { reply: { subject: subject, body: body } }).then(function (message) {
      return updateCache(message);
    });
  }
  function remove(id) {
    return CMS.api.remove('/messages/' + encodeURIComponent(id)).then(function () {
      rows = rows.filter(function (message) { return message.id !== id; });
      return true;
    });
  }
  function statusLabel(value) {
    for (var i = 0; i < cfg.messageStatuses.length; i++) {
      if (cfg.messageStatuses[i].value === value) return cfg.messageStatuses[i].label;
    }
    return value;
  }
  function feedbackLabel(status) { return status === 'unread' ? 'New' : 'Reviewed'; }
  function statusBadge(message, asFeedback) {
    var label = asFeedback ? feedbackLabel(message.status) : statusLabel(message.status);
    var color = asFeedback ? (message.status === 'unread' ? 'amber' : 'blue') : STATUS_COLOR[message.status];
    return CMS.ui.el('span', { class: 'cms-status cms-status-' + color, text: label });
  }
  function formatDateTime(value) {
    var date = new Date(String(value || '').replace(' ', 'T'));
    return isNaN(date.getTime()) ? str(value) : date.toLocaleString();
  }
  function stars(rating) {
    var result = '';
    for (var i = 1; i <= 5; i++) result += i <= rating ? '\u2605' : '\u2606';
    return result;
  }
  function initials(name) {
    var parts = str(name).trim().split(/\s+/).filter(Boolean);
    if (!parts.length) return '?';
    return (parts[0].charAt(0) + (parts.length > 1 ? parts[parts.length - 1].charAt(0) : '')).toUpperCase();
  }

  function confirmDelete(options) {
    options = options || {};
    var ui = CMS.ui, el = ui.el;
    return new Promise(function (resolve) {
      var answered = false;
      var titleId = 'deleteModalTitle', textId = 'deleteModalText';
      var cancel = el('button', { type: 'button', class: 'cms-btn cms-btn-outline', 'data-bs-dismiss': 'modal', text: options.cancelLabel || 'Cancel' });
      var confirm = el('button', { type: 'button', class: 'cms-btn cms-btn-danger', text: options.confirmLabel || 'Delete' });
      var modalEl = el('div', { class: 'modal fade cms-modal delete-modal', tabindex: '-1', 'aria-labelledby': titleId, 'aria-describedby': textId, 'aria-hidden': 'true' }, [
        el('div', { class: 'modal-dialog modal-dialog-centered' }, [
          el('div', { class: 'modal-content' }, [
            el('div', { class: 'delete-modal-body' }, [
              el('h2', { class: 'delete-modal-title', id: titleId, text: options.title || 'Delete this item?' }),
              el('p', { class: 'delete-modal-text', id: textId, text: options.message || 'This action cannot be undone.' }),
              el('div', { class: 'delete-modal-actions' }, [cancel, confirm])
            ])
          ])
        ])
      ]);
      var modal = ui.openModal(modalEl);
      modalEl.addEventListener('shown.bs.modal', function () { cancel.focus(); });
      confirm.addEventListener('click', function () { answered = true; modal.hide(); resolve(true); });
      modalEl.addEventListener('hidden.bs.modal', function () { if (!answered) resolve(false); });
    });
  }

  function exportCsv(records, baseName) {
    var headers = ['Name', 'Email', 'Subject', 'Campus/Department', 'Message', 'Status', 'Type', 'Rating', 'Category', 'Submitted'];
    var data = records.map(function (message) {
      return [
        message.fullName, message.email, message.subject, message.campusDepartment, message.message,
        statusLabel(message.status), message.type, message.rating === null ? '' : message.rating,
        message.category, message.submittedAt.replace('T', ' ')
      ];
    });
    CMS.csv.download(baseName + '-' + CMS.ui.todayISO() + '.csv', headers, data);
  }

  CMS.messages = {
    all: all, load: load, get: get, counts: counts, setStatus: setStatus, saveReply: saveReply, remove: remove,
    statusLabel: statusLabel, feedbackLabel: feedbackLabel, statusBadge: statusBadge,
    formatDateTime: formatDateTime, stars: stars, initials: initials, exportCsv: exportCsv, confirmDelete: confirmDelete
  };
})();
