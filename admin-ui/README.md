# admin-ui — CMS front-end prototype

Static HTML / CSS / vanilla JS / Bootstrap 5 screens for the PSU CMS admin panel.
News, Announcements, Events, Event Categories, Faculty & Staff Directory,
Colleges, Departments, Academic Programs, and Users use the local Laravel API
and the `psu_cms_admin` database from `psu_cms_admin.sql`. Documents, Media, Campuses, and
News/Announcement Categories, Homepage Management, and Contact & Feedback are
also managed through Laravel. Their keyword and available dropdown filters run
against that database. Other prototype screens still use `localStorage`.

## Run it
Run the API from `laravel-admin-api` using `php artisan serve --host 127.0.0.1 --port 8001`.
In a second terminal at the repo root, run `php -S localhost:8000` and visit
`http://localhost:8000/admin-ui/`. See [laravel-admin-api/README.md](../laravel-admin-api/README.md)
for database setup and API details.

## Folder map
| Path | Purpose |
|---|---|
| `news/`, `announcements/`, `events/`, `search/` | One HTML file per screen |
| `css/cms-*.css` | Shared design tokens, layout and components |
| `css/news.css` etc. | Module styles (class prefixes `news-`, `announcement-`, `event-`, `search-`) |
| `js/config.js` | Feature flags, campus list, categories, page sizes, upload limits |
| `js/seed-data.js` | Sample data (first run only; never overwrites saved data) |
| `js/storage.js` | Repositories: `CMS.db.news.list() / get(id) / save(record) / remove(id)` |
| `js/sanitize.js` | Allow-list HTML sanitizer for rich text |
| `js/richtext.js` | Rich-text editor (bold, italic, underline, lists, link, image) |
| `js/announcement-rules.js` | Expiry / active / campus-visibility rules (also used by Search) |
| `js/event-rules.js` | Upcoming / Ongoing / Completed status and category lookups (also used by Search) |
| `js/search-sources.js`, `js/search.js` | Global Search: one entry per content type (add or replace sources here), plus the page logic |
| `js/news.js`, `js/announcements.js`, `js/events.js`, `js/categories.js` | Page logic for each module |
| `resources/library.html`, `js/resource-library.js` | Laravel-backed Document and Media Libraries |
| `campuses/`, `js/campuses.js` | Laravel-backed campus management |
| `categories/content-categories.html`, `js/content-categories.js` | Laravel-backed News and Announcement category management |
| `faculty/`, `js/faculty.js` | Faculty & Staff Directory connected to the Laravel API |
| `js/ui.js` | Toasts, confirm/detail modals, pagination, custom selects, highlight, image reader |
| `js/layout.js` | Sidebar/header behavior, current-database notifications, and the configured public-site link |
| `js/message-store.js`, `js/contact.js` | Database-backed Contact & Feedback submission and inbox actions |
| `vendor/` | Bootstrap 5.3.3, Bootstrap Icons 1.11.3, Inter font — **local on purpose** (see CSP) |

## Why Bootstrap is vendored
`bootstrap.php` sends `script-src 'self'; style-src 'self'`, so CDN files and inline
scripts/styles are blocked when pages are served through PHP. Rules used here:
no inline `<script>`, `onclick=""` or `style=""`; no `blob:` URLs (images use data URLs).

## Testing helpers (browser console)
- `CMS.dev.clearEvents()` then reload All Events → empty state
- `CMS.dev.resetAll()` → restore the sample data
- `CMS.dev.clearAll()` → remove all data

## Optional features
`CMS.config.features` in `js/config.js`: `campus` and `priority`. Set to `false` to remove them;
the layout falls back to the original screenshots.

## Remaining prototype screens

Global Search still searches browser-storage sample data rather than Laravel data.
Dashboard remains a placeholder. Homepage Management uses the `homepage_*`
tables created by its Laravel migration. Contact & Feedback uses
`contact_submissions` and `feedback_submissions`; public form submissions are
server-validated and displayed in the admin inbox. General Settings and Website
Information load from and save to the `site_settings` table; the public site's
name and logo use those saved settings. The root public homepage also reads
`homepage_settings`, active banners, and featured items from Homepage Management.
Theme preferences remain local to the browser.
Account sign-in and Security Settings use the local session-authenticated
Laravel API.
The shared header loads the signed-in user's notifications from the
`notifications` table in `psu_cms_admin`; it shows an empty state when that user
has no notification records. The public View Website link uses the root PHP
homepage, which reads published records from the current `psu_cms_admin`
database.

## Converting other views to Laravel later
- Shared sidebar/header HTML is inside `<!-- BEGIN SHARED -->` / `<!-- END SHARED -->` comments in every page →
  move into `partials/sidebar.php` / `partials/header.php` and `include` them.
- `<body data-page="...">` picks the active sidebar link → make it a PHP variable.
- Rename `.html` → `.php` and update links (single pattern).
- Replace the bodies of `read()` / `write()` in `js/storage.js` (or the repositories) with calls to PHP endpoints.
- `js/sanitize.js` is only a front-end safeguard — sanitize and escape on the server too.
- The legacy `content` table used by the original PHP pages has no author, category,
  expiration, campus, or priority fields; the v2 schema/API is separate.
- Add CSRF tokens to forms (marked with `<!-- PHP: ... -->` comments).

## University Structure & User Management (Member 3)
Screens: `colleges/`, `departments/`, `programs/`, `users/` (9 screens, matching the Member 3 design document).

| File | Purpose |
| --- | --- |
| `js/structure-seed.js` | First-run sample data (colleges, departments, programs, roles, users, extra faculty). |
| `js/structure-db.js` | Adds `CMS.db.colleges / departments / programs / roles / users` and the shared helpers in `CMS.structure` for the still-local prototype screens. Does **not** edit `storage.js`. |
| `js/colleges.js`, `programs.js`, `users.js` | College, Academic Program, and User pages connected to Laravel. |
| `js/roles.js` | Role labels and user counts derived from `users.role`; renaming updates the label on all assigned users. |
| `js/departments.js` | Departments list and form connected to Laravel; uses database colleges and faculty for its selectors. |
| `js/combobox.js` + `css/cms-combobox.css` | Reusable searchable dropdown (used for Department Head). Any module may reuse it. |
| `css/structure.css`, `colleges.css`, `departments.css`, `programs.css`, `users.css` | Styles (prefixes `structure-`, `college-`, `department-`, `program-`, `user-`, `role-`). |

The Colleges, Departments, Programs, Campuses, and content categories use database-backed records and relationships. Users store a role label directly; the User Roles screen derives roles from current users and can rename a role for all users assigned to it.

Notes for the team:
- The sidebar groups **University Structure** and **User Management** replaced the two placeholder links in every page.
- These API-backed pages no longer save or display their localStorage sample records.
- New user passwords are hashed by Laravel and never returned. The API uses a local session-based sign-in; it is still a prototype and should not be exposed or used for real administrator access without a production security review.
- College logos are uploaded to the schema's Media table. Fields unsupported by the v2 schema (such as college vision or program campus/syllabus) are omitted.
- The User Roles page cannot create independent roles or configure permissions because the schema has no roles or permissions table.
- The shared header reads per-user notifications and unread counts from `psu_cms_admin.notifications`; unread alerts can be marked as read. Account and Security Settings use the signed-in Laravel session. API mutations include the session CSRF token and are role-protected; the backend remains loopback-only for local development.
- Document Library supports uploads up to 300 KB (matching the announcement attachment API); Media Library supports image uploads up to 5 MB.

## Git
Never push to `main`. Work on `feature/news-announcements-events-search`, push that branch, and open a pull request.
