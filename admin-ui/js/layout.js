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

  function init() {
    [].forEach.call(document.querySelectorAll('.cms-nav-link[data-nav="faculty"][data-pending]'), function (link) {
      link.href = new URL('../faculty/index.html', window.location.href).href;
      link.removeAttribute('data-pending');
    });
    [
      ['documents', '../resources/library.html?type=documents'],
      ['media', '../resources/library.html?type=media']
    ].forEach(function (item) {
      [].forEach.call(document.querySelectorAll('.cms-nav-link[data-nav="' + item[0] + '"][data-pending]'), function (link) {
        link.href = new URL(item[1], window.location.href).href;
        link.removeAttribute('data-pending');
      });
    });

    function addSubnavLink(groupName, label, page, href) {
      var subnav = document.querySelector('.cms-nav-group[data-group="' + groupName + '"] .cms-subnav');
      if (!subnav || subnav.querySelector('.cms-nav-link[data-nav="' + page + '"]')) return;
      subnav.appendChild(CMS.ui.el('a', {
        class: 'cms-nav-link', href: new URL(href, window.location.href).href, 'data-nav': page
      }, [CMS.ui.el('span', { class: 'cms-nav-text', text: label })]));
    }

    addSubnavLink('news', 'News & Announcement Categories', 'content-categories', '../categories/content-categories.html');
    addSubnavLink('university', 'Campuses', 'campuses', '../campuses/campuses.html');
    addSubnavLink('settings', 'Forms', 'forms', '../contact/contact-form.html');

    var notificationButton = document.querySelector('.cms-notif');
    if (notificationButton) {
      notificationButton.removeAttribute('data-pending');
      var count = notificationButton.querySelector('.cms-badge-count');
      if (count) count.remove();
      notificationButton.addEventListener('click', function () {
        CMS.ui.showDetails({
          title: 'Notifications unavailable',
          rows: [['Status', 'The cms_db_2.sql schema has no notifications table or notification service to connect.']]
        });
      });
    }

    var accountButton = document.querySelector('.cms-user');
    if (accountButton) {
      accountButton.removeAttribute('data-pending');
      var accountName = accountButton.querySelector('.cms-user-name');
      if (accountName) accountName.textContent = 'No active session';
      accountButton.addEventListener('click', function () {
        CMS.ui.showDetails({
          title: 'Account unavailable',
          rows: [['Status', 'This prototype has no sign-in or authenticated session. The users table alone cannot identify a signed-in account.']]
        });
      });
    }

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

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();

  return { init: init };
})();
