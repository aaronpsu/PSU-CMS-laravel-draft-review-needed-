(function () {
  var ui = CMS.ui, api = CMS.api;
  var sections = {
    banner: 'Banner',
    featured_news: 'Featured News',
    announcements: 'Announcements',
    events: 'Events',
    about: 'About PSU'
  };
  var defaults = {
    header: 'logo-center',
    footer: 'three-columns',
    menu: 'primary',
    order: Object.keys(sections)
  };
  var headerOptions = [
    { value: 'logo-left', label: 'Logo left, menu right' },
    { value: 'logo-center', label: 'Logo centered, menu below' }
  ];
  var footerOptions = [
    { value: 'three-columns', label: 'Three columns' },
    { value: 'two-columns', label: 'Two columns' },
    { value: 'simple', label: 'Single column' }
  ];
  var menuOptions = [
    { value: 'primary', label: 'Primary Menu' },
    { value: 'compact', label: 'Compact Menu (without Academics)' }
  ];

  function init() {
    var list = document.getElementById('sectionList');
    var preview = document.getElementById('layoutPreview');
    var header = document.getElementById('f_header');
    var footer = document.getElementById('f_footer');
    var menu = document.getElementById('f_menu');
    var form = document.getElementById('layoutForm');
    var saveButton = document.getElementById('layoutSave');

    ui.setOptions(header, headerOptions);
    ui.setOptions(footer, footerOptions);
    ui.setOptions(menu, menuOptions);
    saveButton.disabled = true;

    function currentOrder() {
      return [].map.call(list.querySelectorAll('.res-section-item'), function (item) {
        return item.getAttribute('data-key');
      });
    }

    function renderSections(order) {
      ui.clear(list);
      order.forEach(function (key, index) {
        var label = sections[key];
        var item = ui.el('li', { class: 'res-section-item', 'data-key': key }, [
          ui.el('i', { class: 'bi bi-list res-section-handle', 'aria-hidden': 'true' }),
          ui.el('span', { class: 'res-section-label', text: label }),
          ui.el('span', { class: 'res-section-moves' }, [
            ui.el('button', { type: 'button', class: 'res-move', 'data-move': 'up', 'aria-label': 'Move ' + label + ' up' }, [ui.icon('arrow-up')]),
            ui.el('button', { type: 'button', class: 'res-move', 'data-move': 'down', 'aria-label': 'Move ' + label + ' down' }, [ui.icon('arrow-down')])
          ])
        ]);
        if (index === 0) item.querySelector('[data-move="up"]').disabled = true;
        if (index === order.length - 1) item.querySelector('[data-move="down"]').disabled = true;
        list.appendChild(item);
      });
    }

    function syncMoves() {
      var items = [].slice.call(list.querySelectorAll('.res-section-item'));
      items.forEach(function (item, index) {
        item.querySelector('[data-move="up"]').disabled = index === 0;
        item.querySelector('[data-move="down"]').disabled = index === items.length - 1;
      });
    }

    function renderPreview() {
      var headerValue = header.value || defaults.header;
      var footerValue = footer.value || defaults.footer;
      var menuValue = menu.value || defaults.menu;
      preview.setAttribute('data-header', headerValue);
      preview.setAttribute('data-footer', footerValue);
      preview.setAttribute('data-menu', menuValue);
      ui.clear(preview);
      preview.appendChild(ui.el('div', { class: 'res-pv-block res-pv-header', text: headerValue === 'logo-center' ? 'PSU logo · Centered header' : 'PSU logo · Left header' }));
      preview.appendChild(ui.el('div', { class: 'res-pv-block', text: menuValue === 'compact' ? 'Compact navigation' : 'Primary navigation' }));
      var sectionContainer = ui.el('div', { class: 'res-pv-sections' });
      currentOrder().forEach(function (key) {
        sectionContainer.appendChild(ui.el('div', { class: 'res-pv-block', text: sections[key] }));
      });
      preview.appendChild(sectionContainer);
      preview.appendChild(ui.el('div', { class: 'res-pv-block res-pv-footer', text: footerOptions.filter(function (option) { return option.value === footerValue; })[0].label + ' footer' }));
    }

    function normalizeOrder(value) {
      var order = [];
      if (Array.isArray(value)) {
        value.forEach(function (key) {
          if (Object.prototype.hasOwnProperty.call(sections, key) && order.indexOf(key) === -1) order.push(key);
        });
      }
      Object.keys(sections).forEach(function (key) {
        if (order.indexOf(key) === -1) order.push(key);
      });
      return order;
    }

    function applySettings(settings) {
      settings = settings || {};
      header.value = headerOptions.some(function (option) { return option.value === settings.header_layout; })
        ? settings.header_layout : defaults.header;
      footer.value = footerOptions.some(function (option) { return option.value === settings.footer_layout; })
        ? settings.footer_layout : defaults.footer;
      menu.value = menuOptions.some(function (option) { return option.value === settings.menu_layout; })
        ? settings.menu_layout : defaults.menu;
      ui.syncSelect(header);
      ui.syncSelect(footer);
      ui.syncSelect(menu);
      renderSections(normalizeOrder(settings.homepage_section_order));
      renderPreview();
    }

    list.addEventListener('click', function (event) {
      var button = event.target.closest('[data-move]');
      if (!button || button.disabled) return;
      var item = button.closest('.res-section-item');
      var direction = button.getAttribute('data-move');
      if (direction === 'up' && item.previousElementSibling) list.insertBefore(item, item.previousElementSibling);
      if (direction === 'down' && item.nextElementSibling) list.insertBefore(item.nextElementSibling, item);
      syncMoves();
      renderPreview();
      item.classList.add('is-moved');
      window.setTimeout(function () { item.classList.remove('is-moved'); }, 450);
      var focusTarget = item.querySelector('[data-move="' + direction + '"]');
      if (focusTarget.disabled) focusTarget = item.querySelector('.res-move:not(:disabled)');
      if (focusTarget) focusTarget.focus();
    });

    [header, footer, menu].forEach(function (select) {
      select.addEventListener('change', renderPreview);
    });

    api.get('/site-settings').then(function (result) {
      if (!result || !result.data || typeof result.data !== 'object') {
        throw new Error('The server returned invalid site settings.');
      }
      applySettings(result.data);
      saveButton.disabled = false;
    }).catch(function (error) {
      ui.toast('Could not load website layout: ' + error.message, 'error');
    });

    form.addEventListener('submit', function (event) {
      event.preventDefault();
      saveButton.disabled = true;
      api.put('/site-settings', {
        settings: {
          header_layout: header.value,
          footer_layout: footer.value,
          menu_layout: menu.value,
          homepage_section_order: currentOrder()
        }
      }).then(function (result) {
        applySettings(result.data);
        ui.toast('Website layout saved.', 'success');
      }).catch(function (error) {
        ui.toast('Could not save website layout: ' + error.message, 'error');
      }).then(function () {
        saveButton.disabled = false;
      });
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    if (document.getElementById('layoutForm')) init();
  });
})();
