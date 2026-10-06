<?php

namespace HoseinMomeni\MahexWoo\Checkout;

/**
 * Declares the storage and checkout surfaces that this plugin actually supports.
 *
 * Shipping methods are consumed by both the classic checkout and the Store API
 * used by Cart/Checkout Blocks. No block-only JavaScript registration is needed
 * unless a custom interactive checkout component is introduced later.
 */
final class Compatibility {
	public static function register(): void {
		add_action( 'before_woocommerce_init', array( self::class, 'declare' ) );
		RatePresenter::register();
	}

	public static function declare(): void {
		$features = '\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil';

		if ( ! class_exists( $features ) || ! method_exists( $features, 'declare_compatibility' ) ) {
			return;
		}

		$plugin_file = defined( 'HM_MAHEX_FILE' ) ? HM_MAHEX_FILE : dirname( __DIR__, 2 ) . '/mahex-shipping-for-woocommerce.php';

		$features::declare_compatibility( 'custom_order_tables', $plugin_file, true );
		$features::declare_compatibility( 'cart_checkout_blocks', $plugin_file, true );
	}
}
