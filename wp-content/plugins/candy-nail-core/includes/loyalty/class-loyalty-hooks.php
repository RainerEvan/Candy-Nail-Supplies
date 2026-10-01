<?php

if (!defined('ABSPATH')) {
    exit;
}

class CN_Loyalty_Hooks
{
    public static function init()
    {
        /*
         * Actually redeem held points only when completed.
         */
        add_action(
            'woocommerce_order_status_completed',
            [self::class, 'redeem_order_points'],
            10,
            1
        );

        /*
         * Earn points only after the order is completed.
         */
        add_action(
            'woocommerce_order_status_completed',
            [self::class, 'award_points'],
            20,
            1
        );

        /*
         * Hold redeemed points when order is created.
         */
        add_action(
            'woocommerce_checkout_order_processed',
            [self::class, 'hold_order_points'],
            20,
            3
        );

        /*
         * Release held points if order fails or is cancelled.
         */
        add_action(
            'woocommerce_order_status_cancelled',
            [self::class, 'release_order_points'],
            10,
            1
        );

        add_action(
            'woocommerce_order_status_failed',
            [self::class, 'release_order_points'],
            10,
            1
        );

        /*
         * Refund handling.
         */
        add_action(
            'woocommerce_order_refunded',
            [self::class, 'refund_order_points'],
            10,
            2
        );
    }

    /**
     * Award points when order becomes completed.
     */
    public static function award_points($order_id)
    {
        $order = wc_get_order($order_id);

        if (!$order) {
            return;
        }

        $user_id = $order->get_user_id();

        if (!$user_id) {
            return;
        }

        /*
         * Prevent duplicate earning.
         */
        if ($order->get_meta('_cn_points_awarded') === 'yes') {
            return;
        }

        $points =
            CN_Reward_Service::calculate_points($order);

        if ($points <= 0) {

            $order->update_meta_data(
                '_cn_points_awarded',
                'zero'
            );

            $order->save();

            return;
        }

        $success =
            CN_Reward_Service::earn_points(
                $user_id,
                $order_id,
                $points
            );

        if (!$success) {
            return;
        }

        $order->update_meta_data(
            '_cn_points_awarded',
            'yes'
        );

        $order->update_meta_data(
            '_cn_points_points',
            $points
        );

        $order->save();
    }

    /**
     * Hold redeemed points when order is created.
     */
    public static function hold_order_points(
        $order_id,
        $posted_data,
        $order
    ) {
        $points = (int) $order->get_meta(
            '_cn_points_used'
        );

        if ($points <= 0) {
            return;
        }

        $user_id = $order->get_user_id();

        if (!$user_id) {
            return;
        }

        /*
         * Prevent duplicate hold.
         */
        if (
            $order->get_meta('_cn_points_held')
            === 'yes'
        ) {
            return;
        }

        $success =
            CN_Redemption_Service::hold_points(
                $user_id,
                $order_id,
                $points
            );

        if (!$success) {

            $order->update_status(
                'failed',
                'Unable to hold customer points.'
            );

            return;
        }

        $order->update_meta_data(
            '_cn_points_held',
            'yes'
        );

        $order->save();
    }

    /**
     * Redeem held points when order becomes completed.
     */
    public static function redeem_order_points($order_id)
    {
        $order = wc_get_order($order_id);

        if (!$order) {
            return;
        }

        if (
            $order->get_meta('_cn_points_converted')
            === 'yes'
        ) {
            return;
        }

        /*
         * No points used.
         */
        if (
            (int) $order->get_meta('_cn_points_used')
            <= 0
        ) {
            return;
        }

        $redeemed =
            CN_Redemption_Service::redeem_order_points(
                $order_id
            );

        if (!$redeemed) {
            return;
        }

        $order->update_meta_data(
            '_cn_points_converted',
            'yes'
        );

        $order->save();
    }

    /**
     * Release held points.
     */
    public static function release_order_points($order_id)
    {
        $order = wc_get_order($order_id);

        if (!$order) {
            return;
        }

        if (
            $order->get_meta('_cn_points_released')
            === 'yes'
        ) {
            return;
        }

        $released =
            CN_Redemption_Service::release_hold(
                $order_id
            );

        if (!$released) {
            return;
        }

        $order->update_meta_data(
            '_cn_points_released',
            'yes'
        );

        $order->save();
    }

    /**
     * Handle refunds.
     *
     * $refund_id is the WooCommerce refund record.
     */
    public static function refund_order_points(
        $order_id,
        $refund_id
    ) {
        $order = wc_get_order($order_id);

        if (!$order) {
            return;
        }

        $user_id = $order->get_user_id();

        if (!$user_id) {
            return;
        }

        /*
         * Return redeemed points.
         */
        $used_points = (int) $order->get_meta(
            '_cn_points_used'
        );

        if ($used_points > 0) {

            CN_Redemption_Service::refund_redeemed_points(
                $order_id
            );
        }

        /*
         * Reverse earned points.
         */
        if (
            $order->get_meta('_cn_points_awarded')
            === 'yes'
        ) {

            CN_Redemption_Service::refund_earned_points(
                $order_id,
                $refund_id
            );
        }
    }
}
