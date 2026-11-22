/* ==========================================================================
   homepage.js — Homepage Management screens, connected to the Laravel API
   (/api/homepage/*). One file, one page per <body data-hp="...">:
     overview | banners | banner-form | editor | featured
   Needs: config.js, sanitize.js, ui.js, layout.js, api-client.js
   ========================================================================== */
(function () {
  var ui = CMS.ui, el = ui.el, api = CMS.api;
  var MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  var TYPES = [
    { key: 'news', title: 'Featured News', singular: 'news article', icon: 'journal-text' },
    { key: 'announcements', title: 'Featured Announcements', singular: 'announcement', icon: 'megaphone' },
    { key: 'events', title: 'Featured Events', singular: 'event', icon: 'calendar-event' }
  ];

  /* ---------- small helpers ---------- */
  function $(id) { return document.getElementById(id); }

  function formatDate(value) {
    var m = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(value || ''));
    return m ? MONTHS[parseInt(m[2], 10) - 1] + ' ' + parseInt(m[3], 10) + ', ' + m[1] : '';
  }

  function plain(text, max) {
    var value = String(text || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
    return max && value.length > max ? value.slice(0, max - 1) + '…' : value;
  }

  function errorMessage(error) {
    if (error && error.status === 401) return 'Your session has ended. Sign in from the account menu (top right), then try again.';
    if (error && error.status === 403) return 'Your account does not have permission to manage the homepage.';
    if (error && (error.name === 'TypeError' || /failed to fetch/i.test(error.message || ''))) {
      return 'Cannot reach the Laravel API at ' + CMS.config.apiBase + '. Start it with "php artisan serve --host 127.0.0.1 --port 8001" and reload.';
    }
    return (error && error.message) || 'Something went wrong. Please try again.';
  }

  function openSignIn() {
    var account = document.querySelector('.cms-user');
    if (account) account.click();
  }

  function stateBox(container, kind, message, retry, error) {
    ui.clear(container);
    var box;
    if (kind === 'loading') {
      box = el('div', { class: 'hp-state', role: 'status' }, [el('div', { class: 'hp-spinner', 'aria-hidden': 'true' }), el('span', { text: message || 'Loading…' })]);
    } else {
      var children = [el('strong', { text: error && error.status === 401 ? 'Sign in required' : 'Could not load this page' }), el('span', { text: message })];
      var row = el('div', { class: 'hp-actions' });
      if (error && error.status === 401) {
        var signIn = el('button', { type: 'button', class: 'cms-btn cms-btn-primary', text: 'Sign in' });
        signIn.addEventListener('click', openSignIn);
        row.appendChild(signIn);
      }
      if (retry) {
        var again = el('button', { type: 'button', class: 'cms-btn cms-btn-outline', text: 'Try again' });
        again.addEventListener('click', retry);
        row.appendChild(again);
      }
      children.push(row);
      box = el('div', { class: 'hp-state', role: 'alert' }, children);
    }
    container.appendChild(box);
  }

  function setBusy(button, busy, label) {
    if (!button) return;
    if (busy) { button.dataset.label = button.textContent; button.textContent = label || 'Saving…'; }
    else if (button.dataset.label) { button.textContent = button.dataset.label; }
    button.disabled = busy;
  }

  function safeLink(value) {
    return /^(https?:\/\/\S+|mailto:\S+|\/\S*|#\S*)$/i.test(String(value || '').trim());
  }

  function statusPill(active) {
    return el('span', { class: 'cms-status cms-status-' + (active ? 'green' : 'gray'), text: active ? 'Active' : 'Inactive' });
  }

  function bannerImage(banner) {
    return banner.image_url
      ? el('img', { src: banner.image_url, alt: '', loading: 'lazy' })
      : el('span', { text: 'No image' });
  }

  /* ======================================================================
     Overview  (homepage-mg.html)
     ====================================================================== */
  function initOverview() {
    var root = $('hpOverview');
    var slideTimer = null;

    function renderSlides(card, banners) {
      var frame = el('div', { class: 'hp-slide' });
      var nav = el('div', { class: 'hp-slide-nav' });
      var index = 0;

      function show(i) {
        index = i;
        ui.clear(frame);
        var b = banners[i];
        frame.appendChild(bannerImage(b));
        frame.appendChild(el('div', { class: 'hp-slide-caption' }, [
          el('strong', { text: b.title }),
          b.subtitle ? el('span', { text: plain(b.subtitle, 140) }) : null
        ]));
        [].forEach.call(nav.children, function (dot, n) {
          if (n === i) dot.setAttribute('aria-current', 'true'); else dot.removeAttribute('aria-current');
        });
      }

      if (!banners.length) {
        frame.appendChild(el('div', { class: 'hp-slide-empty' }, ['No active banners yet. Add one under Manage Banner.']));
      } else {
        banners.forEach(function (b, i) {
          var dot = el('button', { type: 'button', class: 'hp-dot', 'aria-label': 'Show banner ' + (i + 1) + ': ' + b.title });
          dot.addEventListener('click', function () { clearInterval(slideTimer); show(i); });
          nav.appendChild(dot);
        });
        show(0);
        if (banners.length > 1 && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
          slideTimer = setInterval(function () { show((index + 1) % banners.length); }, 6000);
        }
      }
      card.appendChild(frame);
      if (banners.length > 1) card.appendChild(nav);
    }

    function render(data) {
      var settings = data.settings || {};
      var banners = data.banners || [];
      var active = banners.filter(function (b) { return b.is_active; });
      var featured = data.featured || [];
      ui.clear(root);

      root.appendChild(el('div', { class: 'hp-stats' }, [
        stat('Active banners', active.length + ' / ' + banners.length),
        stat('Featured items', String(featured.length)),
        stat('Featured content', ({ 'latest-news': 'Latest News', 'latest-announcements': 'Latest Announcements', 'upcoming-events': 'Upcoming Events' })[settings.featured_content] || 'Not set')
      ]));

      var previewCard = el('section', { class: 'cms-card', 'aria-label': 'Banner preview' }, [
        el('h2', { class: 'hp-card-title' }, [el('span', { text: 'Banner preview' }), el('a', { href: 'homepage-banner-mg.html', text: 'Manage' })])
      ]);
      renderSlides(previewCard, active);

      var rows = [
        ['Welcome heading', settings.welcome_heading],
        ['Call-to-action button', settings.cta_button_label],
        ['Call-to-action text', settings.call_to_action_text],
        ['Homepage description', plain(settings.homepage_description, 160)],
        ['University introduction', plain(settings.university_introduction, 160)]
      ];
      var list = el('dl', { class: 'hp-kv' });
      rows.forEach(function (row) {
        list.appendChild(el('div', {}, [
          el('dt', { text: row[0] }),
          row[1] ? el('dd', { text: row[1] }) : el('dd', { class: 'hp-muted', text: 'Not set' })
        ]));
      });
      var contentCard = el('section', { class: 'cms-card', 'aria-label': 'Homepage content' }, [
        el('h2', { class: 'hp-card-title' }, [el('span', { text: 'Homepage content' }), el('a', { href: 'homepage-coedit.html', text: 'Edit' })]),
        list
      ]);

      root.appendChild(el('div', { class: 'hp-overview' }, [previewCard, contentCard]));
    }

    function stat(label, value) {
      return el('div', { class: 'cms-card hp-stat' }, [el('div', { class: 'hp-stat-label', text: label }), el('div', { class: 'hp-stat-value', text: value })]);
    }

    function load() {
      clearInterval(slideTimer);
      stateBox(root, 'loading', 'Loading homepage…');
      api.get('/homepage').then(function (result) { render(result.data || {}); })
        .catch(function (error) { stateBox(root, 'error', errorMessage(error), load, error); });
    }
    load();
  }

  /* ======================================================================
     Banner list  (homepage-banner-mg.html)
     ====================================================================== */
  function initBanners() {
    var grid = $('hpBanners');
    var banners = [];

    function render() {
      ui.clear(grid);
      if (!banners.length) {
        var empty = el('div', { class: 'cms-card' });
        ui.renderEmpty(empty, { title: 'No banners yet', text: 'Add a banner to show it on the homepage.', actionLabel: '+ Add New Banner', actionHref: 'homepage-banner-mg-add.html' });
        grid.className = '';
        grid.appendChild(empty);
        return;
      }
      grid.className = 'hp-banner-grid';
      banners.forEach(function (banner, index) {
        var up = el('button', { type: 'button', class: 'hp-icon-btn', 'aria-label': 'Move "' + banner.title + '" earlier', title: 'Move earlier' }, [ui.icon('arrow-up')]);
        var down = el('button', { type: 'button', class: 'hp-icon-btn', 'aria-label': 'Move "' + banner.title + '" later', title: 'Move later' }, [ui.icon('arrow-down')]);
        up.disabled = index === 0;
        down.disabled = index === banners.length - 1;
        up.addEventListener('click', function () { move(index, -1); });
        down.addEventListener('click', function () { move(index, 1); });

        var del = el('button', { type: 'button', class: 'hp-icon-btn is-danger', text: 'Delete' });
        del.addEventListener('click', function () { remove(banner); });

        grid.appendChild(el('article', { class: 'cms-card hp-banner' }, [
          el('div', { class: 'hp-banner-img' }, [bannerImage(banner)]),
          el('div', { class: 'hp-banner-body' }, [
            el('h2', { text: banner.title }),
            el('p', { text: plain(banner.subtitle) || 'No description.' }),
            el('div', { class: 'hp-banner-foot' }, [
              el('div', { class: 'hp-row-actions' }, [statusPill(banner.is_active), el('span', { class: 'hp-order', text: '#' + (index + 1) })]),
              el('div', { class: 'hp-row-actions' }, [
                up, down,
                el('a', { class: 'hp-icon-btn', href: 'homepage-banner-mg-edit.html?id=' + encodeURIComponent(banner.banner_id), text: 'Edit' }),
                del
              ])
            ])
          ])
        ]));
      });
    }

    function move(index, delta) {
      var target = index + delta;
      if (target < 0 || target >= banners.length) return;
      var next = banners.slice();
      next.splice(target, 0, next.splice(index, 1)[0]);
      var changed = [];
      next.forEach(function (b, i) { if (Number(b.sort_order) !== i) changed.push({ id: b.banner_id, order: i }); });
      Promise.all(changed.map(function (c) { return api.put('/homepage/banners/' + c.id, { sort_order: c.order }); }))
        .then(function () { next.forEach(function (b, i) { b.sort_order = i; }); banners = next; render(); ui.toast('Banner order saved.', 'success'); })
        .catch(function (error) { ui.toast(errorMessage(error), 'error'); load(); });
    }

    function remove(banner) {
      ui.confirmDialog({ title: 'Delete banner?', message: '“' + banner.title + '” will be removed from the homepage. This cannot be undone.', confirmLabel: 'Delete' }).then(function (yes) {
        if (!yes) return;
        api.remove('/homepage/banners/' + banner.banner_id).then(function () {
          banners = banners.filter(function (b) { return b.banner_id !== banner.banner_id; });
          render();
          ui.toast('Banner deleted.', 'success');
        }).catch(function (error) { ui.toast(errorMessage(error), 'error'); });
      });
    }

    function load() {
      stateBox(grid, 'loading', 'Loading banners…');
      grid.className = '';
      api.get('/homepage/banners').then(function (result) { banners = result.data || []; render(); })
        .catch(function (error) { grid.className = ''; stateBox(grid, 'error', errorMessage(error), load, error); });
    }
    load();
  }

  /* ======================================================================
     Banner add / edit  (homepage-banner-mg-add.html / -edit.html)
     ====================================================================== */
  function initBannerForm() {
    var editId = ui.getParam('id');
    var form = $('bannerForm');
    var preview = $('bannerImagePreview');
    var file = $('bannerImage');
    var save = $('bannerSave');
    var imageData = null;     // data URL of a newly chosen image
    var hasImage = false;

    function showImage(src) {
      var img = el('img', { src: src, alt: 'Banner image preview' });
      preview.replaceChildren(img);
      hasImage = true;
    }

    $('bannerUploadButton').addEventListener('click', function () { file.click(); });
    file.addEventListener('change', function () {
      var chosen = file.files[0];
      if (!chosen) return;
      ui.readImage(chosen).then(function (dataUrl) {
        imageData = dataUrl;
        showImage(dataUrl);
        fieldError('bannerImageField', '');
      }).catch(function (error) { ui.toast(error.message, 'error'); file.value = ''; });
    });

    function fieldError(fieldId, message) {
      var field = $(fieldId);
      if (!field) return;
      field.classList.toggle('has-error', !!message);
      var out = field.querySelector('.cms-field-error');
      if (out) out.textContent = message;
    }

    function fill(banner) {
      $('bannerTitle').value = banner.title || '';
      $('bannerDescription').value = banner.subtitle || '';
      $('bannerButtonLabel').value = banner.button_label || '';
      $('bannerButtonLink').value = banner.button_url || '';
      $('bannerStatus').value = banner.is_active ? 'active' : 'inactive';
      ui.enhanceSelects();
      if (banner.image_url) showImage(banner.image_url);
    }

    if (editId) {
      form.hidden = true;
      var holder = $('bannerLoad');
      stateBox(holder, 'loading', 'Loading banner…');
      (function load() {
        stateBox(holder, 'loading', 'Loading banner…');
        api.get('/homepage/banners').then(function (result) {
          var banner = (result.data || []).filter(function (b) { return String(b.banner_id) === String(editId); })[0];
          if (!banner) { stateBox(holder, 'error', 'That banner no longer exists.', null); return; }
          ui.clear(holder);
          form.hidden = false;
          fill(banner);
        }).catch(function (error) { stateBox(holder, 'error', errorMessage(error), load, error); });
      })();
    }

    form.addEventListener('submit', function (event) {
      event.preventDefault();
      var title = $('bannerTitle').value.trim();
      var link = $('bannerButtonLink').value.trim();
      var ok = true;
      fieldError('bannerTitleField', ''); fieldError('bannerLinkField', ''); fieldError('bannerImageField', '');
      if (!title) { fieldError('bannerTitleField', 'Enter a banner title.'); ok = false; }
      if (link && !safeLink(link)) { fieldError('bannerLinkField', 'Use a link starting with http://, https://, mailto: or /.'); ok = false; }
      if (!hasImage) { fieldError('bannerImageField', 'Upload a banner image.'); ok = false; }
      if (!ok) { var bad = form.querySelector('.has-error'); if (bad) bad.scrollIntoView({ block: 'center', behavior: 'smooth' }); return; }

      var payload = {
        title: title,
        subtitle: $('bannerDescription').value.trim() || null,
        button_label: $('bannerButtonLabel').value.trim() || null,
        button_url: link || null,
        is_active: $('bannerStatus').value === 'active'
      };
      if (imageData) payload.image = imageData;

      setBusy(save, true);
      var request = editId ? api.put('/homepage/banners/' + encodeURIComponent(editId), payload) : api.post('/homepage/banners', payload);
      request.then(function () {
        ui.flash(editId ? 'Banner updated.' : 'Banner created.', 'success');
        window.location.href = 'homepage-banner-mg.html';
      }).catch(function (error) {
        setBusy(save, false);
        ui.toast(errorMessage(error), 'error');
      });
    });
  }

  /* ======================================================================
     Content editor  (homepage-coedit.html)
     ====================================================================== */
  function initEditor() {
    var form = $('homepageEditorForm');
    var holder = $('editorLoad');
    var save = $('editorSave');
    var rte = $('about-psu');
    var dirty = false;
    var fields = {
      welcome_heading: $('welcome-heading'),
      cta_button_label: $('cta-button-label'),
      university_introduction: $('university-introduction'),
      homepage_description: $('homepage-description'),
      call_to_action_text: $('call-to-action-text'),
      featured_content: $('featured-content')
    };

    function markDirty() { dirty = true; }
    window.addEventListener('beforeunload', function (event) { if (dirty) { event.preventDefault(); event.returnValue = ''; } });
    Object.keys(fields).forEach(function (key) { fields[key].addEventListener('input', markDirty); fields[key].addEventListener('change', markDirty); });
    rte.addEventListener('input', markDirty);

    /* rich-text toolbar (bold / italic / underline / list / link) */
    [].forEach.call(document.querySelectorAll('[data-format], [data-insert]'), function (button) {
      button.addEventListener('mousedown', function (event) { event.preventDefault(); });
      button.addEventListener('click', function () {
        var command = button.dataset.format || button.dataset.insert;
        var value = null;
        if (command === 'createLink') {
          value = (window.prompt('Enter the link URL (https://…):') || '').trim();
          if (!value) return;
          if (!/^(https?:\/\/|mailto:)/i.test(value)) { ui.toast('Links must start with http://, https:// or mailto:.', 'error'); return; }
        }
        rte.focus();
        document.execCommand(command, false, value);
        markDirty();
      });
    });

    /* paste as plain text so no foreign markup sneaks in */
    rte.addEventListener('paste', function (event) {
      event.preventDefault();
      var text = (event.clipboardData || window.clipboardData).getData('text/plain');
      document.execCommand('insertText', false, text);
    });

    function load() {
      form.hidden = true;
      stateBox(holder, 'loading', 'Loading homepage content…');
      api.get('/homepage/settings').then(function (result) {
        var data = result.data || {};
        Object.keys(fields).forEach(function (key) {
          if (data[key] !== null && data[key] !== undefined) fields[key].value = data[key];
        });
        if (!fields.featured_content.value) fields.featured_content.value = 'latest-news';
        ui.enhanceSelects();
        CMS.sanitize.render(rte, data.about_psu || '');
        ui.clear(holder);
        form.hidden = false;
        dirty = false;
      }).catch(function (error) { stateBox(holder, 'error', errorMessage(error), load, error); });
    }
    load();

    $('editorPreview').addEventListener('click', function () {
      window.open(new URL(CMS.config.publicSiteUrl, window.location.href).href, '_blank', 'noopener');
    });

    form.addEventListener('submit', function (event) {
      event.preventDefault();
      var settings = {};
      Object.keys(fields).forEach(function (key) {
        var value = fields[key].value.trim();
        settings[key] = value === '' ? null : value;
      });
      var heading = settings.welcome_heading;
      if (heading && heading.length > 255) { ui.toast('The welcome heading must be 255 characters or fewer.', 'error'); return; }
      if (settings.cta_button_label && settings.cta_button_label.length > 100) { ui.toast('The button label must be 100 characters or fewer.', 'error'); return; }
      settings.about_psu = CMS.sanitize.clean(rte.innerHTML) || null;

      setBusy(save, true);
      api.put('/homepage/settings', { settings: settings }).then(function () {
        dirty = false;
        setBusy(save, false);
        ui.toast('Homepage content saved.', 'success');
      }).catch(function (error) {
        setBusy(save, false);
        ui.toast(errorMessage(error), 'error');
      });
    });
  }

  /* ======================================================================
     Featured sections  (homepage-feature.html)
     ====================================================================== */
  function initFeatured() {
    var root = $('hpFeatured');
    var bar = $('hpSaveBar');
    var saveButton = $('hpSave');
    var discardButton = $('hpDiscard');
    var state = { news: [], announcements: [], events: [] };
    var dirty = false;

    window.addEventListener('beforeunload', function (event) { if (dirty) { event.preventDefault(); event.returnValue = ''; } });

    function setDirty(value) {
      dirty = value;
      bar.hidden = !value;
    }

    function fromServer(item) {
      var content = item.content || null;
      return {
        type: item.content_type,
        id: Number(item.content_id),
        title: content ? content.title : 'Content no longer available',
        date: content ? (content.published_at || content.start_at || content.created_at) : '',
        stale: !content || content.status !== 'published'
      };
    }

    function render() {
      ui.clear(root);
      var wrapper = el('div', { class: 'hp-featured' });
      var staleCount = 0;

      TYPES.forEach(function (type) {
        var items = state[type.key];
        var addButton = el('button', { type: 'button', class: 'cms-btn cms-btn-outline', 'aria-label': 'Add ' + type.singular }, [ui.icon('plus-lg'), ' Add']);
        addButton.addEventListener('click', function () { openPicker(type); });

        var list = el('ul', { class: 'hp-list' });
        if (!items.length) {
          list.appendChild(el('li', { class: 'hp-list-empty', text: 'Nothing featured yet. Use Add to choose a ' + type.singular + '.' }));
        }
        items.forEach(function (item, index) {
          if (item.stale) staleCount++;
          var up = el('button', { type: 'button', class: 'hp-icon-btn', 'aria-label': 'Move ' + item.title + ' up', title: 'Move up' }, [ui.icon('arrow-up')]);
          var down = el('button', { type: 'button', class: 'hp-icon-btn', 'aria-label': 'Move ' + item.title + ' down', title: 'Move down' }, [ui.icon('arrow-down')]);
          var removeButton = el('button', { type: 'button', class: 'hp-icon-btn is-danger', text: 'Remove', 'aria-label': 'Remove ' + item.title });
          up.disabled = index === 0;
          down.disabled = index === items.length - 1;
          up.addEventListener('click', function () { swap(type.key, index, index - 1); });
          down.addEventListener('click', function () { swap(type.key, index, index + 1); });
          removeButton.addEventListener('click', function () { items.splice(index, 1); setDirty(true); render(); });

          var dateText = formatDate(item.date);
          list.appendChild(el('li', { class: 'hp-item' + (item.stale ? ' is-stale' : '') }, [
            el('span', { class: 'hp-item-thumb', 'aria-hidden': 'true' }, [ui.icon(type.icon)]),
            el('div', { class: 'hp-item-copy' }, [
              el('strong', { text: item.title }),
              el('span', { text: item.stale ? 'No longer published — removed when you save' : dateText })
            ]),
            el('div', { class: 'hp-row-actions' }, [up, down, removeButton])
          ]));
        });

        wrapper.appendChild(el('section', { class: 'cms-card', 'aria-labelledby': 'hp-sec-' + type.key }, [
          el('div', { class: 'hp-section-head' }, [el('h2', { id: 'hp-sec-' + type.key, text: type.title }), addButton]),
          list
        ]));
      });

      if (staleCount) {
        root.appendChild(el('div', { class: 'hp-alert is-warn', role: 'status' }, [staleCount + ' featured item' + (staleCount === 1 ? ' is' : 's are') + ' no longer published and will be dropped when you save.']));
      }
      root.appendChild(wrapper);
    }

    function swap(key, from, to) {
      var items = state[key];
      if (to < 0 || to >= items.length) return;
      items.splice(to, 0, items.splice(from, 1)[0]);
      setDirty(true);
      render();
    }

    function openPicker(type) {
      var search = el('input', { type: 'search', class: 'cms-input', placeholder: 'Search ' + type.key + '…', 'aria-label': 'Search ' + type.key });
      var list = el('ul', { class: 'hp-picker-list' });
      var body = el('div', {}, [search, list]);
      var modalEl = ui.modalShell('Add ' + type.singular, [body], [el('button', { type: 'button', class: 'cms-btn cms-btn-outline', 'data-bs-dismiss': 'modal', text: 'Close' })]);
      var modal = ui.openModal(modalEl);
      var options = [];

      function renderOptions() {
        ui.clear(list);
        var term = search.value.trim().toLowerCase();
        var taken = state[type.key].map(function (i) { return i.id; });
        var shown = options.filter(function (o) { return !term || String(o.title).toLowerCase().indexOf(term) !== -1; });
        if (!shown.length) {
          list.appendChild(el('li', { class: 'hp-list-empty', text: options.length ? 'No matches.' : 'No published ' + type.key + ' to choose from yet.' }));
          return;
        }
        shown.forEach(function (option) {
          var already = taken.indexOf(Number(option.content_id)) !== -1;
          var button = el('button', { type: 'button', class: 'hp-picker-item' }, [
            el('div', {}, [el('strong', { text: option.title }), el('span', { text: (already ? 'Already featured · ' : '') + formatDate(option.published_at) })])
          ]);
          button.disabled = already;
          button.addEventListener('click', function () {
            state[type.key].push({ type: type.key, id: Number(option.content_id), title: option.title, date: option.published_at, stale: false });
            setDirty(true);
            render();
            modal.hide();
          });
          list.appendChild(el('li', {}, [button]));
        });
      }

      list.appendChild(el('li', { class: 'hp-list-empty', text: 'Loading…' }));
      api.get('/homepage/content/' + type.key).then(function (result) {
        options = result.data || [];
        renderOptions();
      }).catch(function (error) {
        ui.clear(list);
        list.appendChild(el('li', { class: 'hp-list-empty', text: errorMessage(error) }));
      });
      search.addEventListener('input', renderOptions);
      modalEl.addEventListener('shown.bs.modal', function () { search.focus(); });
    }

    function save() {
      var items = [];
      TYPES.forEach(function (type) {
        state[type.key].forEach(function (item) {
          if (item.stale) return;
          items.push({ content_type: type.key, content_id: item.id, section_title: type.title, sort_order: items.length, is_active: true });
        });
      });
      setBusy(saveButton, true);
      api.put('/homepage/featured', { items: items }).then(function (result) {
        applyServer(result.data || []);
        setDirty(false);
        render();
        ui.toast('Featured sections saved.', 'success');
      }).catch(function (error) { ui.toast(errorMessage(error), 'error'); }).then(function () { setBusy(saveButton, false); });
    }

    function applyServer(featured) {
      state = { news: [], announcements: [], events: [] };
      featured.forEach(function (item) { if (state[item.content_type]) state[item.content_type].push(fromServer(item)); });
    }

    function load() {
      setDirty(false);
      stateBox(root, 'loading', 'Loading featured sections…');
      api.get('/homepage/featured').then(function (result) {
        applyServer(result.data || []);
        render();
      }).catch(function (error) { stateBox(root, 'error', errorMessage(error), load, error); });
    }

    saveButton.addEventListener('click', save);
    discardButton.addEventListener('click', function () { load(); });
    load();
  }

  /* ---------- boot ---------- */
  function boot() {
    var page = document.body.getAttribute('data-hp');
    var start = { overview: initOverview, banners: initBanners, 'banner-form': initBannerForm, editor: initEditor, featured: initFeatured }[page];
    if (start) start();
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
})();
