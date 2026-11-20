/* ==========================================================================
   dashboard.js — fills the Dashboard from GET /api/dashboard.
   Cards, chart, recent content and recent activity all come from cms_db.
   Everything is inserted with text nodes / setAttribute (never innerHTML).
   ========================================================================== */
(function () {
  var ui = CMS.ui, el = ui.el;
  var SVG_NS = 'http://www.w3.org/2000/svg';
  var MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  /* bottom-to-top stacking order; matches the .series-* classes in dashboard.css */
  var SERIES = [
    { key: 'news', label: 'News' },
    { key: 'announcements', label: 'Announcements' },
    { key: 'events', label: 'Events' },
    { key: 'documents', label: 'Documents' },
    { key: 'other', label: 'Other' }
  ];
  /* chart geometry taken from the existing static SVG (viewBox 960x240) */
  var BASE_Y = 205, MAX_HEIGHT = 150, COL_STEP = 80, COL_START = 40, COL_WIDTH = 36;

  var TYPES = {
    news:         { label: 'News',         href: '../news/all-news.html' },
    announcement: { label: 'Announcement', href: '../announcements/all-announcements.html' },
    event:        { label: 'Event',        href: '../events/all-events.html' }
  };

  function svg(tag, attrs, children) {
    var node = document.createElementNS(SVG_NS, tag);
    Object.keys(attrs || {}).forEach(function (k) {
      if (k === 'text') node.textContent = attrs[k]; else node.setAttribute(k, attrs[k]);
    });
    [].concat(children || []).forEach(function (c) { node.appendChild(c); });
    return node;
  }

  function shortDate(iso) {
    var m = /^(\d{4})-(\d{2})-(\d{2})/.exec(iso || '');
    return m ? MONTHS[parseInt(m[2], 10) - 1] + ' ' + parseInt(m[3], 10) : '';
  }

  function ago(seconds, day) {
    if (seconds < 60) return 'Just now';
    if (seconds < 3600) return Math.floor(seconds / 60) + ' min ago';
    if (seconds < 86400) return Math.floor(seconds / 3600) + ' hr ago';
    if (seconds < 172800) return 'Yesterday';
    return shortDate(day);
  }

  function plural(n, word) { return n + ' ' + word + (n === 1 ? '' : 's'); }

  /* ---------- summary cards ---------- */
  function renderCards(cards) {
    document.querySelectorAll('[data-metric]').forEach(function (node) {
      var value = cards[node.getAttribute('data-metric')];
      node.textContent = typeof value === 'number' ? value.toLocaleString('en-US') : '\u2014';
    });
  }

  /* ---------- stacked monthly chart ---------- */
  function renderChart(chart) {
    var columns = document.getElementById('activityColumns');
    var totalsRow = document.getElementById('activityTotals');
    var subtitle = document.getElementById('chartSubtitle');
    ui.clear(columns); ui.clear(totalsRow);
    if (subtitle) subtitle.textContent = 'Items published in ' + chart.year;

    var totals = MONTHS.map(function (_, i) {
      return SERIES.reduce(function (sum, s) { return sum + ((chart.series[s.key] || [])[i] || 0); }, 0);
    });
    var scaleMax = Math.max(4, Math.max.apply(null, totals));   // keep tiny counts from filling the whole plot
    var unit = MAX_HEIGHT / scaleMax;
    var latest = -1;
    totals.forEach(function (t, i) { if (t > 0) latest = i; });

    MONTHS.forEach(function (month, i) {
      var cx = COL_START + COL_STEP * i;
      var group = svg('g', { 'class': 'chart-column' }, [svg('text', { 'class': 'chart-month-label', x: cx, y: 225, text: month })]);
      var top = BASE_Y;
      SERIES.forEach(function (s) {
        var count = (chart.series[s.key] || [])[i] || 0;
        if (!count) return;
        var height = count * unit;
        top -= height;
        group.appendChild(svg('rect', {
          'class': 'series-' + s.key, x: cx - COL_WIDTH / 2, y: top.toFixed(2), width: COL_WIDTH, height: height.toFixed(2)
        }, [svg('title', { text: s.label + ' \u2013 ' + month + ': ' + count })]));
      });
      columns.appendChild(group);

      var cell = el('td');
      if (totals[i] > 0) cell.appendChild(el('span', { 'class': 'chart-total' + (i === latest ? ' latest-total' : ''), text: plural(totals[i], 'item') }));
      totalsRow.appendChild(cell);
    });
  }

  /* ---------- recently published ---------- */
  function renderPublished(items) {
    var list = document.getElementById('recentPublished');
    ui.clear(list);
    if (!items.length) { list.appendChild(el('li', { 'class': 'dashboard-empty', text: 'Nothing has been published yet.' })); return; }
    items.forEach(function (item) {
      var type = TYPES[item.type] || { label: item.type, href: '#' };
      var thumb = el('span', { 'class': 'content-thumbnail', 'aria-hidden': 'true' });
      if (item.thumbnail) {
        var img = el('img', { 'class': 'content-thumbnail', src: item.thumbnail, alt: '', loading: 'lazy' });
        img.addEventListener('error', function () { img.replaceWith(el('span', { 'class': 'content-thumbnail', 'aria-hidden': 'true' })); });
        thumb = img;
      }
      list.appendChild(el('li', {}, [
        el('a', { 'class': 'published-item', href: type.href }, [
          thumb,
          el('span', { 'class': 'published-copy' }, [
            el('strong', {}, [el('span', { text: item.title }), el('span', { 'class': 'published-status', text: 'Published' })]),
            el('span', {}, [type.label + ' ', el('span', { 'class': 'content-date', text: '\u00b7 ' + shortDate(item.date) })])
          ])
        ])
      ]));
    });
  }

  /* ---------- recent activity ---------- */
  function renderActivity(rows) {
    var body = document.getElementById('recentActivity');
    ui.clear(body);
    if (!rows.length) {
      body.appendChild(el('tr', {}, [el('td', { colspan: 4, 'class': 'dashboard-empty', text: 'No recent activity.' })]));
      return;
    }
    rows.forEach(function (row) {
      body.appendChild(el('tr', {}, [
        el('td', { text: row.user || 'Unknown user' }),
        el('td', { text: row.activity }),
        el('td', { text: row.content }),
        el('td', {}, [el('time', { datetime: row.date, text: ago(row.ageSeconds, row.date) })])
      ]));
    });
  }

  function renderFailure(error) {
    renderCards({});
    renderPublished([]);
    renderActivity([]);
    renderChart({ year: new Date().getFullYear(), series: {} });
    ui.toast('Could not load the dashboard: ' + error.message, 'error');
  }

  CMS.api.get('/dashboard').then(function (data) {
    renderCards(data.cards || {});
    renderChart(data.chart || { year: new Date().getFullYear(), series: {} });
    renderPublished(data.recentPublished || []);
    renderActivity(data.recentActivity || []);
  }).catch(renderFailure);
})();
