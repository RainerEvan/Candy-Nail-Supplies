<?php

if (!defined('ABSPATH')) {
    exit;
}

class CN_DB
{
    public static function loyalty_accounts_table()
    {
        global $wpdb;

        return $wpdb->prefix . 'cn_loyalty_accounts';
    }
    
    public static function points_table()
    {
        global $wpdb;

        return $wpdb->prefix . 'cn_points_transactions';
    }

    public static function reservations_table()
    {
        global $wpdb;

        return $wpdb->prefix . 'cn_points_reservations';
    }
}