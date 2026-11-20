## PSU CMS local API

This Laravel API connects the News, Announcements, Events, Event Categories, News/Announcement Categories, Campuses, Colleges, Departments, Academic Programs, Users/User Roles, Document Library, Media Library, and Faculty & Staff Directory screens to the repository's `cms_db` database defined in `cms_db_2.sql`. Search, filters, and CRUD actions for those screens use MySQL.

### Setup

1. Import `..\cms_db_2.sql` into MySQL if its tables are not already present.
2. Copy `.env.example` to `.env` and set your local `cms_db` credentials.
3. Run `composer install` and `php artisan key:generate`.
4. From this folder, run `php artisan serve --host 127.0.0.1 --port 8001`.
5. In another terminal at the repository root, run `php -S localhost:8000`.
6. Open `http://localhost:8000/admin-ui/`.

The API is deliberately restricted to local requests in the `local` environment. Do not expose this unauthenticated prototype server to a network or deploy it. Add authentication and authorization before any shared or production deployment.

The API does not create, migrate, or reset database tables.

### Connected screens

- News: keyword, category, status, date, and campus filters; create, edit, view, and delete.
- Announcements: keyword, category, status, date, and campus filters; create, edit, view, and delete.
- Events: keyword, category, status, and date filters; create, edit, view, and delete.
- Event Categories: create, rename, and delete categories used by Events.
- News and Announcement Categories: search, create, rename, and delete; deletion is blocked while content uses a category.
- Campuses: search, status filter, create, edit, and delete; deletion is blocked while News or Announcements refer to the campus.
- Document Library: search, upload (up to 300 KB), rename, download, and delete; deletion is blocked while an announcement uses the document.
- Media Library: search, image upload (up to 5 MB), edit alt text, preview, and delete; deletion is blocked while News, Events, or Colleges reference the image.
- Faculty & Staff Directory: keyword and department filters; create, edit, view, and delete.
- Departments: keyword, college, and status filters; create, edit, view, and delete. Deletion is blocked while academic programs reference the department.
- Colleges: keyword and status filters; create, edit, view, and delete. Deletion is blocked while departments or programs reference the college.
- Academic Programs: keyword, college, degree-level, and status filters; create, edit, view, and delete. A program's department must belong to its selected college.
- Users: keyword, role, and status filters; create, edit, view, and delete. New passwords are hashed by Laravel; existing passwords are never returned by the API.
- User Roles: role labels and user counts derived from `users.role`; rename applies to all users assigned that role. Independent roles and permissions are not in the schema.

### Schema limitations

- News and Announcements support `draft`, `published`, and `archived`; News `pending review` is not present in the schema.
- Announcements have no author-name field; the schema only permits an optional `created_by` user reference.
- Event form actions create `draft` or `published` records. The schema also has `cancelled`, `completed`, and `archived` states, but the prototype has no controls to set those states.
- `users.role` is a plain label, not a permissions model; there is no roles table, username, last-login field, or user profile image field. The prototype has no login/session system or authorization checks, so User Management is not suitable for managing real administrator access.
- There is no notifications table or authenticated session in this schema/prototype. The header controls report those limitations and do not display fabricated alerts or a fake signed-in identity.
- The SQL seed users contain demo values in `password_hash`; they are not usable password hashes. Newly created users do receive Laravel password hashes, but this prototype does not implement sign-in.
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
