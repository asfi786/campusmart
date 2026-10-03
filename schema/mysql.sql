-- =====================================================================
-- CampusMart · MySQL / MariaDB schema
-- Run via install.php (recommended) or:  mysql -u USER -p DB < mysql.sql
-- =====================================================================
SET NAMES utf8mb4;

-- ---------------------------------------------------------------- users
CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(100) NOT NULL,
    email         VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role          ENUM('student','admin') NOT NULL DEFAULT 'student',
    is_verified   TINYINT(1) NOT NULL DEFAULT 1,
    is_banned     TINYINT(1) NOT NULL DEFAULT 0,
    verify_token  VARCHAR(64) NULL,
    reset_token   VARCHAR(64) NULL,
    reset_expires DATETIME NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------- categories
CREATE TABLE IF NOT EXISTS categories (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(80) NOT NULL,
    slug       VARCHAR(80) NOT NULL,
    color      VARCHAR(7) NOT NULL DEFAULT '#6C4CF1',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_categories_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------- listings
CREATE TABLE IF NOT EXISTS listings (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    title       VARCHAR(150) NOT NULL,
    description TEXT NOT NULL,
    price       DECIMAL(10,2) NOT NULL,
    `condition` ENUM('new','like_new','good','fair') NOT NULL DEFAULT 'good',
    campus      VARCHAR(100) NOT NULL DEFAULT '',
    location    VARCHAR(150) NOT NULL DEFAULT '',
    status      ENUM('active','sold','deleted') NOT NULL DEFAULT 'active',
    views       INT UNSIGNED NOT NULL DEFAULT 0,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_listings_user FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_listings_category FOREIGN KEY (category_id)
        REFERENCES categories (id),
    KEY idx_listings_status_created (status, created_at),
    KEY idx_listings_category (category_id),
    KEY idx_listings_price (price),
    FULLTEXT KEY ft_listings_title_desc (title, description)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------- listing_images
CREATE TABLE IF NOT EXISTS listing_images (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    listing_id INT UNSIGNED NOT NULL,
    filename   VARCHAR(255) NOT NULL,   -- relative path inside /uploads
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_images_listing FOREIGN KEY (listing_id)
        REFERENCES listings (id) ON DELETE CASCADE,
    KEY idx_images_listing (listing_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------- conversations
CREATE TABLE IF NOT EXISTS conversations (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    listing_id      INT UNSIGNED NOT NULL,
    buyer_id        INT UNSIGNED NOT NULL,
    seller_id       INT UNSIGNED NOT NULL,
    last_message_at DATETIME NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_conv_listing FOREIGN KEY (listing_id)
        REFERENCES listings (id) ON DELETE CASCADE,
    CONSTRAINT fk_conv_buyer FOREIGN KEY (buyer_id)
        REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_conv_seller FOREIGN KEY (seller_id)
        REFERENCES users (id) ON DELETE CASCADE,
    UNIQUE KEY uq_conv_listing_buyer (listing_id, buyer_id),
    KEY idx_conv_buyer (buyer_id),
    KEY idx_conv_seller (seller_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------- messages
CREATE TABLE IF NOT EXISTS messages (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT UNSIGNED NOT NULL,
    sender_id       INT UNSIGNED NOT NULL,
    body            TEXT NOT NULL,
    is_read         TINYINT(1) NOT NULL DEFAULT 0,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_msg_conversation FOREIGN KEY (conversation_id)
        REFERENCES conversations (id) ON DELETE CASCADE,
    CONSTRAINT fk_msg_sender FOREIGN KEY (sender_id)
        REFERENCES users (id) ON DELETE CASCADE,
    KEY idx_msg_conversation (conversation_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------- wishlists
CREATE TABLE IF NOT EXISTS wishlists (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    listing_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_wish_user FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_wish_listing FOREIGN KEY (listing_id)
        REFERENCES listings (id) ON DELETE CASCADE,
    UNIQUE KEY uq_wishlist (user_id, listing_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- reviews
CREATE TABLE IF NOT EXISTS reviews (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    listing_id  INT UNSIGNED NOT NULL,
    reviewer_id INT UNSIGNED NOT NULL,   -- the buyer
    seller_id   INT UNSIGNED NOT NULL,
    rating      TINYINT UNSIGNED NOT NULL,
    comment     TEXT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_review_listing FOREIGN KEY (listing_id)
        REFERENCES listings (id) ON DELETE CASCADE,
    CONSTRAINT fk_review_reviewer FOREIGN KEY (reviewer_id)
        REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_review_seller FOREIGN KEY (seller_id)
        REFERENCES users (id) ON DELETE CASCADE,
    UNIQUE KEY uq_review (listing_id, reviewer_id),  -- one review per sale
    KEY idx_review_seller (seller_id),
    CONSTRAINT chk_review_rating CHECK (rating BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- reports
CREATE TABLE IF NOT EXISTS reports (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reporter_id      INT UNSIGNED NOT NULL,
    listing_id       INT UNSIGNED NULL,
    reported_user_id INT UNSIGNED NULL,
    reason           VARCHAR(100) NOT NULL,
    details          TEXT NULL,
    status           ENUM('pending','reviewed','dismissed') NOT NULL DEFAULT 'pending',
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_report_reporter FOREIGN KEY (reporter_id)
        REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_report_listing FOREIGN KEY (listing_id)
        REFERENCES listings (id) ON DELETE SET NULL,
    CONSTRAINT fk_report_user FOREIGN KEY (reported_user_id)
        REFERENCES users (id) ON DELETE SET NULL,
    KEY idx_reports_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------- lost_found
CREATE TABLE IF NOT EXISTS lost_found (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    type        ENUM('lost','found') NOT NULL,
    title       VARCHAR(150) NOT NULL,
    description TEXT NOT NULL,
    image       VARCHAR(255) NULL,
    location    VARCHAR(150) NOT NULL DEFAULT '',
    status      ENUM('open','resolved') NOT NULL DEFAULT 'open',
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_lf_user FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE CASCADE,
    KEY idx_lf_type_status (type, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------- notifications
CREATE TABLE IF NOT EXISTS notifications (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    type       VARCHAR(50) NOT NULL,     -- message | review | report | system
    title      VARCHAR(150) NOT NULL,
    body       TEXT NULL,
    link       VARCHAR(255) NULL,
    is_read    TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notif_user FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE CASCADE,
    KEY idx_notif_user (user_id, is_read, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------- settings
CREATE TABLE IF NOT EXISTS settings (
    setting_key   VARCHAR(100) PRIMARY KEY,
    setting_value TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------- allowed_domains
CREATE TABLE IF NOT EXISTS allowed_domains (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    domain     VARCHAR(190) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_domain (domain)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------- login_attempts
-- Used for login rate limiting (5 failures / 15 min per IP+email).
CREATE TABLE IF NOT EXISTS login_attempts (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip           VARCHAR(45) NOT NULL,
    email        VARCHAR(190) NOT NULL,
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_attempts_ip_email (ip, email, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
