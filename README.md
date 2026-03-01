# Domain Tracker
- Easily add, view, edit, and delete all of your domains, subdomains, and their associated mySQL databases.
- A lightweight PHP + SQLite web app for tracking domains, subdomains, file locations, and database credentials in one secure dashboard.

> 🔗 **Live demo:** [domainstracker-demo.blazehost.co](https://domainstracker-demo.blazehost.co) — *login: demo / demo*

## Features
- **Web-based installer** — Copy a single file to your server, run it in a browser, and complete a guided setup
- Secure login (single admin user)
- File-based storage with SQLite
- Encrypted database passwords at rest (AES-256-GCM)
- CRUD for domains and subdomains
- Registrar metadata and expiry tracking
- Expiry dashboard and scheduled expiry checks
- CSV import for migrating from spreadsheets
- Search across domains and subdomains
- Collapsible cards for easy navigation
- Clean, responsive UI

## Requirements
- PHP 8.1+ (recommended 8.2+) with PDO SQLite and OpenSSL extensions
- Apache (or equivalent) with PHP enabled
- Designed with shared hosting environments in mind

## Setup

### Option A: Web-based Installer (recommended)

1. Copy `installer/installer.php` to your server (e.g. `public_html/installer.php`).
2. Visit the installer URL in your browser (e.g. `https://your-site.com/installer.php`).
3. Follow the steps: requirements check → download from GitHub → configure admin credentials and `.env` → complete installation.
4. After install, point your domain's document root to the `public/` folder, or use the auto-created `index.php` redirect.

See `installer/README.md` for full details.

### Option B: Manual Setup

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

## Security
- **Authentication**: Session-based login with a single admin username and password hash from `.env`. Optional TOTP-based 2FA is supported, and failed attempts are rate-limited and stored in the `login_attempts` table (username, IP, user agent, reason).
- **Encryption**: Database passwords are encrypted at rest using AES-256-GCM with a key derived from `APP_KEY`. If `APP_KEY` is missing, database passwords are not saved.
- **Backups**: Backups are not automated by the app. Back up `data/app.db` and your `.env` file together, and keep `APP_KEY` safe so encrypted values can be decrypted later.
- **Key rotation**: Use the CLI script to re-encrypt all stored database passwords with a new key. From the project root, run `php scripts/rotate_key.php` (or `APP_KEY_NEW=your_new_key php scripts/rotate_key.php`), then update `APP_KEY` in your `.env` file. See the Security page for full instructions.
- **Logs and credentials**: The app logs login attempts only (no database credentials). Database usernames/passwords are never written to logs by the app.

### Key rotation

1. Back up `data/app.db` and `.env`.
2. Generate a new key: `php -r "echo bin2hex(random_bytes(32));"`
3. Run: `php scripts/rotate_key.php` (paste the new key when prompted) or `APP_KEY_NEW=your_new_key php scripts/rotate_key.php`.
4. Update `.env`: replace `APP_KEY` with the new key.
5. Remove any temporary `APP_KEY_NEW` from your environment.

## Security Notes
- Keep the web root pointed at `public/` so `.env`, `config/`, and `data/` are not exposed.
- The app includes `.htaccess` files to deny access to sensitive folders, but do not rely on this alone.

## Database Schema
The database is initialized automatically from `migrations/001_init.sql` on first run.

If you already have an existing `data/app.db`, you may need to add new columns manually when schema changes occur.

## Project Structure
```
installer/      Web-based installer (installer.php)
config/         App configuration
data/           SQLite database file
migrations/     Schema SQL
public/         Web root (index, login, assets, import template)
scripts/        CLI scripts (check_expiry, rotate_key)
src/            App logic (auth, crypto, repo)
views/          Templates
```

## Expiry Alerts

Domains can have an expiry date, registrar, renewal price, and auto-renew flag. The **Expiry** dashboard shows domains expiring in 7, 30, 60, and 90 days. Run the scheduled task from the project root to refresh or click the button to refresh:

```
php scripts/check_expiry.php
```

Add to cron (e.g. daily at 6am): `0 6 * * * cd /path/to/project && php scripts/check_expiry.php`

## CSV Import

Import domains and nested subdomains from a spreadsheet.

**Domains:** `type`=domain (or omit), `name`, `url`. Optional: description, registrar, expires_at, renewal_price, auto_renew, db_host, db_port, db_name, db_user, db_password.

**Subdomains:** `type`=subdomain, `parent_domain` (domain name or url), `name`, `url`, `file_location`. Optional: description, db_host, db_port, db_name, db_user, db_password.

Put domain rows before their subdomains. Date format: `YYYY-MM-DD` or `MM/DD/YYYY`. Auto-renew: `1`/`0` or `yes`/`no`.

Download the template from the Import page or see `public/assets/import.csv`.

## Usage Tips
- Use the dashboard search to find domains or subdomains quickly.
- Collapse domain and subdomain cards to reduce clutter.
- Hover truncated file paths to see the full path.

## License
GPLv3 (see `LICENSE`).

<img width="1913" height="912" alt="login_page" src="https://github.com/user-attachments/assets/a622e0c4-4103-473a-a952-3b8d302f26ea" />
<img width="1919" height="909" alt="main_dashboard_collapsed_all" src="https://github.com/user-attachments/assets/c43ec6d3-18c1-4350-aea7-f5fbce1d743e" />
<img width="1916" height="909" alt="main_dashboard_collapsed_sds" src="https://github.com/user-attachments/assets/080f56b4-4c0b-4c7b-8953-696b4a3e7b1e" />
<img width="1917" height="908" alt="main_dashboard_expanded" src="https://github.com/user-attachments/assets/d43f47c6-a6c6-4504-b023-b260bb8b7e94" />
<img width="1918" height="910" alt="new_domain" src="https://github.com/user-attachments/assets/8de19189-3db8-4d6e-a8ba-5e35a8ebf906" />
<img width="1917" height="908" alt="new_subdomain" src="https://github.com/user-attachments/assets/6e8ed3ae-7210-457e-8721-fa563c8b7de2" />
<img width="1919" height="907" alt="edit_domain" src="https://github.com/user-attachments/assets/1d468090-f053-48fd-a2de-cbcaca73117e" />
<img width="1918" height="907" alt="edit_subdomain" src="https://github.com/user-attachments/assets/9f848542-bfd6-49a9-87a0-8c30342e1989" />
<img width="449" height="145" alt="prevent_polulated_domain_deletion" src="https://github.com/user-attachments/assets/ff2e492a-a36e-4525-bf80-532d9d76f041" />
<img width="450" height="142" alt="delete_subdomain_confirmation" src="https://github.com/user-attachments/assets/9334b400-87d0-4443-8230-b6f3a0134a0b" />
<img width="449" height="147" alt="unsaved_changes_confirmation" src="https://github.com/user-attachments/assets/c18f47f2-c820-4c69-ad5a-1a6607fa7ff2" />
<img width="645" height="1109" alt="mobile_dashboard" src="https://github.com/user-attachments/assets/e414ced6-4949-4479-8b06-29a00634535e" />
<img width="645" height="1272" alt="mobile_subdomains" src="https://github.com/user-attachments/assets/da788f0b-b83c-42a3-bb73-023a6fc945ba" />
<img width="645" height="1272" alt="mobile_subdomain_exanded" src="https://github.com/user-attachments/assets/463457aa-b77a-47c8-aff8-b19d1e26387f" />
<img width="645" height="1263" alt="mobile_edit_subdomain" src="https://github.com/user-attachments/assets/88f71365-d7f5-4847-8778-b834d0514bf4" />
