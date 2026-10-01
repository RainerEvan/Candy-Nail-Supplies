<?php

if (!defined('ABSPATH')) {
    exit;
}

class CN_WooCommerce_Price {

    public static function init() {

        add_filter(
            'woocommerce_get_price_html',
            [self::class, 'add_discount_percentage'],
            20,
            2
        );

        add_action(
            'wp_enqueue_scripts',
            [self::class, 'enqueue_styles']
        );
    }

    /**
     * Add discount percentage to WooCommerce sale prices.
     */
    public static function add_discount_percentage($price, $product) {

        // Only modify products that are on sale.
        if (!$product->is_on_sale()) {
            return $price;
        }

        $regular_price = (float) $product->get_regular_price();
        $sale_price    = (float) $product->get_sale_price();

        // Make sure both prices are valid.
        if (
            $regular_price <= 0 ||
            $sale_price <= 0 ||
            $sale_price >= $regular_price
        ) {
            return $price;
        }

        // Calculate discount percentage.
        $discount = round(
            (($regular_price - $sale_price) / $regular_price) * 100
        );

        return $price . sprintf(
            '<span class="cn-discount">-%d%%</span>',
            $discount
        );
    }

    /**
     * Enqueue WooCommerce price styles.
     */
    public static function enqueue_styles() {

        if (!class_exists('WooCommerce')) {
            return;
        }

        $css_path = CN_CORE_PATH . 'assets/css/woocommerce-price.css';
        $css_url  = CN_CORE_URL . 'assets/css/woocommerce-price.css';

        wp_enqueue_style(
            'cn-woocommerce-price',
            $css_url,
            [],
            file_exists($css_path)
                ? filemtime($css_path)
                : CN_CORE_VERSION
        );
    }
}