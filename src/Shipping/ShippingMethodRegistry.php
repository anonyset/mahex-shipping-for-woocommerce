<?php

namespace HoseinMomeni\MahexWoo\Shipping;

final class ShippingMethodRegistry {
	public static function register(): void {
		add_filter( 'woocommerce_shipping_methods', array( self::class, 'add_method' ) );
	}

	/**
	 * @param array<string, string|object> $methods
	 * @return array<string, string|object>
	 */
	public static function add_method( array $methods ): array {
		// Checking the WooCommerce parent first prevents autoloading the method too early.
		if ( ! class_exists( '\\WC_Shipping_Method', false ) ) {
			return $methods;
		}

		$methods['hm_mahex'] = MahexShippingMethod::class;
		return $methods;
	}
}
