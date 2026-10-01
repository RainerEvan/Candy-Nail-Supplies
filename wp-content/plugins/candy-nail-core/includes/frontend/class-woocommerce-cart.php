<?php

if (! defined('ABSPATH')) {
    exit;
}

class CN_WooCommerce_Cart
{

    public static function init()
    {

        add_action(
            'wp',
            [self::class, 'disable_kiriminaja_template_overrides'],
            20
        );

        add_filter(
            'woocommerce_cart_totals_shipping_html',
            [self::class, 'hide_shipping'],
            10
        );

        add_filter(
            'woocommerce_cart_totals_order_total_html',
            [self::class, 'display_cart_total_without_shipping'],
            10
        );

        add_action(
            'wp_enqueue_scripts',
            [self::class, 'enqueue_styles']
        );
    }

    public static function disable_kiriminaja_template_overrides()
    {

        if (! function_exists('is_cart') || ! is_cart()) {
            return;
        }

        remove_filter(
            'woocommerce_locate_template',
            'kiriof_override_woocommerce_template',
            10
        );

        add_filter(
            'woocommerce_cart_get_fees',
            [self::class, 'hide_insurance_on_cart'],
            10
        );
    }

    public static function hide_shipping($html)
    {

        if (is_cart()) {
            return '';
        }

        return $html;
    }

    public static function display_cart_total_without_shipping($total_html)
    {

        if (! is_cart() || ! WC()->cart) {
            return $total_html;
        }

        return '<strong>' .
            wc_price(WC()->cart->get_cart_contents_total()) .
            '</strong>';
    }

    public static function enqueue_styles()
    {

        if (! is_cart()) {
            return;
        }

        $css_path = CN_CORE_PATH . 'assets/css/woocommerce-cart.css';
        $css_url  = CN_CORE_URL . 'assets/css/woocommerce-cart.css';

        wp_enqueue_style(
            'cn-woocommerce-cart',
            $css_url,
            [],
            file_exists($css_path)
                ? filemtime($css_path)
                : CN_CORE_VERSION
        );
    }
}
