# CampusMart — Student Marketplace

Buy and sell used items on campus (books, notes, laptops, hostel essentials),
chat with sellers in-app, save a wishlist, rate sellers, and post Lost & Found
items. Replaces messy WhatsApp groups with a searchable, trustworthy platform.

**Stack:** plain PHP 8+ (no framework, no Composer) · PDO with prepared
statements · MySQL/MariaDB for hosting + SQLite for zero-setup local runs ·
server-rendered pages + vanilla JS + hand-written CSS · AJAX chat polling.

## Quick start (SQLite, zero setup)

```bash
php -S localhost:8000
# open http://localhost:8000
```

The ZIP ships with a pre-seeded SQLite database, so it works immediately.
To rebuild from scratch:

```bash
rm -f database/campusmart.sqlite
php install.php --cli --admin-email=admin@campusmart.local --admin-pass=admin123 --demo
php -S localhost:8000
```

## XAMPP (Windows)

1. Copy the `campusmart` folder to `C:\xampp\htdocs\`
2. Start Apache in the XAMPP control panel
3. Open `http://localhost/campusmart/install.php`, create the admin account
   (tick "demo data"), then **delete `install.php`**

## Free hosting (InfinityFree / 000webhost / Hostinger)

1. Create an account and a MySQL database (note host, name, user, password)
2. Upload all files via FTP / File Manager to `htdocs`
3. Edit `config.php`: set `db_driver` to `'mysql'` and fill in credentials
4. Visit `https://your-site/install.php` → create admin → **delete `install.php`**

## Demo accounts (seeded)

| Role    | Email                     | Password     |
|---------|---------------------------|--------------|
| Admin   | admin@campusmart.local    | admin123     |
| Student | ali@campus.edu.pk         | password123  |
| Student | sara@campus.edu.pk        | password123  |

## Configuration

Everything lives in `config.php` (copy of `config.sample.php`):

- `db_driver`: `'sqlite'` or `'mysql'`
- `email_verification`: off by default (free hosts often block mail)
- `allowed_domains_only`: restrict sign-ups to university domains (managed in Admin → Email domains)

## Project structure

```
campusmart/
├── config.php / config.sample.php   # the ONLY config file
├── install.php                      # web + CLI installer (delete after use)
├── index.php                        # home: search, filters, listing grid
├── listing.php  create.php  edit.php # listing CRUD
├── login.php register.php logout.php verify.php forgot.php reset.php
├── chat.php  conversation.php        # inbox + thread (AJAX polling)
├── wishlist.php  review.php  report.php  profile.php  notifications.php
├── lost-found.php                   # Lost & Found board
├── admin.php                        # dashboard, users, listings, reports, ...
├── api/                             # JSON endpoints (chat, wishlist, price…)
├── app/                             # bootstrap, db, helpers, auth, mailer,
│                                    # image (GD), price (suggestion), seed
├── assets/css/style.css             # hand-written design system
├── assets/js/app.js                 # vanilla JS
├── uploads/                         # user images (generated thumbs too)
├── schema/mysql.sql  schema/sqlite.sql
└── database/                        # SQLite file (local runs)
```

## Security

- `password_hash`/`password_verify`, secure session cookies, session
  regeneration on login, CSRF tokens on every form, login rate limiting
  (5 failures / 15 min), prepared statements everywhere, uploads validated
  by real MIME type + resized with GD, `.htaccess` blocks PHP in `/uploads`.

## License

MIT — built as a final-year project.
