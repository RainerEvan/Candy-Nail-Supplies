<?php

if (!defined('ABSPATH')) {
    exit;
}

class CN_WooCommerce_Coupon
{
    public static function init()
    {
        /*
         * Disable coupon form on Cart page.
         */
        add_filter(
            'woocommerce_coupons_enabled',
            [self::class, 'disable_cart_coupon'],
            10
        );

        /*
         * Display coupon form on Checkout page.
         */
        add_action(
            'woocommerce_before_checkout_form',
            [self::class, 'display_checkout_coupon'],
            10
        );
    }

    public static function disable_cart_coupon($enabled)
    {
        if (is_cart()) {
            return false;
        }

        return $enabled;
    }

    public static function display_checkout_coupon()
    {
        if (!wc_coupons_enabled()) {
            return;
        }

        wc_get_template('checkout/form-coupon.php');
    }
}