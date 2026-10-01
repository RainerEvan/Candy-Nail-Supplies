<?php

if (!defined('ABSPATH')) {
    exit;
}

class CN_Loyalty_Expiration
{
    const CRON_HOOK = 'cn_loyalty_daily_expiration';

    public static function init()
    {
        add_action(
            self::CRON_HOOK,
            [self::class, 'run']
        );
    }

    /**
     * Run once per year.
     *
     * WP-Cron executes daily, but we only actually expire
     * points once each year.
     */
    public static function run()
    {
        $year = (int) current_time('Y');

        /*
         * Don't expire until January.
         */
        if (
            (int) current_time('n') !== 1
        ) {
            return;
        }

        $last_run =
            (int) get_option(
                'cn_loyalty_last_expiration_year',
                0
            );

        if ($last_run >= $year) {
            return;
        }

        /*
         * Get all loyalty accounts.
         */
        global $wpdb;

        $accounts_table =
            CN_DB::loyalty_accounts_table();

        $accounts = $wpdb->get_results(
            "
            SELECT user_id, points_balance
            FROM {$accounts_table}
            WHERE points_balance > 0
            "
        );

        foreach ($accounts as $account) {

            self::expire_account(
                (int) $account->user_id,
                $year
            );
        }

        update_option(
            'cn_loyalty_last_expiration_year',
            $year,
            false
        );
    }

    /**
     * Expire 20% of currently available points.
     */
    private static function expire_account(
        $user_id,
        $year
    ) {
        global $wpdb;

        $accounts_table =
            CN_DB::loyalty_accounts_table();

        $reservation_table =
            CN_DB::reservations_table();

        /*
         * Lock account while calculating expiration.
         */
        $wpdb->query('START TRANSACTION');

        try {

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
                $wpdb->query('ROLLBACK');
                return;
            }

            $balance =
                (int) $account->points_balance;

            if ($balance <= 0) {
                $wpdb->query('COMMIT');
                return;
            }

            /*
             * Held points are protected.
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

            $available = max(
                0,
                $balance - (int) $held
            );

            if ($available <= 0) {
                $wpdb->query('COMMIT');
                return;
            }

            /*
             * 20% of available balance.
             */
            $expire_points = (int) floor(
                $available * 0.20
            );

            if ($expire_points <= 0) {
                $wpdb->query('COMMIT');
                return;
            }

            /*
             * We cannot call adjust_balance here because
             * this transaction already owns the account lock.
             *
             * Perform the ledger mutation directly.
             */
            $new_balance =
                $balance - $expire_points;

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
                throw new Exception(
                    'Unable to update expiration balance.'
                );
            }

            $reference =
                'EXPIRE-' . $year;

            /*
             * Avoid duplicate yearly transaction.
             */
            $points_table =
                CN_DB::points_table();

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
                /*
                 * This should only happen in an unusual retry scenario.
                 * Roll back the balance update.
                 */
                throw new Exception(
                    'Expiration transaction already exists.'
                );
            }

            $inserted = $wpdb->insert(
                $points_table,
                [
                    'user_id'    => $user_id,
                    'type'       => 'expire',
                    'points'     => -$expire_points,
                    'reference'  => $reference,
                    'notes'      =>
                        'Annual expiration for ' . $year,
                    'status'     => 'active',
                    'created_at' =>
                        current_time('mysql'),
                ],
                [
                    '%d',
                    '%s',
                    '%d',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                ]
            );

            if ($inserted === false) {
                throw new Exception(
                    'Unable to create expiration transaction.'
                );
            }

            $wpdb->query('COMMIT');

        } catch (Throwable $e) {

            $wpdb->query('ROLLBACK');

            error_log(
                'Candy Nail Loyalty Expiration Error: ' .
                $e->getMessage()
            );
        }
    }
}