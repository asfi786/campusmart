# CampusMart — Local Test Report

**Date:** September 24, 2026
**Environment:** Linux VM, PHP 8.3.6 (cli), built-in web server
**Database under test:** SQLite (`database/campusmart.sqlite`), zero-setup mode

## Environment

- PHP version: 8.3.6
- Verified PHP modules: `curl`, `gd`, `mbstring`, `PDO`, `pdo_sqlite`, `sqlite3`
- Web server: `php -S 127.0.0.1:8000` (PHP built-in server)

## 1. Static checks

| Check | Result |
|---|---|
| `php -l` on every PHP file in the project | All pass, zero syntax errors |
| Installer run (`install.php --cli --demo`) | Success — created tables, admin account, demo data |
| Clean seed contents | 6 users, 20 listings, 39 listing images, 8 categories, 2 conversations, 5 messages, 3 reviews, 2 reports, 4 Lost & Found posts, 4 settings rows; 78 generated JPEGs incl. thumbnails |

## 2. Automated end-to-end test suite (`tests/e2e.php`)

Real HTTP requests against the running server, using cookie jars and a real image upload.

**Result: 40 passed, 0 failed.**

Covered:
- User registration, login, session handling, logout
- Failed-login throttling
- Admin login; non-admin blocked from admin pages (403)
- Real image upload + gallery rendering
- Listing CRUD, search, category filter, sorting, pagination
- Smart Price Suggestion API (median/IQR comparables, confidence levels)
- Wishlist toggle via AJAX; API CSRF rejection for bad tokens
- Conversations: creation, 3-second-poll chat send/receive, read state, unread-count API
- Mark listing as sold; review creation + review restrictions (no self-review)
- Reports: submit, admin handling
- Lost & Found: post, filter, claim flow
- Notifications: creation, list, mark-as-read
- Password reset: token flow, login with new password

Issues found and fixed during testing:
- E2E cookie jars and a couple of endpoint/action names were wrong in the test script itself (fixed in the test, not the app).
- API CSRF errors now return parseable JSON (`validate_csrf(true)`).
- `reset.php` now uses the resolved reset token correctly.
- Default mailer changed from `mail` to `none`; email verification stays OFF by default (configurable).

## 3. Manual smoke test (after clean database rebuild)

| Page | Result |
|---|---|
| `index.php` (home) | 200 — renders listing cards with prices |
| `listing.php?id=2` | 200 — gallery + details render |
| `listing.php` (no id) | 404 — app's own "Not found" page, correct |
| `login.php`, `register.php`, `lost-found.php` | 200 |
| `create.php` as guest | 302 → login, correct |
| `api/price_suggest.php?title=laptop&category_id=1` | 200 — valid JSON estimate with confidence |

## 4. What was NOT tested (honest limitations)

1. **MySQL/MariaDB deployment was not tested locally.** Only SQLite mode is genuinely verified. The schema files exist for both, and all queries use PDO with portable SQL, but a real MySQL run (shared hosting / XAMPP) has not been executed in this environment.
2. **No real-browser UI testing.** This environment's managed Chromium blocks local-server access (Local Network Access checks) and has screenshots disabled, so dark mode, mobile menu, gallery lightbox, and responsive layout could not be visually verified in a browser. Layout was reviewed in code only.
3. **Email sending was not tested.** Mailer defaults to `none`; the `mail()` and SMTP paths exist in code but were not exercised against a real mail server.
4. **Demo credentials** (seeded): admin `admin@campusmart.local` / `admin123`; students `ali@campus.edu.pk`, `sara@campus.edu.pk` / `password123`. Change these before any real deployment.
