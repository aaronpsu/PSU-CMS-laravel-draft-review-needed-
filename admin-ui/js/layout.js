/* ==========================================================================
   layout.js — behavior for the shared sidebar and header.
   The sidebar/header HTML itself sits inside each page between
   "BEGIN SHARED" / "END SHARED" comments (so it can become a PHP include).
   This file also builds the consistent navigation for the integrated
   personnel, resource, and website-layout screens.
   Set <body data-page="all-news"> to choose the active sidebar link.
   ========================================================================== */
window.CMS = window.CMS || {};

CMS.layout = (function () {
  var layoutScript = document.currentScript;
  var apiClientUrl = layoutScript ? new URL('api-client.js', layoutScript.src).href : null;

  function setDrawer(open) {
    document.body.classList.toggle('cms-sidebar-open', open);
    var t = document.querySelector('.cms-menu-toggle');
    if (t) t.setAttribute('aria-expanded', open ? 'true' : 'false');
  }

  function openGroup(group, open) {
    group.classList.toggle('is-open', open);
    var toggle = group.querySelector('.cms-nav-toggle');
    if (toggle) toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
  }

  function resourceGroup(anchor, name, iconName, links) {
    var group = document.createElement('div');
    group.className = 'cms-nav-group';
    group.setAttribute('data-group', name);

    var toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'cms-nav-link cms-nav-toggle';
    toggle.setAttribute('aria-expanded', 'false');
    var subnavId = 'subnav-' + name;
    toggle.setAttribute('aria-controls', subnavId);
    toggle.appendChild(CMS.ui.el('i', { class: 'bi bi-' + iconName, 'aria-hidden': 'true' }));
    toggle.appendChild(CMS.ui.el('span', { class: 'cms-nav-text', text: name === 'documents' ? 'Document Library' : 'Media Library' }));
    toggle.appendChild(CMS.ui.el('span', { class: 'cms-nav-caret', 'aria-hidden': 'true' }));
    group.appendChild(toggle);

    var subnav = document.createElement('div');
    subnav.className = 'cms-subnav';
    subnav.id = subnavId;
    links.forEach(function (item) {
      var link = CMS.ui.el('a', {
        class: 'cms-nav-link',
        href: new URL(item.path, layoutScript.src).href,
        'data-nav': item.page
      }, [CMS.ui.el('span', { class: 'cms-nav-text', text: item.label })]);
      subnav.appendChild(link);
    });
    group.appendChild(subnav);
    anchor.parentNode.replaceChild(group, anchor);
  }

  function installResourceNavigation() {
    var sidebar = document.querySelector('.cms-sidebar');
    if (!sidebar || !layoutScript) return;

    var categoriesLink = sidebar.querySelector('.cms-nav-link[data-nav="document-categories"]');
    if (categoriesLink) categoriesLink.remove();

    var documentsLink = sidebar.querySelector('.cms-nav-link[data-nav="documents"]');
    if (documentsLink && !documentsLink.closest('.cms-nav-group')) {
      resourceGroup(documentsLink, 'documents', 'file-earmark-text', [
        { label: 'All Documents', page: 'documents', path: '../resources/library.html?type=documents' },
        { label: 'Upload Document', page: 'upload-document', path: '../resources/upload-document.html' },
        { label: 'Document Categories', page: 'document-categories', path: '../resources/library.html?type=categories' }
      ]);
    }

    var mediaLink = sidebar.querySelector('.cms-nav-link[data-nav="media"]');
    if (mediaLink && !mediaLink.closest('.cms-nav-group')) {
      resourceGroup(mediaLink, 'media', 'image', [
        { label: 'All Media', page: 'media', path: '../resources/library.html?type=media' },
        { label: 'Upload Media', page: 'upload-media', path: '../resources/upload-media.html' }
      ]);
    }
  }

  function installIntegratedAdminNavigation() {
    var page = document.body.getAttribute('data-page');
    var supportedPages = {
      personnel: true,
      'personnel-form': true,
      'resource-library': true,
      'upload-document': true,
      'upload-media': true,
      'website-layout': true
    };
    var nav = document.querySelector('.cms-sidebar nav[aria-label="Main navigation"]');
    if (!supportedPages[page] || !nav || !layoutScript) return;

    function link(item) {
      var children = [];
      if (item.icon) children.push(CMS.ui.el('i', { class: 'bi bi-' + item.icon, 'aria-hidden': 'true' }));
      children.push(CMS.ui.el('span', { class: 'cms-nav-text', text: item.label }));
      return CMS.ui.el('a', {
        class: 'cms-nav-link',
        href: new URL(item.path, layoutScript.src).href,
        'data-nav': item.page
      }, children);
    }

    function group(item) {
      var groupElement = document.createElement('div');
      groupElement.className = 'cms-nav-group';
      groupElement.setAttribute('data-group', item.name);

      var toggle = CMS.ui.el('button', {
        type: 'button',
        class: 'cms-nav-link cms-nav-toggle',
        'aria-expanded': 'false',
        'aria-controls': 'subnav-' + item.name
      }, [
        CMS.ui.el('i', { class: 'bi bi-' + item.icon, 'aria-hidden': 'true' }),
        CMS.ui.el('span', { class: 'cms-nav-text', text: item.label }),
        CMS.ui.el('span', { class: 'cms-nav-caret', 'aria-hidden': 'true' })
      ]);
      if (item.name === 'users') toggle.setAttribute('data-nav', 'users');
      groupElement.appendChild(toggle);

      var subnav = document.createElement('div');
      subnav.className = 'cms-subnav';
      subnav.id = 'subnav-' + item.name;
      item.links.forEach(function (child) { subnav.appendChild(link(child)); });
      groupElement.appendChild(subnav);
      return groupElement;
    }

    var sections = [
      { label: 'Main', links: [
        { label: 'Dashboard', page: 'dashboard', path: '../dashboard/dashboard.html', icon: 'columns-gap' }
      ] },
      { label: 'Website content', links: [
        { label: 'Homepage Management', page: 'homepage', path: '../homapage/homepage-mg.html', icon: 'house' },
        { name: 'news', label: 'News & Announcements', icon: 'journal-text', links: [
          { label: 'All News', page: 'all-news', path: '../news/all-news.html' },
          { label: 'Add New News', page: 'add-news', path: '../news/add-news.html' },
          { label: 'All Announcements', page: 'all-announcements', path: '../announcements/all-announcements.html' },
          { label: 'Add New Announcement', page: 'add-announcement', path: '../announcements/add-announcement.html' },
          { label: 'News & Announcement Categories', page: 'content-categories', path: '../categories/content-categories.html' }
        ] },
        { name: 'events', label: 'Events Management', icon: 'calendar', links: [
          { label: 'All Events', page: 'all-events', path: '../events/all-events.html' },
          { label: 'Add New Event', page: 'add-event', path: '../events/add-event.html' },
          { label: 'Event Categories', page: 'event-categories', path: '../events/event-categories.html' }
        ] },
        { name: 'university', label: 'University Structure', icon: 'bank', links: [
          { label: 'Colleges', page: 'colleges', path: '../colleges/colleges.html' },
          { label: 'Departments', page: 'departments', path: '../departments/departments.html' },
          { label: 'Academic Programs', page: 'programs', path: '../programs/academic-programs.html' },
          { label: 'Campuses', page: 'campuses', path: '../campuses/campuses.html' }
        ] },
        { name: 'faculty', label: 'Faculty & Staff Directory', icon: 'people', links: [
          { label: 'All Personnel', page: 'personnel', path: '../faculty/index.html' },
          { label: 'Add New Personnel', page: 'add-personnel', path: '../faculty/personnel-form.html' }
        ] }
      ] },
      { label: 'Resources', links: [
        { label: 'Document Library', page: 'documents', path: '../resources/library.html?type=documents', icon: 'file-earmark-text' },
        { label: 'Media Library', page: 'media', path: '../resources/library.html?type=media', icon: 'image' },
        { label: 'Document Categories', page: 'document-categories', path: '../resources/library.html?type=categories', icon: 'folder' }
      ] },
      { label: 'Communication', links: [
        { name: 'contact', label: 'Contact & Feedback', icon: 'envelope', links: [
          { label: 'All Messages', page: 'all-messages', path: '../messages/all-messages.html' },
          { label: 'Unread Messages', page: 'unread-messages', path: '../messages/unread-messages.html' },
          { label: 'Feedback', page: 'feedback', path: '../messages/feedback.html' }
        ] },
        { label: 'Search', page: 'search', path: '../search/search-results.html', icon: 'search' }
      ] },
      { label: 'System', links: [
        { name: 'users', label: 'User Management', icon: 'person', links: [
          { label: 'All Users', page: 'all-users', path: '../users/all-users.html' },
          { label: 'Add New User', page: 'add-user', path: '../users/add-user.html' },
          { label: 'User Roles', page: 'user-roles', path: '../users/user-roles.html' }
        ] },
        { name: 'settings', label: 'Settings', icon: 'sliders', links: [
          { label: 'General Settings', page: 'general-settings', path: '../system/system-gen.html' },
          { label: 'Website Information', page: 'website-information', path: '../system/system-web-info.html' },
          { label: 'Account Settings', page: 'account-settings', path: '../settings/account-settings.html' },
          { label: 'Security Settings', page: 'security-settings', path: '../settings/security-settings.html' }
        ] }
      ] },
      { links: [
        { name: 'appearance', label: 'Appearance', icon: 'palette', links: [
          { label: 'Theme Settings', page: 'theme-settings', path: '../settings/theme-settings.html' },
          { label: 'Website Layout', page: 'website-layout', path: '../settings/website-layout.html' }
        ] }
      ] }
    ];

    CMS.ui.clear(nav);
    sections.forEach(function (section) {
      if (section.label) nav.appendChild(CMS.ui.el('p', { class: 'cms-nav-label', text: section.label }));
      section.links.forEach(function (item) {
        nav.appendChild(item.links ? group(item) : link(item));
      });
    });
  }

  function loadApiClient() {
    if (CMS.api) return Promise.resolve(CMS.api);
    if (!apiClientUrl) return Promise.reject(new Error('The API client could not be located.'));

    return new Promise(function (resolve, reject) {
      var script = document.createElement('script');
      script.src = apiClientUrl;
      script.onload = function () {
        if (CMS.api) resolve(CMS.api);
        else reject(new Error('The API client did not initialize.'));
      };
      script.onerror = function () { reject(new Error('The API client could not be loaded.')); };
      document.head.appendChild(script);
    });
  }

  function initNotifications(button) {
    button.removeAttribute('data-pending');
    button.setAttribute('aria-haspopup', 'true');
    button.setAttribute('aria-expanded', 'false');

    var badge = button.querySelector('.cms-badge-count');
    if (badge) badge.remove();

    var wrapper = document.createElement('div');
    wrapper.className = 'cms-notifications';
    button.parentNode.insertBefore(wrapper, button);
    wrapper.appendChild(button);

    var panel = document.createElement('section');
    panel.className = 'cms-notification-panel';
    panel.id = 'cmsNotificationPanel';
    panel.hidden = true;
    panel.setAttribute('aria-label', 'Notifications');
    panel.setAttribute('aria-live', 'polite');

    var title = document.createElement('h2');
    title.className = 'cms-notification-title';
    title.textContent = 'Notifications';
    panel.appendChild(title);

    var list = document.createElement('ul');
    list.className = 'cms-notification-list';
    panel.appendChild(list);
    wrapper.appendChild(panel);
    button.setAttribute('aria-controls', panel.id);

    var unreadCount = 0;
    var notifications = [];
    var loading = null;

    function updateBadge(count) {
      unreadCount = count;
      button.setAttribute('aria-label', count ? 'Notifications (' + count + ' unread)' : 'Notifications');
      var currentBadge = button.querySelector('.cms-badge-count');
      if (!count) {
        if (currentBadge) currentBadge.remove();
        return;
      }
      if (!currentBadge) {
        currentBadge = document.createElement('span');
        currentBadge.className = 'cms-badge-count';
        button.appendChild(currentBadge);
      }
      currentBadge.textContent = count > 99 ? '99+' : String(count);
    }

    function addStatus(message, retry) {
      CMS.ui.clear(list);
      var item = document.createElement('li');
      item.className = 'cms-notification-empty';
      item.setAttribute('role', 'status');
      item.textContent = message;
      list.appendChild(item);
      if (retry) {
        var retryButton = document.createElement('button');
        retryButton.type = 'button';
        retryButton.className = 'cms-btn cms-btn-outline';
        retryButton.textContent = 'Try again';
        retryButton.addEventListener('click', loadNotifications);
        list.appendChild(retryButton);
      }
    }

    function isRead(notification) {
      return notification.is_read === true || Number(notification.is_read) === 1;
    }

    function formatDate(value) {
      if (!value) return '';
      var date = new Date(String(value).replace(' ', 'T'));
      return isNaN(date.getTime()) ? String(value) : date.toLocaleString();
    }

    function renderNotifications() {
      CMS.ui.clear(list);
      if (!notifications.length) {
        addStatus('No notifications.');
        return;
      }

      notifications.forEach(function (notification) {
        var read = isRead(notification);
        var entry = document.createElement('li');
        entry.className = 'cms-notification-entry' + (read ? '' : ' is-unread');

        var item = document.createElement('button');
        item.type = 'button';
        item.className = 'cms-notification-item';
        item.disabled = read;

        var message = document.createElement('span');
        message.className = 'cms-notification-message';
        message.textContent = notification.message || 'Notification';
        item.appendChild(message);

        var meta = document.createElement('span');
        meta.className = 'cms-notification-meta';
        meta.textContent = [notification.type, formatDate(notification.created_at)].filter(Boolean).join(' - ');
        item.appendChild(meta);

        if (!read) {
          item.addEventListener('click', function () {
            item.disabled = true;
            loadApiClient().then(function (api) {
              return api.put('/auth/notifications/' + encodeURIComponent(notification.notification_id) + '/read', {});
            }).then(function () {
              notification.is_read = 1;
              updateBadge(Math.max(0, unreadCount - 1));
              renderNotifications();
            }).catch(function (error) {
              item.disabled = false;
              CMS.ui.toast(error.message, 'error');
            });
          });
        }

        entry.appendChild(item);
        list.appendChild(entry);
      });
    }

    function loadNotifications() {
      if (loading) return loading;
      addStatus('Loading notifications...');
      loading = loadApiClient().then(function (api) {
        return api.get('/auth/notifications');
      }).then(function (result) {
        if (!result || !Array.isArray(result.data)) {
          throw new Error('The server returned an invalid notifications response.');
        }
        var count = Number(result.unreadCount);
        if (!isFinite(count) || count < 0) {
          throw new Error('The server returned an invalid unread notification count.');
        }
        notifications = result.data;
        updateBadge(count);
        renderNotifications();
      }).catch(function (error) {
        updateBadge(0);
        addStatus('Unable to load notifications: ' + error.message, true);
      }).then(function () {
        loading = null;
      });
      return loading;
    }

    function setOpen(open) {
      panel.hidden = !open;
      button.setAttribute('aria-expanded', open ? 'true' : 'false');
      if (open) loadNotifications();
    }

    button.addEventListener('click', function () {
      setOpen(panel.hidden);
    });
    document.addEventListener('click', function (event) {
      if (!wrapper.contains(event.target)) setOpen(false);
    });
    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && !panel.hidden) {
        setOpen(false);
        button.focus();
      }
    });

    loadNotifications();
    return loadNotifications;
  }

  function initAccount(button, refreshNotifications, user) {
    var accountName = button.querySelector('.cms-user-name');
    var currentUser = null;

    function setUser(user) {
      currentUser = user;
      if (accountName) accountName.textContent = user ? user.name : 'Sign in';
      button.setAttribute('aria-label', user ? 'Account: ' + user.name : 'Sign in');
    }

    function openAccountDialog() {
      var email = CMS.ui.el('input', { type: 'email', class: 'form-control', name: 'email', autocomplete: 'username', required: 'required' });
      var password = CMS.ui.el('input', { type: 'password', class: 'form-control', name: 'password', autocomplete: 'current-password', required: 'required' });
      var form = CMS.ui.el('form', { class: 'vstack gap-3' });
      var body;
      var footer;
      var modal;

      if (currentUser) {
        body = [
          CMS.ui.el('p', { text: 'Signed in as ' + currentUser.name + ' (' + currentUser.email + ').' }),
          CMS.ui.el('p', { text: 'Role: ' + currentUser.role })
        ];
        var logout = CMS.ui.el('button', { type: 'button', class: 'btn btn-outline-danger', text: 'Sign out' });
        footer = [logout];
        modal = CMS.ui.openModal(CMS.ui.modalShell('Your account', body, footer));
        logout.addEventListener('click', function () {
          logout.disabled = true;
          CMS.api.post('/auth/logout', {}).then(function () {
            CMS.api.clearCsrf();
            setUser(null);
            if (refreshNotifications) refreshNotifications();
            modal.hide();
            window.location.replace(new URL('../login/login-sc.html', window.location.href).href);
          }).catch(function (error) {
            logout.disabled = false;
            CMS.ui.toast(error.message, 'error');
          });
        });
        return;
      }

      form.appendChild(CMS.ui.el('label', { class: 'form-label', text: 'Email' }));
      form.lastChild.appendChild(email);
      form.appendChild(CMS.ui.el('label', { class: 'form-label', text: 'Password' }));
      form.lastChild.appendChild(password);
      var signIn = CMS.ui.el('button', { type: 'submit', class: 'btn btn-primary', text: 'Sign in' });
      signIn.style.width = '100%';
      form.appendChild(signIn);
      body = [form];
      footer = [];
      modal = CMS.ui.openModal(CMS.ui.modalShell('Sign in to PSU CMS', body, footer));
      form.addEventListener('submit', function (event) {
        event.preventDefault();
        signIn.disabled = true;
        CMS.api.post('/auth/login', { email: email.value.trim(), password: password.value }).then(function (result) {
          setUser(result.user);
          if (refreshNotifications) refreshNotifications();
          modal.hide();
          CMS.ui.toast('Signed in successfully.', 'success');
        }).catch(function (error) {
          signIn.disabled = false;
          CMS.ui.toast(error.message, 'error');
        });
      });
    }

    button.removeAttribute('data-pending');
    setUser(user);
    button.addEventListener('click', openAccountDialog);
  }

  function initLayout(user) {
    installIntegratedAdminNavigation();

    var pendingPages = {
      dashboard: '../dashboard/dashboard.html',
      homepage: '../homapage/homepage-mg.html',
      university: '../colleges/colleges.html',
      faculty: '../faculty/index.html',
      documents: '../resources/library.html?type=documents',
      media: '../resources/library.html?type=media',
      contact: '../messages/all-messages.html',
      users: '../users/all-users.html',
      settings: '../system/system-gen.html',
      'general-settings': '../system/system-gen.html',
      'website-information': '../system/system-web-info.html',
      appearance: '../settings/theme-settings.html'
    };
    [].forEach.call(document.querySelectorAll('.cms-nav-link[data-pending]'), function (link) {
      var destination = pendingPages[link.getAttribute('data-nav')];
      if (destination) {
        link.href = new URL(destination, window.location.href).href;
        link.removeAttribute('data-pending');
      }
    });

    // var settingsLink = document.querySelector('.cms-sidebar .cms-nav-link[data-nav="settings"]');
    // if (settingsLink && !settingsLink.closest('.cms-nav-group')) {
    //   var settingsGroup = document.createElement('div');
    //   settingsGroup.className = 'cms-nav-group';
    //   settingsGroup.dataset.group = 'settings';
    //   settingsGroup.dataset.generated = 'true';

    //   var settingsToggle = document.createElement('button');
    //   settingsToggle.type = 'button';
    //   settingsToggle.className = 'cms-nav-link cms-nav-toggle';
    //   settingsToggle.setAttribute('aria-expanded', 'false');
    //   settingsToggle.setAttribute('aria-controls', 'subnav-settings');
    //   while (settingsLink.firstChild) settingsToggle.appendChild(settingsLink.firstChild);

    //   var settingsSubnav = document.createElement('div');
    //   settingsSubnav.className = 'cms-subnav';
    //   settingsSubnav.id = 'subnav-settings';
    //   settingsGroup.appendChild(settingsToggle);
    //   settingsGroup.appendChild(settingsSubnav);
    //   settingsLink.parentNode.replaceChild(settingsGroup, settingsLink);
    // }

    // var facultyLink = document.querySelector('.cms-sidebar .cms-nav-link[data-nav="faculty"]');
    // var mainNavLabel = [].filter.call(document.querySelectorAll('.cms-sidebar .cms-nav-label'), function (label) {
    //   return label.textContent.trim() === 'Main';
    // })[0];
    // if (facultyLink) {
    //   [].forEach.call(facultyLink.querySelectorAll('.bi, .cms-nav-caret'), function (icon) {
    //     icon.remove();
    //   });
    //   if (mainNavLabel) {
    //     var dashboardLink = mainNavLabel.nextElementSibling;
    //     var insertAfter = dashboardLink && dashboardLink.getAttribute('data-nav') === 'dashboard' ? dashboardLink : mainNavLabel;
    //     insertAfter.parentNode.insertBefore(facultyLink, insertAfter.nextSibling);
    //   }
    // }

    // var settingsGroupElement = document.querySelector('.cms-nav-group[data-group="settings"]');
    // var settingsSubnavElement = settingsGroupElement && settingsGroupElement.querySelector('.cms-subnav');
    // var appearanceGroup = document.querySelector('.cms-nav-group[data-group="appearance"]');
    // if (settingsSubnavElement && appearanceGroup) {
    //   [].forEach.call(appearanceGroup.querySelectorAll('.cms-subnav .cms-nav-link'), function (link) {
    //     settingsSubnavElement.appendChild(link);
    //   });
    //   appearanceGroup.remove();
    // }

    // var appearanceLink = document.querySelector('.cms-sidebar .cms-nav-link[data-nav="appearance"]');
    // if (settingsSubnavElement && appearanceLink) {
    //   var appearanceCaret = appearanceLink.querySelector('.cms-nav-caret');
    //   if (appearanceCaret) appearanceCaret.remove();
    //   appearanceLink.setAttribute('data-nav', 'theme-settings');
    //   appearanceLink.href = new URL('../settings/theme-settings.html', window.location.href).href;
    //   appearanceLink.removeAttribute('data-pending');
    //   var appearanceText = appearanceLink.querySelector('.cms-nav-text');
    //   if (appearanceText) appearanceText.textContent = 'Theme Settings';
    //   settingsSubnavElement.appendChild(appearanceLink);
    // }

    // var settingsToggleElement = settingsGroupElement && settingsGroupElement.querySelector('.cms-nav-toggle');
    // if (settingsToggleElement) {
    //   if (!settingsToggleElement.querySelector('.cms-nav-caret')) {
    //     settingsToggleElement.appendChild(CMS.ui.el('span', { class: 'cms-nav-caret', 'aria-hidden': 'true' }));
    //   }
    // }

    // function addSubnavLink(groupName, label, page, href) {
    //   var subnav = document.querySelector('.cms-nav-group[data-group="' + groupName + '"] .cms-subnav');
    //   if (!subnav || subnav.querySelector('.cms-nav-link[data-nav="' + page + '"]')) return;
    //   var children = [];
    //   children.push(CMS.ui.el('span', { class: 'cms-nav-text', text: label }));
    //   subnav.appendChild(CMS.ui.el('a', {
    //     class: 'cms-nav-link', href: new URL(href, window.location.href).href, 'data-nav': page
    //   }, children));
    // }

    // addSubnavLink('settings', 'General Settings', 'general-settings', '../system/system-gen.html');
    // addSubnavLink('settings', 'Website Information', 'website-information', '../system/system-web-info.html');
    // addSubnavLink('settings', 'Theme Settings', 'theme-settings', '../settings/theme-settings.html');
    // addSubnavLink('news', 'News & Announcement Categories', 'content-categories', '../categories/content-categories.html');
    // addSubnavLink('university', 'Campuses', 'campuses', '../campuses/campuses.html');
    // addSubnavLink('settings', 'Account Settings', 'account-settings', '../settings/account-settings.html');
    // addSubnavLink('settings', 'Security Settings', 'security-settings', '../settings/security-settings.html');
    // addSubnavLink('settings', 'Forms', 'forms', '../contact/contact-form.html');
    // if (settingsGroupElement) {
    //   [].forEach.call(settingsGroupElement.querySelectorAll('.bi'), function (icon) {
    //     icon.remove();
    //   });
    // }
    // if (settingsSubnavElement) {
    //   var settingsOrder = ['general-settings', 'website-information', 'theme-settings', 'website-layout', 'account-settings', 'security-settings', 'forms'];
    //   [].slice.call(settingsSubnavElement.querySelectorAll('.cms-nav-link'))
    //     .sort(function (a, b) {
    //       var aIndex = settingsOrder.indexOf(a.getAttribute('data-nav'));
    //       var bIndex = settingsOrder.indexOf(b.getAttribute('data-nav'));
    //       return (aIndex === -1 ? settingsOrder.length : aIndex) - (bIndex === -1 ? settingsOrder.length : bIndex);
    //     })
    //     .forEach(function (link) { settingsSubnavElement.appendChild(link); });
    // }
    
    var userManagementGroup = document.querySelector('.cms-sidebar .cms-nav-group[data-group="users"]');
    var settingsGroupElement = document.querySelector('.cms-sidebar .cms-nav-group[data-group="settings"]');
    if (String(user.role || '').toLowerCase() !== 'admin') {
      if (userManagementGroup) userManagementGroup.hidden = true;
      if (settingsGroupElement) settingsGroupElement.hidden = true;
    }

    var notificationButton = document.querySelector('.cms-notif');
    var refreshNotifications = notificationButton ? initNotifications(notificationButton) : null;

    var viewSiteText = document.querySelector('.cms-view-site-text');
    var viewSiteLink = viewSiteText && viewSiteText.closest('a');
    if (viewSiteLink && CMS.config.publicSiteUrl) {
      viewSiteLink.href = new URL(CMS.config.publicSiteUrl, window.location.href).href;
    }

    var accountButton = document.querySelector('.cms-user');
    if (accountButton) initAccount(accountButton, refreshNotifications, user);

    /* 1. active link + its group */
    var page = document.body.getAttribute('data-page');
    if (page === 'resource-library') {
      var resourceType = new URLSearchParams(window.location.search).get('type');
      page = resourceType === 'media' ? 'media' : (resourceType === 'categories' ? 'document-categories' : 'documents');
    }
    if (page === 'personnel-form') page = 'add-personnel';
    installResourceNavigation();
    var pendingLayoutLink = document.querySelector('.cms-nav-link[data-nav="website-layout"][data-pending]');
    if (pendingLayoutLink && layoutScript) {
      pendingLayoutLink.href = new URL('../settings/website-layout.html', layoutScript.src).href;
      pendingLayoutLink.removeAttribute('data-pending');
    }
    var link = page ? document.querySelector('.cms-nav-link[data-nav="' + page + '"]') : null;
    if (link) {
      link.classList.add('active');
      link.setAttribute('aria-current', 'page');
      var group = link.closest('.cms-nav-group');
      if (group) openGroup(group, true);
    }

    /* 2. expand / collapse groups */
    [].forEach.call(document.querySelectorAll('.cms-nav-toggle'), function (btn) {
      btn.addEventListener('click', function () {
        var group = btn.closest('.cms-nav-group');
        openGroup(group, !group.classList.contains('is-open'));
      });
    });

    /* 3. links to pages other teammates will build */
    document.addEventListener('click', function (e) {
      var a = e.target.closest('[data-pending]');
      if (!a) return;
      e.preventDefault();
      var name = a.getAttribute('data-pending') || 'This page';
      CMS.ui.toast(name + ' isn\u2019t part of this module yet.', 'info');
    });

    /* 4. mobile drawer */
    var menuBtn = document.querySelector('.cms-menu-toggle');
    if (menuBtn) menuBtn.addEventListener('click', function () { setDrawer(!document.body.classList.contains('cms-sidebar-open')); });
    var backdrop = document.querySelector('.cms-backdrop');
    if (backdrop) backdrop.addEventListener('click', function () { setDrawer(false); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') setDrawer(false); });
    [].forEach.call(document.querySelectorAll('.cms-sidebar a.cms-nav-link'), function (a) {
      a.addEventListener('click', function () { setDrawer(false); });
    });

    /* 5. header search: Enter or the icon submits; empty search does nothing */
    var form = document.querySelector('.cms-search');
    if (form) {
      var input = form.querySelector('input[name="q"]');
      form.addEventListener('submit', function (e) {
        if (!input.value.trim()) { e.preventDefault(); input.focus(); }
      });
      var toggle = document.querySelector('.cms-search-toggle');
      if (toggle) toggle.addEventListener('click', function () {
        var open = form.classList.toggle('is-open');
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (open) input.focus();
      });
    }

    /* 6. shared widgets (feature flags first, so removed fields never get enhanced) */
    CMS.ui.applyFeatures();
    CMS.ui.enhanceSelects();
    CMS.ui.showFlash();
  }

  function showAuthenticationError(error) {
    document.body.classList.add('cms-auth-error');
    var panel = document.createElement('main');
    panel.className = 'cms-auth-message';
    var heading = document.createElement('h1');
    heading.textContent = 'Unable to verify your sign-in';
    var message = document.createElement('p');
    message.textContent = 'The administration panel is hidden until your session can be checked. ' + error.message;
    var actions = document.createElement('div');
    actions.className = 'cms-auth-message-actions';
    var retry = document.createElement('button');
    retry.type = 'button';
    retry.className = 'cms-btn cms-btn-primary';
    retry.textContent = 'Try again';
    retry.addEventListener('click', function () { window.location.reload(); });
    var signIn = document.createElement('a');
    signIn.className = 'cms-btn cms-btn-outline';
    signIn.href = new URL('../login/login-sc.html', window.location.href).href;
    signIn.textContent = 'Go to sign in';
    actions.appendChild(retry);
    actions.appendChild(signIn);
    panel.appendChild(heading);
    panel.appendChild(message);
    panel.appendChild(actions);
    document.body.appendChild(panel);
  }

  function init() {
    loadApiClient().then(function (api) {
      return api.get('/auth/me');
    }).then(function (result) {
      if (!result.user) {
        var error = new Error('Please sign in to continue.');
        error.status = 401;
        throw error;
      }
      CMS.config.currentUser = result.user;
      initLayout(result.user);
      document.body.classList.add('cms-authenticated');
    }).catch(function (error) {
      if (error.status === 401) {
        window.location.replace(new URL('../login/login-sc.html', window.location.href).href);
        return;
      }
      showAuthenticationError(error);
    });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();

  return { init: init };
})();
