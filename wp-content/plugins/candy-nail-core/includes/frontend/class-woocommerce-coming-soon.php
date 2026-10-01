<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CN_WooCommerce_Coming_Soon {

    private const PAGE_ID = 4376;

    public static function init() {

        add_filter(
            'render_block_woocommerce/coming-soon',
            [ self::class, 'render_elementor_content' ],
            10,
            2
        );
    }

    public static function render_elementor_content( $block_content, $block ) {

        if ( ! get_post( self::PAGE_ID ) ) {
            return $block_content;
        }

        if ( ! class_exists( '\Elementor\Plugin' ) ) {
            return $block_content;
        }

        return \Elementor\Plugin::$instance
            ->frontend
            ->get_builder_content_for_display( self::PAGE_ID );
    }
}