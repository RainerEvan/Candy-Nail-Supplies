<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CN_WooCommerce_Account {

    public static function init() {

        add_action(
            'wp_enqueue_scripts',
            [ self::class, 'enqueue_billing_address_script' ]
        );
    }

    /**
     * Enqueue KiriminAja address script on the Billing Address page.
     */
    public static function enqueue_billing_address_script() {

        if ( ! is_account_page() ) {
            return;
        }

        global $wp;

        if (
            ! isset( $wp->query_vars['edit-address'] ) ||
            'billing' !== $wp->query_vars['edit-address']
        ) {
            return;
        }

        wp_enqueue_script(
            'cn-kiriminaja-address',
            CN_CORE_URL . 'assets/js/kiriminaja-edit-address.js',
            [ 'jquery' ],
            CN_CORE_VERSION,
            true
        );
    }
}