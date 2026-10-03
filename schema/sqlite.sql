-- =====================================================================
-- CampusMart · SQLite schema (zero-setup local development)
-- Run via install.php (recommended) or:  sqlite3 campusmart.sqlite < sqlite.sql
-- =====================================================================
PRAGMA foreign_keys = ON;

-- ---------------------------------------------------------------- users
CREATE TABLE IF NOT EXISTS users (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    name          TEXT NOT NULL,
    email         TEXT NOT NULL,
    password_hash TEXT NOT NULL,
    role          TEXT NOT NULL DEFAULT 'student' CHECK (role IN ('student','admin')),
    is_verified   INTEGER NOT NULL DEFAULT 1,
    is_banned     INTEGER NOT NULL DEFAULT 0,
    verify_token  TEXT NULL,
    reset_token   TEXT NULL,
    reset_expires TEXT NULL,
    created_at    TEXT NOT NULL DEFAULT (datetime('now')),
    UNIQUE (email)
);
CREATE INDEX IF NOT EXISTS idx_users_role ON users (role);

-- ----------------------------------------------------------- categories
CREATE TABLE IF NOT EXISTS categories (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    name       TEXT NOT NULL,
    slug       TEXT NOT NULL,
    color      TEXT NOT NULL DEFAULT '#6C4CF1',
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    UNIQUE (slug)
);

-- ------------------------------------------------------------- listings
CREATE TABLE IF NOT EXISTS listings (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id     INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    category_id INTEGER NOT NULL REFERENCES categories (id),
    title       TEXT NOT NULL,
    description TEXT NOT NULL,
    price       REAL NOT NULL,
    "condition" TEXT NOT NULL DEFAULT 'good'
                CHECK ("condition" IN ('new','like_new','good','fair')),
    campus      TEXT NOT NULL DEFAULT '',
    location    TEXT NOT NULL DEFAULT '',
    status      TEXT NOT NULL DEFAULT 'active'
                CHECK (status IN ('active','sold','deleted')),
    views       INTEGER NOT NULL DEFAULT 0,
    created_at  TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at  TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_listings_status_created ON listings (status, created_at);
CREATE INDEX IF NOT EXISTS idx_listings_category ON listings (category_id);
CREATE INDEX IF NOT EXISTS idx_listings_price ON listings (price);

-- ------------------------------------------------------- listing_images
CREATE TABLE IF NOT EXISTS listing_images (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    listing_id INTEGER NOT NULL REFERENCES listings (id) ON DELETE CASCADE,
    filename   TEXT NOT NULL,
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_images_listing ON listing_images (listing_id, sort_order);

-- -------------------------------------------------------- conversations
CREATE TABLE IF NOT EXISTS conversations (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    listing_id      INTEGER NOT NULL REFERENCES listings (id) ON DELETE CASCADE,
    buyer_id        INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    seller_id       INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    last_message_at TEXT NULL,
    created_at      TEXT NOT NULL DEFAULT (datetime('now')),
    UNIQUE (listing_id, buyer_id)
);
CREATE INDEX IF NOT EXISTS idx_conv_buyer ON conversations (buyer_id);
CREATE INDEX IF NOT EXISTS idx_conv_seller ON conversations (seller_id);

-- --------------------------------------------------------------- messages
CREATE TABLE IF NOT EXISTS messages (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    conversation_id INTEGER NOT NULL REFERENCES conversations (id) ON DELETE CASCADE,
    sender_id       INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    body            TEXT NOT NULL,
    is_read         INTEGER NOT NULL DEFAULT 0,
    created_at      TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_msg_conversation ON messages (conversation_id, id);

-- -------------------------------------------------------------- wishlists
CREATE TABLE IF NOT EXISTS wishlists (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id    INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    listing_id INTEGER NOT NULL REFERENCES listings (id) ON DELETE CASCADE,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    UNIQUE (user_id, listing_id)
);

-- ---------------------------------------------------------------- reviews
CREATE TABLE IF NOT EXISTS reviews (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    listing_id  INTEGER NOT NULL REFERENCES listings (id) ON DELETE CASCADE,
    reviewer_id INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    seller_id   INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    rating      INTEGER NOT NULL CHECK (rating BETWEEN 1 AND 5),
    comment     TEXT NULL,
    created_at  TEXT NOT NULL DEFAULT (datetime('now')),
    UNIQUE (listing_id, reviewer_id)
);
CREATE INDEX IF NOT EXISTS idx_review_seller ON reviews (seller_id);

-- ---------------------------------------------------------------- reports
CREATE TABLE IF NOT EXISTS reports (
    id               INTEGER PRIMARY KEY AUTOINCREMENT,
    reporter_id      INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    listing_id       INTEGER NULL REFERENCES listings (id) ON DELETE SET NULL,
    reported_user_id INTEGER NULL REFERENCES users (id) ON DELETE SET NULL,
    reason           TEXT NOT NULL,
    details          TEXT NULL,
    status           TEXT NOT NULL DEFAULT 'pending'
                     CHECK (status IN ('pending','reviewed','dismissed')),
    created_at       TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_reports_status ON reports (status);

-- -------------------------------------------------------------- lost_found
CREATE TABLE IF NOT EXISTS lost_found (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id     INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    type        TEXT NOT NULL CHECK (type IN ('lost','found')),
    title       TEXT NOT NULL,
    description TEXT NOT NULL,
    image       TEXT NULL,
    location    TEXT NOT NULL DEFAULT '',
    status      TEXT NOT NULL DEFAULT 'open' CHECK (status IN ('open','resolved')),
    created_at  TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_lf_type_status ON lost_found (type, status);

-- ---------------------------------------------------------- notifications
CREATE TABLE IF NOT EXISTS notifications (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id    INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    type       TEXT NOT NULL,
    title      TEXT NOT NULL,
    body       TEXT NULL,
    link       TEXT NULL,
    is_read    INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_notif_user ON notifications (user_id, is_read, created_at);

-- --------------------------------------------------------------- settings
CREATE TABLE IF NOT EXISTS settings (
    setting_key   TEXT PRIMARY KEY,
    setting_value TEXT NULL
);

-- -------------------------------------------------------- allowed_domains
CREATE TABLE IF NOT EXISTS allowed_domains (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    domain     TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    UNIQUE (domain)
);

-- -------------------------------------------------------- login_attempts
CREATE TABLE IF NOT EXISTS login_attempts (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    ip           TEXT NOT NULL,
    email        TEXT NOT NULL,
    attempted_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_attempts_ip_email ON login_attempts (ip, email, attempted_at);
