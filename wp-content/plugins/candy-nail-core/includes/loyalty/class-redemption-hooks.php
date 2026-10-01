<?php

if (!defined('ABSPATH')) {
    exit;
}

class CN_Redemption_Hooks
{
    public static function init()
    {
        add_action(
            'woocommerce_review_order_before_payment',
            [self::class, 'display_checkbox']
        );

        add_action(
            'woocommerce_cart_calculate_fees',
            [CN_Redemption_Service::class, 'apply_discount']
        );

        add_action(
            'woocommerce_checkout_update_order_review',
            [self::class, 'save_checkbox']
        );

        add_action(
            'wp_footer',
            [self::class, 'checkout_script']
        );

        add_action(
            'woocommerce_checkout_create_order',
            [self::class, 'save_order_points'],
            20,
            2
        );
    }

    public static function display_checkbox()
    {
        if (!is_user_logged_in()) {
            return;
        }

        $balance =
            CN_Redemption_Service::get_redeemable_points(
                get_current_user_id()
            );

        if ($balance <= 0) {
            return;
        }

        woocommerce_form_field(
            'cn_use_points',
            [
                'type'  => 'checkbox',
                'class' => ['form-row-wide'],
                'label' => sprintf(
                    'Use my %s points',
                    number_format_i18n($balance)
                ),
            ],
            WC()->session->get(
                'cn_use_points'
            ) === 'yes'
        );
    }

    public static function save_checkbox($posted_data)
    {
        if (!WC()->session) {
            return;
        }

        parse_str(
            $posted_data,
            $data
        );

        WC()->session->set(
            'cn_use_points',
            !empty($data['cn_use_points'])
                ? 'yes'
                : 'no'
        );
    }

    public static function checkout_script()
    {
        if (!is_checkout()) {
            return;
        }

        ?>
        <script>
        jQuery(function($) {
            $(document.body).on(
                'change',
                '#cn_use_points',
                function() {
                    $('body').trigger('update_checkout');
                }
            );
        });
        </script>
        <?php
    }

    public static function save_order_points(
        $order,
        $posted_data
    ) {
        $points_used = 0;

        foreach (
            $order->get_items('fee')
            as $fee
        ) {

            if (
                $fee->get_name()
                !== 'Points Discount'
            ) {
                continue;
            }

            $fee_total =
                (float) $fee->get_total();

            if ($fee_total < 0) {
                $points_used =
                    abs($fee_total);
            }

            break;
        }

        if ($points_used <= 0) {
            return;
        }

        /*
         * 1 point = 1 IDR.
         */
        $points = (int) round(
            $points_used
        );

        if ($points <= 0) {
            return;
        }

        $order->update_meta_data(
            '_cn_points_used',
            $points
        );
    }
}