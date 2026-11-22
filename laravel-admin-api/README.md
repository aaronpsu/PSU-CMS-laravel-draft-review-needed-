## PSU CMS local API

This Laravel API connects the News, Announcements, Events, Event Categories, News/Announcement Categories, Homepage Management, Contact & Feedback, Campuses, Colleges, Departments, Academic Programs, Users/User Roles, Document Library, Media Library, and Faculty & Staff Directory screens to the `psu_cms_admin` database defined in `psu_cms_admin.sql`. Search, filters, and CRUD actions for those screens use MySQL.

### Setup

1. Import `..\psu_cms_admin.sql` into MySQL if its tables are not already present.
2. Copy `.env.example` to `.env` and set your local `psu_cms_admin` credentials.
3. Run `composer install`, `php artisan key:generate`, and `php artisan migrate`.
4. From this folder, run `php artisan serve --host 127.0.0.1 --port 8001`.
5. In another terminal at the repository root, run `php -S 127.0.0.1:8000`.
6. Open `http://127.0.0.1:8000/admin-ui/`.

Admin API routes are restricted to loopback requests in the `local` or `testing` environment. They require a signed-in `admin` or `editor`; user management and site settings require `admin`. Mutations require JSON, a matching session CSRF token, a request body no larger than 8 MiB, and reject HTTP method overrides. The public homepage-data endpoint is intentionally unauthenticated. The API is still a local prototype: do not expose it to a network or deploy it.

Back up the database before running migrations. The initial Laravel migration preserves pre-existing `users` and `password_reset_tokens` tables and does not drop them on rollback; it creates those tables only when absent. The compatibility migration adapts the imported schema for the admin API; it does not reset tables. Use an application-specific database account with only required privileges.

Session cookies are `HttpOnly` and `SameSite=Lax`; secure cookies default on in production/staging and off in local development so HTTP localhost sessions work. Image uploads require GD, are dimension-limited, and are decoded and re-encoded before storage. Document uploads are limited to 300 KB; file contents are checked against the selected extension, and Office Open XML packages are validated using PHP ZIP support before they are stored privately. Failed record transactions remove newly stored files.

The root PHP site rejects non-local requests when its ignored `config.php` uses the MySQL `root` account or a blank database password. Replace those local credentials with a dedicated account before allowing non-local access; do not commit or share the local config file.

The test suite can run with `php artisan test` from this directory. Live database persistence and deployment behavior still require validation against the intended MySQL server and hosting configuration.

### Connected screens

- News: keyword, category, status, date, and campus filters; create, edit, view, and delete.
- Announcements: keyword, category, status, date, and campus filters; create, edit, view, and delete.
- Events: keyword, category, status, and date filters; create, edit, view, and delete.
- Event Categories: create, rename, and delete categories used by Events.
- News and Announcement Categories: separate tables are intentional. News uses `news_category_map`; Announcements use their own `category_id` foreign key. Deletion is blocked while content uses a category.
- Campuses: search, status filter, create, edit, and delete; deletion is blocked while News or Announcements refer to the campus.
- Document Library: search, upload (up to 300 KB), rename, download, and delete; deletion is blocked while an announcement uses the document.
- Media Library: search, image upload (up to 5 MB), edit alt text, preview, and delete; deletion is blocked while News, Events, or Colleges reference the image.
- Faculty & Staff Directory: keyword and department filters; create, edit, view, and delete.
- Departments: keyword, college, and status filters; create, edit, view, and delete. Deletion is blocked while academic programs reference the department.
- Colleges: keyword and status filters; create, edit, view, and delete. Deletion is blocked while departments or programs reference the college.
- Academic Programs: keyword, college, degree-level, and status filters; create, edit, view, and delete. A program's department must belong to its selected college.
- Users: keyword, role, and status filters; create, edit, view, and delete. New passwords are hashed by Laravel; existing passwords are never returned by the API.
- User Roles: role labels and user counts come from assigned users; rename applies to all users assigned that label. The API does not expose permission configuration.
- Homepage Management: banner images, homepage content, and featured items use the `homepage_*` tables from the homepage migration.
- Contact & Feedback: the public form writes to `contact_submissions` or `feedback_submissions` with server-side validation and throttling; the admin inbox reads, updates, replies to, and deletes those records.
- General Settings and Website Information: read and update supported keys in `site_settings`; website identity, description, and uploaded logo are reflected on the root public site.

### Schema limitations

- News and Announcements support `draft`, `published`, and `archived`; News `pending review` is not present in the schema.
- Announcements have no author-name field; the schema only permits an optional `created_by` user reference.
- Event form actions create `draft` or `published` records. The schema also has `cancelled`, `completed`, and `archived` states, but the prototype has no controls to set those states.
- `users.role_id` references `roles`; initial administrator setup stores that foreign key without a duplicate text role. The nullable `users.role` compatibility label remains for existing users and management screens. API authorization is still limited to `admin` and `editor` behavior and is not a production permissions model.
- The `notifications` table stores per-user alerts. The admin header loads up to 20 newest alerts for the signed-in user and displays the unread count; selecting an unread alert marks it as read.
- Site settings are restricted to the keys represented by the General Settings and Website Information forms. Logo uploads are stored in the shared Media table and served by the root site's `media.php` endpoint.
- The SQL seed users contain demo values in `password_hash`; they are not usable password hashes. If no active administrator exists, open the sign-in page and use the **Create Initial Administrator** form. The setup endpoint is available only on loopback and refuses to run after an active administrator exists. The local API provides session-based sign-in but is not suitable for production use.
- The database supports only the fields now shown in Colleges and Academic Programs; unsupported prototype fields such as vision, contact number, campus, admission criteria, and syllabus uploads are omitted rather than presented as saved data.
- The existing root `admin.php` uses `content`, `admins`, and `feedback` tables, which are not part of `cms_db_2.sql`; it is intentionally left unchanged.

<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
