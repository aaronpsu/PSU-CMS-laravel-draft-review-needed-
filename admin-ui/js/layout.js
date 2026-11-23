/* ==========================================================================
   layout.js — behavior for the shared sidebar and header.
   The sidebar/header HTML itself sits inside each page between
   "BEGIN SHARED" / "END SHARED" comments (so it can become a PHP include).
   This file only: marks the active link, opens/closes menu groups, runs the
   mobile drawer, and handles header search + "coming soon" links.
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

    var settingsLink = document.querySelector('.cms-sidebar .cms-nav-link[data-nav="settings"]');
    if (settingsLink && !settingsLink.closest('.cms-nav-group')) {
      var settingsGroup = document.createElement('div');
      settingsGroup.className = 'cms-nav-group';
      settingsGroup.dataset.group = 'settings';
      settingsGroup.dataset.generated = 'true';

      var settingsToggle = document.createElement('button');
      settingsToggle.type = 'button';
      settingsToggle.className = 'cms-nav-link cms-nav-toggle';
      settingsToggle.setAttribute('aria-expanded', 'false');
      settingsToggle.setAttribute('aria-controls', 'subnav-settings');
      while (settingsLink.firstChild) settingsToggle.appendChild(settingsLink.firstChild);

      var settingsSubnav = document.createElement('div');
      settingsSubnav.className = 'cms-subnav';
      settingsSubnav.id = 'subnav-settings';
      settingsGroup.appendChild(settingsToggle);
      settingsGroup.appendChild(settingsSubnav);
      settingsLink.parentNode.replaceChild(settingsGroup, settingsLink);
    }

    var facultyLink = document.querySelector('.cms-sidebar .cms-nav-link[data-nav="faculty"]');
    var mainNavLabel = [].filter.call(document.querySelectorAll('.cms-sidebar .cms-nav-label'), function (label) {
      return label.textContent.trim() === 'Main';
    })[0];
    if (facultyLink) {
      [].forEach.call(facultyLink.querySelectorAll('.bi, .cms-nav-caret'), function (icon) {
        icon.remove();
      });
      if (mainNavLabel) {
        var dashboardLink = mainNavLabel.nextElementSibling;
        var insertAfter = dashboardLink && dashboardLink.getAttribute('data-nav') === 'dashboard' ? dashboardLink : mainNavLabel;
        insertAfter.parentNode.insertBefore(facultyLink, insertAfter.nextSibling);
      }
    }

    var settingsGroupElement = document.querySelector('.cms-nav-group[data-group="settings"]');
    var settingsSubnavElement = settingsGroupElement && settingsGroupElement.querySelector('.cms-subnav');
    var appearanceGroup = document.querySelector('.cms-nav-group[data-group="appearance"]');
    if (settingsSubnavElement && appearanceGroup) {
      [].forEach.call(appearanceGroup.querySelectorAll('.cms-subnav .cms-nav-link'), function (link) {
        settingsSubnavElement.appendChild(link);
      });
      appearanceGroup.remove();
    }

    var appearanceLink = document.querySelector('.cms-sidebar .cms-nav-link[data-nav="appearance"]');
    if (settingsSubnavElement && appearanceLink) {
      var appearanceCaret = appearanceLink.querySelector('.cms-nav-caret');
      if (appearanceCaret) appearanceCaret.remove();
      appearanceLink.setAttribute('data-nav', 'theme-settings');
      appearanceLink.href = new URL('../settings/theme-settings.html', window.location.href).href;
      appearanceLink.removeAttribute('data-pending');
      var appearanceText = appearanceLink.querySelector('.cms-nav-text');
      if (appearanceText) appearanceText.textContent = 'Theme Settings';
      settingsSubnavElement.appendChild(appearanceLink);
    }

    var settingsToggleElement = settingsGroupElement && settingsGroupElement.querySelector('.cms-nav-toggle');
    if (settingsToggleElement) {
      if (!settingsToggleElement.querySelector('.cms-nav-caret')) {
        settingsToggleElement.appendChild(CMS.ui.el('span', { class: 'cms-nav-caret', 'aria-hidden': 'true' }));
      }
    }

    var subnavIcons = {
      'general-settings': 'gear',
      'website-information': 'globe',
      'theme-settings': 'palette',
      campuses: 'geo-alt',
      'content-categories': 'tags',
      'account-settings': 'person-gear',
      'security-settings': 'shield-lock',
      forms: 'ui-checks',
      'website-layout': 'columns-gap'
    };
    function addSubnavLink(groupName, label, page, href) {
      var subnav = document.querySelector('.cms-nav-group[data-group="' + groupName + '"] .cms-subnav');
      if (!subnav || subnav.querySelector('.cms-nav-link[data-nav="' + page + '"]')) return;
      var children = [];
      if (groupName !== 'settings') {
        children.push(CMS.ui.el('i', { class: 'bi bi-' + (subnavIcons[page] || 'gear'), 'aria-hidden': 'true' }));
      }
      children.push(CMS.ui.el('span', { class: 'cms-nav-text', text: label }));
      subnav.appendChild(CMS.ui.el('a', {
        class: 'cms-nav-link', href: new URL(href, window.location.href).href, 'data-nav': page
      }, children));
    }

    addSubnavLink('settings', 'General Settings', 'general-settings', '../system/system-gen.html');
    addSubnavLink('settings', 'Website Information', 'website-information', '../system/system-web-info.html');
    addSubnavLink('settings', 'Theme Settings', 'theme-settings', '../settings/theme-settings.html');
    addSubnavLink('news', 'News & Announcement Categories', 'content-categories', '../categories/content-categories.html');
    addSubnavLink('university', 'Campuses', 'campuses', '../campuses/campuses.html');
    addSubnavLink('settings', 'Account Settings', 'account-settings', '../settings/account-settings.html');
    addSubnavLink('settings', 'Security Settings', 'security-settings', '../settings/security-settings.html');
    addSubnavLink('settings', 'Forms', 'forms', '../contact/contact-form.html');
    if (settingsGroupElement) {
      [].forEach.call(settingsGroupElement.querySelectorAll('.bi'), function (icon) {
        icon.remove();
      });
    }
    if (settingsSubnavElement) {
      var settingsOrder = ['general-settings', 'website-information', 'theme-settings', 'website-layout', 'account-settings', 'security-settings', 'forms'];
      [].slice.call(settingsSubnavElement.querySelectorAll('.cms-nav-link'))
        .sort(function (a, b) {
          var aIndex = settingsOrder.indexOf(a.getAttribute('data-nav'));
          var bIndex = settingsOrder.indexOf(b.getAttribute('data-nav'));
          return (aIndex === -1 ? settingsOrder.length : aIndex) - (bIndex === -1 ? settingsOrder.length : bIndex);
        })
        .forEach(function (link) { settingsSubnavElement.appendChild(link); });
    }
    if (String(user.role || '').toLowerCase() !== 'admin') {
      var userManagementLink = document.querySelector('.cms-sidebar .cms-nav-link[data-nav="users"]');
      if (userManagementLink) userManagementLink.hidden = true;
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
      page = new URLSearchParams(window.location.search).get('type') === 'media' ? 'media' : 'documents';
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
