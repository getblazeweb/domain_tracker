# Security Policy

## Supported Versions

| Version | Supported          |
| ------- | ------------------ |
| main    | :white_check_mark: |
| < main  | :x:                |

Only the latest `main` branch receives security updates. Upgrade to the latest release if you are on an older version.

## Reporting a Vulnerability

**Please do not report security vulnerabilities through public GitHub issues.**

If you believe you have found a security vulnerability in Domain Tracker, please report it responsibly:

1. **Email** the maintainers privately with a clear description of the issue, steps to reproduce, and potential impact at: [support@blazehost.co](support@blazehost.co)
2. **Include** the following information:
   - Affected component (e.g. installer, updater, login, crypto)
   - Type of vulnerability (e.g. XSS, CSRF bypass, SQL injection, information disclosure)
   - Proof-of-concept or reproduction steps (if applicable)
   - Suggested fix (optional)
3. **Allow** up to 90 days for the maintainers to address the issue before any public disclosure.

### What to Expect

- **Acknowledgment**: A response within 7 days confirming receipt.
- **Assessment**: A preliminary assessment within 14 days.
- **Fix**: A patch or mitigation within 90 days, depending on severity.
- **Disclosure**: Coordinated disclosure after a fix is available. Reporters are credited (unless they prefer anonymity).

### Out of Scope

- Issues in demo mode (public demo/demo login is intended for testing only)
- Issues requiring physical access to the server or database
- Social engineering or phishing
- Denial of service via resource exhaustion (e.g. excessive requests)
- Vulnerabilities in third-party dependencies (report to the upstream project unless Domain Tracker is the vector)

---

## Security Features

Domain Tracker implements the following security measures:

### Authentication

- **Session-based login** with a single admin account
- **Password hashing** via `password_hash()` (PASSWORD_DEFAULT) — never store plaintext passwords
- **Session regeneration** on login to prevent session fixation
- **Secure session cookies** (HttpOnly, SameSite=Lax, Secure when HTTPS)
- **Optional TOTP** (Time-based One-Time Password) for two-factor authentication

### Rate Limiting

- **Login attempt logging** — failed attempts are recorded (username, IP, user agent, reason)
- **Configurable rate limits** — max failed attempts per IP within a time window (configurable in Security settings)
- **Lockout** — users exceeding the limit are blocked until the window expires

### CSRF Protection

- **CSRF tokens** on all state-changing forms (login, domain CRUD, import, settings)
- **Token verification** before processing POST requests

### Encryption

- **Database passwords at rest** — AES-256-GCM encryption for stored credentials
- **Key derivation** — `APP_KEY` is hashed with SHA-256 before use
- **Encryption key rotation** — CLI script to re-encrypt all stored passwords with a new key

### Data Protection

- **No credential logging** — database usernames and passwords are never written to logs
- **Login attempt logging only** — no sensitive data in logs or error output

### Access Control

- **Single admin user** — no multi-user or role-based access control
- **Protected routes** — all app pages require authentication except login

### File Security

- **Document root** — `.env`, `config/`, `data/`, `src/`, `views/` are outside the web root
- **`.htaccess` deny rules** — sensitive directories deny direct access (Apache)
- **Installer** — runs once; can be removed or locked after install

---

## Deployment Best Practices

### Environment

- **Set `APP_KEY`** — 32+ random bytes; generate with `php -r "echo bin2hex(random_bytes(32));"`
- **Set `ADMIN_PASSWORD_HASH`** — never use plaintext; generate with `php -r "echo password_hash('your-password', PASSWORD_DEFAULT);"`
- **Use HTTPS** — enforce TLS in production
- **Keep `.env` private** — never commit it; ensure it is outside the web root

### Web Server

- **Document root** — point to `public/` so `.env`, `config/`, `data/`, `src/`, and `views/` are not directly accessible
- **PHP** — use PHP 8.1+ (8.2+ recommended) with PDO SQLite and OpenSSL extensions
- **Disable directory listing** — if supported by your server

### Backups

- **Back up `data/app.db` and `.env` together** — encrypted data requires `APP_KEY` to decrypt
- **Store backups securely** — treat them as sensitive as live credentials

### Post-Install

- **Remove or lock the installer** — delete `installer.php` or rely on `install.lock` after setup
- **Rotate keys periodically** — use `php scripts/rotate_key.php` and update `.env`

---

## Known Limitations

- **Single admin** — no built-in multi-user support; shared credentials if multiple people need access
- **Demo mode** — uses fixed demo/demo credentials; never enable in production
- **`.htaccess`** — Apache-specific; other servers (e.g. Nginx) require manual configuration to protect sensitive paths
- **Updater** — downloads from GitHub; ensure your server can reach it and verify integrity if needed

---

## Acknowledgments

Security researchers who responsibly disclose vulnerabilities will be credited here (with their consent) after the issue is resolved.
