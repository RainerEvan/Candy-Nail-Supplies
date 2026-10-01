<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/database/class-db.php';
require_once __DIR__ . '/database/class-db-installer.php';

require_once __DIR__ . '/loyalty/class-loyalty-manager.php';
require_once __DIR__ . '/loyalty/class-loyalty-hooks.php';
require_once __DIR__ . '/loyalty/class-loyalty-account.php';
require_once __DIR__ . '/loyalty/class-reward-service.php';
require_once __DIR__ . '/loyalty/class-redemption-service.php';
require_once __DIR__ . '/loyalty/class-redemption-hooks.php';
require_once __DIR__ . '/loyalty/class-loyalty-expiration.php';

require_once __DIR__ . '/frontend/class-woocommerce-account.php';
require_once __DIR__ . '/frontend/class-woocommerce-cart.php';
require_once __DIR__ . '/frontend/class-woocommerce-checkout.php';
require_once __DIR__ . '/frontend/class-woocommerce-coming-soon.php';
require_once __DIR__ . '/frontend/class-woocommerce-coupon.php';
require_once __DIR__ . '/frontend/class-woocommerce-price.php';


CN_Loyalty_Hooks::init();
CN_Loyalty_Account::init();
CN_Redemption_Hooks::init();
CN_Loyalty_Expiration::init();

CN_WooCommerce_Account::init();
CN_WooCommerce_Cart::init();
CN_WooCommerce_Checkout::init();
CN_WooCommerce_Coming_Soon::init();
CN_WooCommerce_Coupon::init();
CN_WooCommerce_Price::init();