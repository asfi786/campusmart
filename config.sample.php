<?php
/**
 * CampusMart — sample configuration.
 * Copy this file to config.php and adjust the values for your environment.
 * config.php is NOT committed to git (see .gitignore) so secrets stay private.
 */
return [
    /* ------------------------------------------------------------------
     * Database: 'sqlite' for zero-setup local runs, 'mysql' for hosting
     * ------------------------------------------------------------------ */
    'db_driver'   => 'sqlite',
    'sqlite_path' => __DIR__ . '/database/campusmart.sqlite',

    'mysql' => [
        'host'    => 'localhost',   // on InfinityFree this looks like sql123.epizy.com
        'dbname'  => 'campusmart',
        'user'    => 'root',
        'pass'    => '',
        'charset' => 'utf8mb4',
    ],

    /* ------------------------------------------------------------------ */
    'site_name' => 'CampusMart',
    'debug'     => true,            // set to false on a live server
    'currency'  => 'Rs',            // price prefix, e.g. "Rs 4,500"

    /* ------------------------------------------------------------------
     * Email. Free hosts often block mail(), so verification is OFF by
     * default. Turn it on only if you have tested that mail works.
     * 'mailer' => 'mail' uses PHP mail(). 'none' disables sending (default:
     * safest for local dev and free hosts without sendmail).
     * ------------------------------------------------------------------ */
    'email_verification' => false,
    'mailer'             => 'none',
    'mail_from'          => 'noreply@example.com',

    /* ------------------------------------------------------------------
     * Registration policy. When true, only addresses whose domain is in
     * the allowed_domains table (managed in the admin panel) may register.
     * ------------------------------------------------------------------ */
    'allowed_domains_only' => false,

    /* Uploads: max megabytes per image, max images per listing */
    'upload_max_mb'      => 5,
    'max_images'         => 5,

    /* Chat polling interval in milliseconds (AJAX long-polling) */
    'chat_poll_ms'       => 3000,

    /* Listings per page on the home/search page */
    'items_per_page'     => 12,
];
