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
