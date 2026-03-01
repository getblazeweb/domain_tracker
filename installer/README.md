# Domain Tracker Web-based Installer

A standalone web-based installer that downloads Domain Tracker from GitHub, configures admin credentials and `.env`, and sets up the app.

## Usage

1. **Copy** `installer.php` to your server (e.g. `public_html/installer.php` or your document root).
2. **Visit** the installer URL in your browser (e.g. `https://your-site.com/installer.php`).
3. **Follow the steps:**
   - Step 1: Requirements check — verify PHP 8.1+, required extensions, and writable directory.
   - Step 2: Click "Download and extract" — fetches the app from GitHub and extracts to the same directory.
   - Step 3: Configure admin username, password, APP_KEY (or use Demo mode).
   - Step 4: Submit — writes `.env`, runs migrations, creates `index.php` redirect, and completes setup.
4. **Success** — You'll see a link to the app and instructions for pointing your document root to the `public/` folder.

## Requirements

- PHP 8.1+
- Extensions: `pdo_sqlite`, `openssl`, `zip`
- `curl` or `allow_url_fopen` (for download)
- Writable install directory

## Install Path

The app installs to the **directory containing** `installer.php`. For example:

- If you create `public_html/installer.php` → app installs to `public_html/` (creates `public_html/public/`, `public_html/src/`, etc.).

## After Install

- An `index.php` redirect is created at the install root so your site works immediately.
- For production, point your domain's document root to the `public/` subfolder for cleaner URLs.
- The installer creates `install.lock` on success. To reinstall, delete this file.

## Security

- Use a CSRF token for form submissions.
- Do not expose `.env` or `APP_KEY` in HTML/JS.
- After install, consider deleting or protecting the installer directory.
