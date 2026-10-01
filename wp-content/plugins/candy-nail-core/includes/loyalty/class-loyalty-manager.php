<?php

if (!defined('ABSPATH')) {
    exit;
}

class CN_Loyalty_Manager
{
    /**
     * Get the customer's actual account balance.
     *
     * This is the source of truth.
     */
    public static function get_account_balance($user_id)
    {
        global $wpdb;

        $user_id = absint($user_id);

        if (!$user_id) {
            return 0;
        }

        $table = CN_DB::loyalty_accounts_table();

        $balance = $wpdb->get_var(
            $wpdb->prepare(
                "
                SELECT points_balance
                FROM {$table}
                WHERE user_id = %d
                LIMIT 1
                ",
                $user_id
            )
        );

        return $balance !== null ? (int) $balance : 0;
    }

    /**
     * Get points currently available for redemption.
     *
     * Actual balance - points currently held by checkout orders.
     *
     * Negative balances are never redeemable.
     */
    public static function get_balance($user_id)
    {
        global $wpdb;

        $user_id = absint($user_id);

        if (!$user_id) {
            return 0;
        }

        $balance = self::get_account_balance($user_id);

        $reservation_table = CN_DB::reservations_table();

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

        return max(
            0,
            $balance - (int) $held
        );
    }

    /**
     * Get currently held points.
     */
    public static function get_held_points($user_id)
    {
        global $wpdb;

        $user_id = absint($user_id);

        if (!$user_id) {
            return 0;
        }

        $table = CN_DB::reservations_table();

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "
                SELECT COALESCE(SUM(points), 0)
                FROM {$table}
                WHERE user_id = %d
                AND status = 'held'
                ",
                $user_id
            )
        );
    }

    /**
     * Adjust account balance and create a transaction.
     *
     * Positive points = add to balance.
     * Negative points = remove from balance.
     *
     * This method is the ONLY place that should mutate points_balance.
     */
    public static function adjust_balance(
        $user_id,
        $points,
        $type,
        $order_id = null,
        $reference = null,
        $notes = null
    ) {
        global $wpdb;

        $user_id = absint($user_id);
        $points  = (int) $points;
        $order_id = $order_id ? absint($order_id) : null;

        if (!$user_id || $points === 0) {
            return false;
        }

        $accounts_table = CN_DB::loyalty_accounts_table();
        $points_table  = CN_DB::points_table();

        $wpdb->query('START TRANSACTION');

        try {

            /*
             * Ensure account exists.
             */
            $wpdb->query(
                $wpdb->prepare(
                    "
                    INSERT IGNORE INTO {$accounts_table}
                    (
                        user_id,
                        points_balance,
                        created_at,
                        updated_at
                    )
                    VALUES
                    (
                        %d,
                        0,
                        %s,
                        %s
                    )
                    ",
                    $user_id,
                    current_time('mysql'),
                    current_time('mysql')
                )
            );

            /*
             * Lock account row.
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
                throw new Exception('Unable to load loyalty account.');
            }

            /*
             * Idempotency check.
             *
             * A reference uniquely identifies a business event.
             */
            if ($reference) {

                $existing = $wpdb->get_var(
                    $wpdb->prepare(
                        "
                        SELECT id
                        FROM {$points_table}
                        WHERE user_id = %d
                        AND reference = %s
                        LIMIT 1
                        ",
                        $user_id,
                        $reference
                    )
                );

                if ($existing) {
                    $wpdb->query('COMMIT');

                    return true;
                }
            }

            $old_balance = (int) $account->points_balance;
            $new_balance = $old_balance + $points;

            /*
             * We intentionally allow negative balances.
             *
             * This handles the case where earned points were already
             * spent and the original order is later refunded.
             */
            $updated = $wpdb->update(
                $accounts_table,
                [
                    'points_balance' => $new_balance,
                    'updated_at'     => current_time('mysql'),
                ],
                [
                    'user_id' => $user_id,
                ],
                [
                    '%d',
                    '%s',
                ],
                [
                    '%d',
                ]
            );

            if ($updated === false) {
                throw new Exception('Unable to update loyalty balance.');
            }

            /*
             * Record the ledger transaction.
             */
            $inserted = $wpdb->insert(
                $points_table,
                [
                    'user_id'    => $user_id,
                    'order_id'   => $order_id,
                    'type'       => sanitize_key($type),
                    'points'     => $points,
                    'reference'  => $reference,
                    'notes'      => $notes,
                    'status'     => 'active',
                    'created_at' => current_time('mysql'),
                ],
                [
                    '%d',
                    $order_id === null ? null : '%d',
                    '%s',
                    '%d',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                ]
            );

            if ($inserted === false) {
                throw new Exception('Unable to create loyalty transaction.');
            }

            $wpdb->query('COMMIT');

            return true;

        } catch (Throwable $e) {

            $wpdb->query('ROLLBACK');

            error_log(
                'Candy Nail Loyalty Error: ' . $e->getMessage()
            );

            return false;
        }
    }

    /**
     * Get transaction history.
     */
    public static function get_transactions($user_id, $limit = 20)
    {
        global $wpdb;

        $user_id = absint($user_id);
        $limit   = absint($limit);

        if (!$user_id) {
            return [];
        }

        if ($limit === 0) {
            $limit = 20;
        }

        $table = CN_DB::points_table();

        return $wpdb->get_results(
            $wpdb->prepare(
                "
                SELECT *
                FROM {$table}
                WHERE user_id = %d
                ORDER BY created_at DESC, id DESC
                LIMIT %d
                ",
                $user_id,
                $limit
            )
        );
    }
}