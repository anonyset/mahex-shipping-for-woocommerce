<?php

namespace HoseinMomeni\MahexWoo\V2;

final class RateCache {
	private const GROUP = 'hm_mahex_rates_v2';
	private const GEN_OPTION = 'hm_mahex_rate_cache_generation';

	public static function key( array $package, int $instanceId, array $extra = array() ): string {
		$destination = is_array( $package['destination'] ?? null ) ? $package['destination'] : array();
		$items = array();
		foreach ( (array) ( $package['contents'] ?? array() ) as $line ) {
			if ( ! is_array( $line ) ) continue;
			$product = $line['data'] ?? null;
			$id = $product instanceof \WC_Product ? $product->get_id() : (int) ( $line['product_id'] ?? 0 );
			$items[] = array( $id, (int) ( $line['quantity'] ?? 0 ) );
		}
		sort( $items );
		$payload = array(
			'generation' => (int) get_option( self::GEN_OPTION, 1 ),
			'instance' => $instanceId,
			'currency' => function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : '',
			'roles' => function_exists('wp_get_current_user') ? array_values((array) wp_get_current_user()->roles) : array(),
			'time_bucket' => function_exists('wp_date') ? wp_date('Y-m-d-H') : gmdate('Y-m-d-H'),
			'destination' => array(
				'country' => (string) ( $destination['country'] ?? '' ),
				'state' => (string) ( $destination['state'] ?? '' ),
				'city' => (string) ( $destination['city'] ?? '' ),
				'postcode' => preg_replace( '/\D/', '', (string) ( $destination['postcode'] ?? '' ) ) ?: '',
			),
			'items' => $items,
			'contents_cost' => round( (float) ( $package['contents_cost'] ?? 0 ), 2 ),
			'extra' => $extra,
		);
		return 'r_' . hash( 'sha256', wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ?: serialize( $payload ) );
	}

	public static function get( string $key ): ?array {
		if ( ! Config::bool( 'rate_cache_enabled', true ) ) return null;
		$found = false;
		$value = wp_cache_get( $key, self::GROUP, false, $found );
		if ( $found && is_array( $value ) ) return $value;
		$value = get_transient( self::GROUP . '_' . $key );
		if ( is_array( $value ) ) {
			wp_cache_set( $key, $value, self::GROUP, Config::int( 'rate_cache_ttl', 300, 30, 3600 ) );
			return $value;
		}
		return null;
	}

	public static function set( string $key, array $value ): void {
		if ( ! Config::bool( 'rate_cache_enabled', true ) ) return;
		$ttl = Config::int( 'rate_cache_ttl', 300, 30, 3600 );
		wp_cache_set( $key, $value, self::GROUP, $ttl );
		set_transient( self::GROUP . '_' . $key, $value, $ttl );
	}

	public static function invalidate(): void {
		$gen = max( 1, (int) get_option( self::GEN_OPTION, 1 ) );
		update_option( self::GEN_OPTION, $gen + 1, false );
		if ( function_exists( 'wp_cache_flush_group' ) ) wp_cache_flush_group( self::GROUP );
	}

	public static function registerInvalidationHooks(): void {
		foreach ( array(
			'hm_mahex_pricing_settings', 'hm_mahex_pro_settings', 'hm_mahex_enterprise_settings',
			'hm_mahex_packaging_profiles', 'hm_mahex_v1_settings', 'hm_mahex_v1_rules',
		) as $option ) {
			add_action( 'update_option_' . $option, static function(): void { self::invalidate(); }, 100 );
			add_action( 'add_option_' . $option, static function(): void { self::invalidate(); }, 100 );
		}
		add_action( 'save_post_product', static function(): void { self::invalidate(); }, 100 );
		add_action( 'woocommerce_update_product', static function(): void { self::invalidate(); }, 100 );
	}
}
