/* ==========================================================================
   config.js — one place for settings the whole team may need to change.
   Loaded first on every page. Everything lives under the global `CMS` object
   (plain scripts, no modules, so pages also work when opened from a folder).
   ========================================================================== */
window.CMS = window.CMS || {};

CMS.config = {
  /* All localStorage keys start with this prefix (avoids clashing with teammates). */
  storagePrefix: 'psucms.',
  apiBase: (window.location.protocol === 'http:' || window.location.protocol === 'https:'
    ? window.location.protocol : 'http:') + '//' + (window.location.hostname || '127.0.0.1') + ':8001/api',

  /* ---- Optional features -------------------------------------------------
     Waiting on the UI team. Set a flag to false and the related fields,
     filters and badges are removed; the layout falls back to the screenshots. */
  features: {
    campus: true,    // campus targeting (news + announcements)
    priority: true   // Normal / Urgent priority (announcements)
  },

  /* ---- Campuses (EDIT ME: confirm the real list with the team) ---------- */
  allCampuses: 'All Campuses',
  campuses: ['Lingayen', 'Urdaneta', 'Bayambang', 'Alaminos City', 'Asingan', 'Binmaley', 'Infanta', 'San Carlos City', 'Santa Maria'],

  currentUser: null,

  /* ---- Fixed dropdown lists (the designs show no screen to manage these) - */
  newsCategories: ['Campus News', 'Academics', 'Research', 'Sports', 'Events', 'Community'],
  announcementCategories: ['Academic', 'Advisory', 'Scholarship', 'Administrative'],
  feedbackCategories: ['Website', 'Services', 'Facilities', 'Other'],

  /* ---- Status lists. `value` is what is stored, `label` is what is shown. */
  newsStatuses: [
    { value: 'draft', label: 'Draft' },
    { value: 'published', label: 'Published' },
    { value: 'archived', label: 'Archived' }
  ],
  announcementStatuses: [
    { value: 'draft', label: 'Draft' },
    { value: 'published', label: 'Published' },
    { value: 'archived', label: 'Archived' }
    /* 'expired' is derived from the expiration date. */
  ],
  eventStates: [
    { value: 'draft', label: 'Draft' },
    { value: 'published', label: 'Published' }
    /* Upcoming / Ongoing / Completed are worked out from the event dates. */
  ],

  /* ---- Rows per page (taken from the screenshots) ----------------------- */
  pageSize: { news: 5, announcements: 4, events: 4, categories: 4 },

  /* ---- Uploads ----------------------------------------------------------- */
  imageMaxFileBytes: 5 * 1024 * 1024,   // reject files bigger than this
  imageMaxWidth: 1200,                  // pictures are scaled down before saving
  imageQuality: 0.82,                   // JPEG quality used when scaling
  documentMaxFileBytes: 300 * 1024, // matches the server attachment limit

  /* ---- Links (relative to a page inside admin-ui/<module>/) ------------- */
  publicSiteUrl: '../../index.php',
  searchPage: '../search/search-results.html'
};
