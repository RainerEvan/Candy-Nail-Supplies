<?php

if (!defined('ABSPATH')) {
    exit;
}

/** @var int $balance */
/** @var array $transactions */
?>

<div class="cn-loyalty-points">
    <h2>Loyalty Points</h2>
    <div class="cn-loyalty-summary">
        <div class="cn-loyalty-summary-icon">
            <svg
                xmlns="http://www.w3.org/2000/svg"
                width="32"
                height="32"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.5"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true">
                <path d="M7.05 11.293l4.243 -4.243a2 2 0 0 1 2.828 0l2.829 2.83a2 2 0 0 1 0 2.828l-4.243 4.243a2 2 0 0 1 -2.828 0l-2.829 -2.831a2 2 0 0 1 0 -2.828z" />
                <path d="M16.243 9.172l3.086 -.772a1.5 1.5 0 0 0 .697 -2.516l-2.216 -2.217a1.5 1.5 0 0 0 -2.44 .47l-1.248 2.913" />
                <path d="M9.172 16.243l-.772 3.086a1.5 1.5 0 0 1 -2.516 .697l-2.217 -2.216a1.5 1.5 0 0 1 .47 -2.44l2.913 -1.248" />
            </svg>
        </div>
        <div class="cn-loyalty-summary-content">
            <div class="cn-loyalty-label"> Available points </div>
            <div class="cn-loyalty-balance"> <?php echo esc_html(number_format_i18n($balance)); ?> </div>
        </div>
    </div>
    <h3>Transaction History</h3>
    <?php if (empty($transactions)) : ?>
        <p>No points transactions yet.</p>
    <?php else : ?>
        <table class="shop_table shop_table_responsive">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Points</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transactions as $transaction) : ?>
                    <tr>
                        <td data-title="Date">
                            <?php
                            echo esc_html(
                                wp_date(
                                    get_option('date_format'),
                                    strtotime($transaction->created_at)
                                )
                            );
                            ?>
                        </td>
                        <td data-title="Type">
                            <?php echo esc_html(ucfirst($transaction->type)); ?>
                        </td>
                        <td data-title="Points">
                            <?php $points = (int) $transaction->points;
                            echo esc_html(($points > 0 ? '+' : '') . number_format_i18n($points)); ?>
                        </td>
                        <td data-title="Notes">
                            <?php echo esc_html($transaction->notes); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>