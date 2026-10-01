<?php

if (! defined('ABSPATH')) {
    exit;
}

class CN_WooCommerce_Checkout
{

    public static function init()
    {
        add_action(
            'wp_enqueue_scripts',
            [self::class, 'enqueue_kiriminaja_checkout_script']
        );

        add_action(
            'woocommerce_checkout_process',
            [self::class, 'remove_kiriminaja_kelurahan_validation'],
            1
        );

        add_action(
            'woocommerce_after_checkout_validation',
            [self::class, 'remove_kiriminaja_duplicate_notices'],
            100,
            2
        );
    }

    public static function enqueue_kiriminaja_checkout_script()
    {

        if (! is_checkout()) {
            return;
        }

        wp_enqueue_script(
            'cn-kiriminaja-checkout',
            CN_CORE_URL . 'assets/js/kiriminaja-checkout.js',
            ['jquery'],
            CN_CORE_VERSION,
            true
        );
    }

    public static function remove_kiriminaja_kelurahan_validation()
    {
        global $wp_filter;

        if (
            ! isset($wp_filter['woocommerce_checkout_process'])
            || ! isset($wp_filter['woocommerce_checkout_process']->callbacks)
        ) {
            return;
        }

        foreach (
            $wp_filter['woocommerce_checkout_process']->callbacks
            as $priority => $callbacks
        ) {

            foreach ($callbacks as $callback) {

                if (
                    isset($callback['function'])
                    && is_array($callback['function'])
                    && isset($callback['function'][0])
                    && isset($callback['function'][1])
                ) {

                    $object = $callback['function'][0];
                    $method = $callback['function'][1];

                    if (
                        is_object($object)
                        && $method === 'kiriof_checkout_field_validation'
                    ) {
                        remove_action(
                            'woocommerce_checkout_process',
                            $callback['function'],
                            $priority
                        );
                    }
                }
            }
        }
    }

    public static function remove_kiriminaja_duplicate_notices($data, $errors)
    {
        $notices = wc_get_notices('error');

        if (! empty($notices)) {

            $remove_messages = [
                'District is a required field',
                'Shipping is a required field',
            ];

            foreach ($notices as $key => $notice) {

                if (
                    ! is_array($notice)
                    || ! isset($notice['notice'])
                ) {
                    continue;
                }

                $message = trim(
                    html_entity_decode(
                        wp_strip_all_tags($notice['notice'])
                    )
                );

                if (in_array($message, $remove_messages, true)) {
                    unset($notices[$key]);
                }
            }

            WC()->session->set(
                'wc_notices',
                [
                    'error' => array_values($notices),
                ]
            );
        }
    }
}
