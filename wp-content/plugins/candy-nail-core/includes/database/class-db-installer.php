<?php

if (!defined('ABSPATH')) {
    exit;
}

class CN_Database_Installer
{
    const DB_VERSION = '1.0.0';

    public static function activate()
    {
        self::create_loyalty_accounts_table();
        self::create_points_table();
        self::create_reservation_table();

        update_option(
            'cn_database_version',
            self::DB_VERSION
        );
    }

    public static function create_loyalty_accounts_table()
    {
        global $wpdb;

        require_once ABSPATH .
            'wp-admin/includes/upgrade.php';

        $charset_collate =
            $wpdb->get_charset_collate();

        $table =
            CN_DB::loyalty_accounts_table();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            points_balance BIGINT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,

            PRIMARY KEY (id),
            UNIQUE KEY user_id (user_id)

        ) {$charset_collate};";

        dbDelta($sql);
    }

    public static function create_points_table()
    {
        global $wpdb;

        require_once ABSPATH .
            'wp-admin/includes/upgrade.php';

        $charset_collate =
            $wpdb->get_charset_collate();

        $table =
            CN_DB::points_table();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            order_id BIGINT UNSIGNED DEFAULT NULL,
            type VARCHAR(30) NOT NULL,
            points BIGINT NOT NULL,
            reference VARCHAR(100) DEFAULT NULL,
            notes TEXT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

            PRIMARY KEY (id),

            KEY user_id (user_id),
            KEY order_id (order_id),
            KEY type (type),
            KEY created_at (created_at),
            UNIQUE KEY user_reference (user_id, reference)

        ) {$charset_collate};";

        dbDelta($sql);
    }

    public static function create_reservation_table()
    {
        global $wpdb;

        require_once ABSPATH .
            'wp-admin/includes/upgrade.php';

        $charset_collate =
            $wpdb->get_charset_collate();

        $table =
            CN_DB::reservations_table();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            order_id BIGINT UNSIGNED NOT NULL,
            points BIGINT NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'held',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            released_at DATETIME NULL,
            converted_at DATETIME NULL,

            PRIMARY KEY (id),

            UNIQUE KEY order_id (order_id),
            KEY user_id (user_id),
            KEY status (status)

        ) {$charset_collate};";

        dbDelta($sql);
    }
}