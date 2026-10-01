<?php

if (!defined('ABSPATH')) {
    exit;
}

class CN_Loyalty_Account
{
    public static function init()
    {
        add_action(
            'init',
            [self::class, 'register_endpoint']
        );

        add_filter(
            'woocommerce_get_query_vars',
            [self::class, 'add_query_var']
        );

        add_filter(
            'woocommerce_account_menu_items',
            [self::class, 'add_menu_item']
        );

        add_action(
            'woocommerce_account_loyalty-points_endpoint',
            [self::class, 'render_page']
        );

        add_filter(
            'woocommerce_account_menu_items',
            [self::class, 'remove_downloads']
        );

        add_action(
            'wp_enqueue_scripts',
            [self::class, 'enqueue_styles']
        );

        add_filter(
            'blocksy:breadcrumbs:items-array',
            [self::class, 'customize_breadcrumb'],
            10,
            1
        );
    }

    public static function register_endpoint()
    {
        add_rewrite_endpoint(
            'loyalty-points',
            EP_ROOT | EP_PAGES
        );
    }

    public static function add_query_var($vars)
    {
        $vars['loyalty-points'] = 'loyalty-points';

        return $vars;
    }

    public static function add_menu_item($items)
    {
        $new_items = [];

        foreach ($items as $key => $label) {
            $new_items[$key] = $label;

            if ($key === 'dashboard') {
                $new_items['loyalty-points'] = 'Loyalty Points';
            }
        }

        return $new_items;
    }

    public static function render_page()
    {
        $user_id = get_current_user_id();

        $balance = CN_Loyalty_Manager::get_balance($user_id);

        $transactions = CN_Loyalty_Manager::get_transactions($user_id);

        wc_get_template(
            'myaccount/loyalty-points.php',
            [
                'balance'      => $balance,
                'transactions' => $transactions,
            ],
            '',
            plugin_dir_path(__FILE__) . '../../templates/'
        );
    }

    public static function remove_downloads($items)
    {
        unset($items['downloads']);

        return $items;
    }

    public static function enqueue_styles()
    {
        if (!is_account_page()) {
            return;
        }

        if (!is_wc_endpoint_url('loyalty-points')) {
            return;
        }

        $css_path = CN_CORE_PATH . 'assets/css/loyalty-points.css';
        $css_url = CN_CORE_URL . 'assets/css/loyalty-points.css';

        wp_enqueue_style(
            'cn-loyalty-points',
            $css_url,
            [],
            file_exists($css_path)
                ? filemtime($css_path)
                : CN_CORE_VERSION
        );
    }

    public static function customize_breadcrumb($items)
    {
        if (!is_wc_endpoint_url('loyalty-points')) {
            return $items;
        }

        $last_index = array_key_last($items);

        if ($last_index !== null) {
            $items[$last_index]['name'] = 'Loyalty Points';
            $items[$last_index]['url']  = '';
        }

        return $items;
    }
}
