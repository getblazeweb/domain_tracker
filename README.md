# Domain Tracker
- Easily add, view, edit, and delete all of your domains, subdomains, and their associated mySQL databases.
- A lightweight PHP + SQLite web app for tracking domains, subdomains, file locations, and database credentials in one secure dashboard.

## Features
- Secure login (single admin user)
- File-based storage with SQLite
- Encrypted database passwords at rest (AES-256-GCM)
- CRUD for domains and subdomains
- Search across domains and subdomains
- Collapsible cards for easy navigation
- Clean, responsive UI

## Requirements
- PHP 8.1+ (recommended 8.2+) with PDO SQLite and OpenSSL extensions
- Apache (or equivalent) with PHP enabled
- Designed with shared hosting environments in mind

## Setup
1. Upload the project to your server.
2. Set your web root to the `public/` directory.
3. Create a `.env` file in the project root (same level as `public/`, `src/`, `config/`).

Example `.env`:
```
APP_KEY=change_me_to_32+_chars
ADMIN_USERNAME=admin
ADMIN_PASSWORD_HASH=replace_with_password_hash
```

Generate values:
```
php -r "echo bin2hex(random_bytes(32));"
php -r "echo password_hash('your-password', PASSWORD_DEFAULT);"
```

Then visit `/login.php` to sign in. The SQLite database will be created automatically at `data/app.db`.

## Security Notes
- Keep the web root pointed at `public/` so `.env`, `config/`, and `data/` are not exposed.
- The app includes `.htaccess` files to deny access to sensitive folders, but do not rely on this alone.
- Database passwords are encrypted using `APP_KEY`. If `APP_KEY` is missing, passwords will not be saved.

## Database Schema
The database is initialized automatically from `migrations/001_init.sql` on first run.

If you already have an existing `data/app.db`, you may need to add new columns manually when schema changes occur.

## Project Structure
```
config/         App configuration
data/           SQLite database file
migrations/     Schema SQL
public/         Web root (index, login, assets)
src/            App logic (auth, crypto, repo)
views/          Templates
```

## Usage Tips
- Use the dashboard search to find domains or subdomains quickly.
- Collapse domain and subdomain cards to reduce clutter.
- Hover truncated file paths to see the full path.

## License
GPLv3 (see `LICENSE`).
