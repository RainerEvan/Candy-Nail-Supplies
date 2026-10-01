<?php

if (!defined('ABSPATH')) {
    exit;
}

class CN_Redemption_Service
{
    /**
     * Get points available for redemption.
     */
    public static function get_redeemable_points($user_id)
    {
        return CN_Loyalty_Manager::get_balance($user_id);
    }

    /**
     * Apply points discount to cart.
     *
     * 1 point = 1 IDR.
     */
    public static function apply_discount($cart)
    {
        if (!is_user_logged_in()) {
            return;
        }

        if (!WC()->session) {
            return;
        }

        if (
            WC()->session->get('cn_use_points') !== 'yes'
        ) {
            return;
        }

        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }

        $user_id = get_current_user_id();

        $points = self::get_redeemable_points($user_id);

        if ($points <= 0) {
            return;
        }

        /*
         * get_subtotal() excludes shipping and tax.
         *
         * This matches the desired behavior:
         * points can discount eligible item subtotal.
         */
        $eligible_total = (float) $cart->get_subtotal();

        $discount = min(
            $points,
            $eligible_total
        );

        if ($discount <= 0) {
            return;
        }

        $cart->add_fee(
            'Points Discount',
            -$discount,
            false
        );
    }

    /**
     * Hold points when an order is created.
     *
     * We do not deduct the balance yet.
     */
    public static function hold_points(
        $user_id,
        $order_id,
        $points
    ) {
        global $wpdb;

        $user_id  = absint($user_id);
        $order_id = absint($order_id);
        $points   = (int) $points;

        if (
            !$user_id ||
            !$order_id ||
            $points <= 0
        ) {
            return false;
        }

        $accounts_table    = CN_DB::loyalty_accounts_table();
        $reservation_table = CN_DB::reservations_table();

        $wpdb->query('START TRANSACTION');

        try {

            /*
             * Lock account.
             */
            $account = $wpdb->get_row(
                $wpdb->prepare(
                    "
                    SELECT *
                    FROM {$accounts_table}
                    WHERE user_id = %d
                    FOR UPDATE
                    ",
                    $user_id
                )
            );

            if (!$account) {
                throw new Exception(
                    'Loyalty account does not exist.'
                );
            }

            /*
             * Prevent duplicate reservation.
             */
            $existing = $wpdb->get_var(
                $wpdb->prepare(
                    "
                    SELECT id
                    FROM {$reservation_table}
                    WHERE order_id = %d
                    LIMIT 1
                    ",
                    $order_id
                )
            );

            if ($existing) {
                throw new Exception(
                    'Reward reservation already exists.'
                );
            }

            /*
             * Calculate currently available balance
             * while the account is locked.
             */
            $held = $wpdb->get_var(
                $wpdb->prepare(
                    "
                    SELECT COALESCE(SUM(points), 0)
                    FROM {$reservation_table}
                    WHERE user_id = %d
                    AND status = 'held'
                    ",
                    $user_id
                )
            );

            $balance   = (int) $account->points_balance;
            $available = max(
                0,
                $balance - (int) $held
            );

            if ($available < $points) {
                throw new Exception(
                    'Insufficient available reward points.'
                );
            }

            $inserted = $wpdb->insert(
                $reservation_table,
                [
                    'user_id'    => $user_id,
                    'order_id'   => $order_id,
                    'points'     => $points,
                    'status'     => 'held',
                    'created_at' => current_time('mysql'),
                ],
                [
                    '%d',
                    '%d',
                    '%d',
                    '%s',
                    '%s',
                ]
            );

            if ($inserted === false) {
                throw new Exception(
                    'Unable to create reward reservation.'
                );
            }

            $wpdb->query('COMMIT');

            return true;

        } catch (Throwable $e) {

            $wpdb->query('ROLLBACK');

            error_log(
                'Candy Nail Redemption Error: ' .
                $e->getMessage()
            );

            return false;
        }
    }

    /**
     * Convert a held reservation into an actual redemption.
     *
     * This deducts the account balance and creates the ledger entry
     * atomically.
     */
    public static function redeem_order_points($order_id)
    {
        global $wpdb;

        $order_id = absint($order_id);

        if (!$order_id) {
            return false;
        }

        $reservation_table = CN_DB::reservations_table();

        $reservation = $wpdb->get_row(
            $wpdb->prepare(
                "
                SELECT *
                FROM {$reservation_table}
                WHERE order_id = %d
                AND status = 'held'
                LIMIT 1
                ",
                $order_id
            )
        );

        if (!$reservation) {

            /*
             * Already redeemed is considered success.
             */
            $existing = $wpdb->get_var(
                $wpdb->prepare(
                    "
                    SELECT id
                    FROM " . CN_DB::points_table() . "
                    WHERE order_id = %d
                    AND type = 'redeem'
                    LIMIT 1
                    ",
                    $order_id
                )
            );

            return (bool) $existing;
        }

        $points = (int) $reservation->points;

        if ($points <= 0) {
            return false;
        }

        /*
         * First mutate balance.
         */
        $success = CN_Loyalty_Manager::adjust_balance(
            $reservation->user_id,
            -$points,
            'redeem',
            $order_id,
            'REDEEM-' . $order_id,
            'Points redeemed for order #' . $order_id
        );

        if (!$success) {
            return false;
        }

        /*
         * Convert reservation.
         */
        $updated = $wpdb->update(
            $reservation_table,
            [
                'status'       => 'converted',
                'converted_at' => current_time('mysql'),
            ],
            [
                'id'     => $reservation->id,
                'status' => 'held',
            ],
            [
                '%s',
                '%s',
            ],
            [
                '%d',
                '%s',
            ]
        );

        return $updated !== false;
    }

    /**
     * Backwards-compatible wrapper.
     */
    public static function convert_hold($order_id)
    {
        return self::redeem_order_points($order_id);
    }

    /**
     * Backwards-compatible wrapper.
     */
    public static function create_redeem_transaction($order_id)
    {
        return self::redeem_order_points($order_id);
    }

    /**
     * Release a held reservation.
     */
    public static function release_hold($order_id)
    {
        global $wpdb;

        $order_id = absint($order_id);

        if (!$order_id) {
            return false;
        }

        $table = CN_DB::reservations_table();

        $updated = $wpdb->query(
            $wpdb->prepare(
                "
                UPDATE {$table}
                SET
                    status = 'released',
                    released_at = %s
                WHERE order_id = %d
                AND status = 'held'
                ",
                current_time('mysql'),
                $order_id
            )
        );

        /*
         * Already released = success.
         */
        if ($updated === 0) {

            $exists = $wpdb->get_var(
                $wpdb->prepare(
                    "
                    SELECT id
                    FROM {$table}
                    WHERE order_id = %d
                    AND status = 'released'
                    LIMIT 1
                    ",
                    $order_id
                )
            );

            return (bool) $exists;
        }

        return $updated !== false;
    }

    /**
     * Return redeemed points after a refund.
     */
    public static function refund_redeemed_points($order_id)
    {
        global $wpdb;

        $order_id = absint($order_id);

        if (!$order_id) {
            return false;
        }

        $points_table = CN_DB::points_table();

        $redeem = $wpdb->get_row(
            $wpdb->prepare(
                "
                SELECT *
                FROM {$points_table}
                WHERE order_id = %d
                AND type = 'redeem'
                LIMIT 1
                ",
                $order_id
            )
        );

        if (!$redeem) {
            return false;
        }

        $reference = 'REFUND-REDEEM-' . $order_id;

        /*
         * If already returned, success.
         */
        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "
                SELECT id
                FROM {$points_table}
                WHERE user_id = %d
                AND reference = %s
                LIMIT 1
                ",
                $redeem->user_id,
                $reference
            )
        );

        if ($existing) {
            return true;
        }

        $points = abs((int) $redeem->points);

        if ($points <= 0) {
            return false;
        }

        return CN_Loyalty_Manager::adjust_balance(
            $redeem->user_id,
            $points,
            'refund',
            $order_id,
            $reference,
            'Points returned from refunded order #' . $order_id
        );
    }

    /**
     * Reverse earned points after refund.
     *
     * Supports full and partial refunds.
     */
    public static function refund_earned_points(
        $order_id,
        $refund_id
    ) {
        global $wpdb;

        $points_table = CN_DB::points_table();

        $order_id = absint($order_id);
        $refund_id = absint($refund_id);

        if (!$order_id || !$refund_id) {
            return false;
        }

        $order = wc_get_order($order_id);
        $refund = wc_get_order($refund_id);

        if (
            !$order ||
            !$refund ||
            !($refund instanceof WC_Order_Refund)
        ) {
            return false;
        }

        $earned_points =
            CN_Reward_Service::calculate_points($order);

        if ($earned_points <= 0) {
            return false;
        }

        /*
         * Calculate cumulative refunded eligible value.
         */
        $original_total =
            CN_Reward_Service::get_order_eligible_total($order);

        if ($original_total <= 0) {
            return false;
        }

        $refunded_total = 0;

        /*
         * WooCommerce refund records accumulate over time.
         */
        foreach ($order->get_refunds() as $existing_refund) {

            foreach (
                $existing_refund->get_items('line_item')
                as $item
            ) {
                if (!($item instanceof WC_Order_Item_Product)) {
                    continue;
                }

                $refunded_total += abs(
                    (float) $item->get_total()
                );
            }
        }

        $ratio = min(
            1,
            $refunded_total / $original_total
        );

        $should_have_reversed =
            (int) floor(
                $earned_points * $ratio
            );

        /*
         * Find how many earned points have already
         * been reversed for this order.
         */
        $already_reversed = $wpdb->get_var(
            $wpdb->prepare(
                "
                SELECT COALESCE(
                    SUM(ABS(points)),
                    0
                )
                FROM {$points_table}
                WHERE order_id = %d
                AND type = 'refund_earn'
                ",
                $order_id
            )
        );

        $already_reversed = (int) $already_reversed;

        $points_to_reverse =
            $should_have_reversed - $already_reversed;

        if ($points_to_reverse <= 0) {
            return true;
        }

        $reference =
            'REFUND-EARN-' . $refund_id;

        return CN_Loyalty_Manager::adjust_balance(
            $order->get_user_id(),
            -$points_to_reverse,
            'refund_earn',
            $order_id,
            $reference,
            'Earned points reversed from refund #' . $refund_id
        );
    }
}