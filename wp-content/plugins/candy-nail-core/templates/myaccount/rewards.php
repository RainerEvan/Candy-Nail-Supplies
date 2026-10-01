<?php

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="cn-rewards">

    <h2>My Rewards</h2>

    <p>
        Available Rewards:
        <strong>
            <?php echo esc_html(number_format_i18n($balance)); ?>
        </strong>
    </p>

    <h3>Transaction History</h3>

    <?php if (empty($transactions)) : ?>

        <p>No rewards transactions yet.</p>

    <?php else : ?>

        <table class="shop_table shop_table_responsive">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Points</th>
                    <th>Remaining</th>
                    <th>Expires</th>
                </tr>
            </thead>

            <tbody>

                <?php foreach ($transactions as $transaction) : ?>

                    <tr>
                        <td>
                            <?php
                            echo esc_html(
                                wp_date(
                                    get_option('date_format'),
                                    strtotime($transaction->created_at)
                                )
                            );
                            ?>
                        </td>

                        <td>
                            <?php echo esc_html(ucfirst($transaction->type)); ?>
                        </td>

                        <td>
                            <?php echo esc_html(number_format_i18n($transaction->points)); ?>
                        </td>

                        <td>
                            <?php echo esc_html(number_format_i18n($transaction->remaining_points)); ?>
                        </td>

                        <td>
                            <?php
                            echo $transaction->expires_at
                                ? esc_html(
                                    wp_date(
                                        get_option('date_format'),
                                        strtotime($transaction->expires_at)
                                    )
                                )
                                : '—';
                            ?>
                        </td>
                    </tr>

                <?php endforeach; ?>

            </tbody>
        </table>

    <?php endif; ?>

</div>