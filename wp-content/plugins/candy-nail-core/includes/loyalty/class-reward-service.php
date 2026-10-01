<?php

if (!defined('ABSPATH')) {
    exit;
}

class CN_Reward_Service
{
    const EARN_RATE = 0.01;

    /**
     * Calculate earned points.
     *
     * Eligible amount is the product line total after product
     * discounts, minus loyalty points redeemed on the order.
     * Shipping and tax are excluded.
     */
    public static function calculate_points($order)
    {
        if (!$order instanceof WC_Order) {
            return 0;
        }

        $eligible_total = 0;

        foreach ($order->get_items('line_item') as $item) {

            if (!($item instanceof WC_Order_Item_Product)) {
                continue;
            }

            $line_total = (float) $item->get_total();

            if ($line_total > 0) {
                $eligible_total += $line_total;
            }
        }

        $points_used = (int) $order->get_meta('_cn_points_used');

        $eligible_total = max(
            0,
            $eligible_total - $points_used
        );

        return (int) floor(
            $eligible_total * self::EARN_RATE
        );
    }

    /**
     * Award points for a completed order.
     */
    public static function earn_points($user_id, $order_id, $points)
    {
        if ($points <= 0) {
            return false;
        }

        return CN_Loyalty_Manager::adjust_balance(
            $user_id,
            $points,
            'earn',
            $order_id,
            'EARN-' . absint($order_id),
            'Points earned from completed order #' . absint($order_id)
        );
    }

    /**
     * Calculate the eligible product total for an order.
     *
     * Used for refund calculations.
     */
    public static function get_order_eligible_total($order)
    {
        if (!$order instanceof WC_Order) {
            return 0;
        }

        $total = 0;

        foreach ($order->get_items('line_item') as $item) {

            if (!($item instanceof WC_Order_Item_Product)) {
                continue;
            }

            $line_total = (float) $item->get_total();

            if ($line_total > 0) {
                $total += $line_total;
            }
        }

        return $total;
    }

    /**
     * Calculate earned points for a refund.
     *
     * The full earned amount is returned for a full refund.
     */
    public static function calculate_refund_earned_points(
        $order,
        $refund
    ) {
        $original_eligible = self::get_order_eligible_total($order);

        if ($original_eligible <= 0) {
            return 0;
        }

        $refunded_eligible = 0;

        foreach ($refund->get_items('line_item') as $item) {

            $line_total = abs(
                (float) $item->get_total()
            );

            $refunded_eligible += $line_total;
        }

        if ($refunded_eligible <= 0) {
            return 0;
        }

        $earned_points = self::calculate_points($order);

        /*
         * Calculate the total points that should have been reversed
         * based on cumulative refunded product value.
         */
        $ratio = min(
            1,
            $refunded_eligible / $original_eligible
        );

        return (int) floor(
            $earned_points * $ratio
        );
    }
}
