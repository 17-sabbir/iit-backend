# IIT Shelf Laravel API

The backend is a Laravel 12 application. API requests are handled by Laravel controllers, requests, middleware, services, and Eloquent/query-builder code. The former `api/` PHP endpoint tree and dispatcher have been removed. Existing Flutter-compatible `.php` URL suffixes are Laravel route aliases only; no PHP endpoint scripts execute.

## Requirements

- PHP 8.2+ with PDO MySQL, fileinfo, and OpenSSL extensions
- Composer
- MariaDB/MySQL
- Flutter/Dart for the client

## Configure

Copy `.env.example` to `.env`, set `APP_KEY`, and configure the main and auxiliary database connections:

- `DB_*` connects to `iit_shelf`
- `PREREG_DB_*` connects to `iit_shelf_prereg`
- `AUTH_TEMP_DB_*` connects to `iit_shelf_auth_temp`

The preregistration database stores approved registration identities and library settings. The auth-temp database stores OTPs. The connection defaults use local development credentials; configure real credentials locally and do not commit them.

For a fresh database, review the Laravel migrations and run `php artisan migrate` deliberately. Do not run migrations against an existing database without a backup and schema review. The application does not migrate the database automatically at startup.

Set `MAIL_MAILER=log` for local development. Use a configured Laravel mail transport for real delivery; external mail is not exercised by the test suite.

## Start

```bash
composer install
php artisan key:generate
php artisan serve --host=0.0.0.0 --port=8000
```

Flutter uses the shared API client. Configure `API_BASE_URL` at build time when the default emulator/web host is not correct, for example `--dart-define=API_BASE_URL=http://192.168.1.20:8000`.

## Validate

```bash
php artisan test
flutter analyze
```

Database-backed tests use transaction rollback. They require the main, preregistration, and auth-temp databases and their expected tables. Upload tests should use temporary files; never clear the existing `uploads/` directory as part of testing.

Auxiliary schemas can be created with the idempotent SQL files under `database/` and `setup_prereg_database.sql`. These scripts create empty tables/default library settings; they do not seed preregistered identities. Add authorized identities through the institution's approved data process.

## Structure

- `app/Http/Controllers`: Laravel endpoint behavior
- `app/Http/Requests`: validation
- `app/Http/Middleware`: bearer authentication, role, permission, and owner checks
- `app/Services`: authentication, token, catalog, and borrow workflows
- `routes/api.php`: Laravel API routes
- `database/migrations`: Laravel schema migrations
- `database/seeders`: Laravel seeders
- `public/index.php`: Laravel front controller
- `uploads/`: existing user-uploaded files; do not delete during cleanup
