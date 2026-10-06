<?php

namespace HoseinMomeni\MahexWoo\Pro;

final class FeatureSettings {
	public const OPTION = 'hm_mahex_pro_settings';

	public static function defaults(): array {
		return array(
			'min_billable_weight_g' => 1000,
			'weight_step_g' => 1000,
			'zone_mode' => 'surcharge',
			'tehran_adjustment' => 0,
			'adjacent_adjustment' => 0,
			'non_adjacent_adjustment' => 0,
			'remote_adjustment' => 0,
			'adjacent_provinces' => 'البرز,مرکزی,قم,قزوین,مازندران,سمنان',
			'remote_cities' => '',
			'remote_postcode_prefixes' => '',
			'postcode_rules' => '',
			'low_cart_fee_threshold_irr' => 0,
			'low_cart_fee_irr' => 0,
			'high_cart_discount_threshold_irr' => 0,
			'high_cart_discount_percent' => 0,
			'vip_roles' => 'customer,vip_customer',
			'vip_discount_percent' => 0,
			'role_adjustments' => '',
			'seasonal_surcharge_percent' => 0,
			'seasonal_start' => '',
			'seasonal_end' => '',
			'carrier_cost_percent' => 80,
			'packaging_low_stock_threshold' => 10,
			'label_template' => 'classic',
			'thermal_size' => '100x150',
			'store_logo_url' => '',
			'auto_create_shipment' => false,
			'debug_rate_log' => false,
		);
	}

	public static function all(): array {
		$stored = function_exists( 'get_option' ) ? get_option( self::OPTION, array() ) : array();
		return array_replace( self::defaults(), is_array( $stored ) ? $stored : array() );
	}

	public static function get( string $key, mixed $default = null ): mixed {
		$all = self::all();
		return array_key_exists( $key, $all ) ? $all[ $key ] : $default;
	}

	public static function bool( string $key, bool $default = false ): bool {
		return in_array( self::get( $key, $default ), array( true, 1, '1', 'yes', 'on' ), true );
	}

	public static function int( string $key, int $default = 0, int $min = 0, int $max = PHP_INT_MAX ): int {
		$value = self::get( $key, $default );
		if ( ! is_scalar( $value ) || ! is_numeric( $value ) ) return $default;
		$value = (int) $value;
		return max( $min, min( $max, $value ) );
	}

	public static function float( string $key, float $default = 0.0, float $min = 0.0, float $max = 1000000000000.0 ): float {
		$value = self::get( $key, $default );
		if ( ! is_scalar( $value ) || ! is_numeric( $value ) ) return $default;
		$value = (float) $value;
		return is_finite( $value ) ? max( $min, min( $max, $value ) ) : $default;
	}

	public static function csv( string $key ): array {
		$value = (string) self::get( $key, '' );
		$parts = preg_split( '/[,،\n\r]+/u', $value ) ?: array();
		$parts = array_map( static fn( $v ) => trim( sanitize_text_field( $v ) ), $parts );
		return array_values( array_unique( array_filter( $parts, static fn( $v ) => '' !== $v ) ) );
	}

	public static function sanitize( mixed $input ): array {
		$input = is_array( $input ) ? $input : array();
		$date = static function( mixed $v ): string {
			$v = is_scalar( $v ) ? trim( (string) $v ) : '';
			return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ) ? $v : '';
		};
		$text = static fn( mixed $v, int $max = 5000 ): string => substr( sanitize_textarea_field( is_scalar( $v ) ? (string) $v : '' ), 0, $max );
		$logo = isset( $input['store_logo_url'] ) ? esc_url_raw( trim( (string) $input['store_logo_url'] ) ) : '';
		$one = static fn( mixed $v, array $allowed, string $default ): string => in_array( (string) $v, $allowed, true ) ? (string) $v : $default;
		return array(
			'min_billable_weight_g' => max( 1, min( 1000000, absint( $input['min_billable_weight_g'] ?? 1000 ) ) ),
			'weight_step_g' => max( 1, min( 100000, absint( $input['weight_step_g'] ?? 1000 ) ) ),
			'zone_mode' => $one( $input['zone_mode'] ?? '', array( 'surcharge', 'fixed' ), 'surcharge' ),
			'tehran_adjustment' => max( 0, (float) ( $input['tehran_adjustment'] ?? 0 ) ),
			'adjacent_adjustment' => max( 0, (float) ( $input['adjacent_adjustment'] ?? 0 ) ),
			'non_adjacent_adjustment' => max( 0, (float) ( $input['non_adjacent_adjustment'] ?? 0 ) ),
			'remote_adjustment' => max( 0, (float) ( $input['remote_adjustment'] ?? 0 ) ),
			'adjacent_provinces' => $text( $input['adjacent_provinces'] ?? '' ),
			'remote_cities' => $text( $input['remote_cities'] ?? '' ),
			'remote_postcode_prefixes' => preg_replace( '/[^0-9,،\n\r ]/', '', (string) ( $input['remote_postcode_prefixes'] ?? '' ) ) ?: '',
			'postcode_rules' => $text( $input['postcode_rules'] ?? '', 10000 ),
			'low_cart_fee_threshold_irr' => max( 0, (int) ( $input['low_cart_fee_threshold_irr'] ?? 0 ) ),
			'low_cart_fee_irr' => max( 0, (int) ( $input['low_cart_fee_irr'] ?? 0 ) ),
			'high_cart_discount_threshold_irr' => max( 0, (int) ( $input['high_cart_discount_threshold_irr'] ?? 0 ) ),
			'high_cart_discount_percent' => max( 0, min( 100, (float) ( $input['high_cart_discount_percent'] ?? 0 ) ) ),
			'vip_roles' => $text( $input['vip_roles'] ?? '' ),
			'vip_discount_percent' => max( 0, min( 100, (float) ( $input['vip_discount_percent'] ?? 0 ) ) ),
			'role_adjustments' => $text( $input['role_adjustments'] ?? '', 10000 ),
			'seasonal_surcharge_percent' => max( 0, min( 500, (float) ( $input['seasonal_surcharge_percent'] ?? 0 ) ) ),
			'seasonal_start' => $date( $input['seasonal_start'] ?? '' ),
			'seasonal_end' => $date( $input['seasonal_end'] ?? '' ),
			'carrier_cost_percent' => max( 0, min( 100, (float) ( $input['carrier_cost_percent'] ?? 80 ) ) ),
			'packaging_low_stock_threshold' => max( 0, min( 100000, absint( $input['packaging_low_stock_threshold'] ?? 10 ) ) ),
			'label_template' => $one( $input['label_template'] ?? '', array( 'classic', 'compact', 'minimal' ), 'classic' ),
			'thermal_size' => $one( $input['thermal_size'] ?? '', array( '80x100', '100x150' ), '100x150' ),
			'store_logo_url' => $logo,
			'auto_create_shipment' => ! empty( $input['auto_create_shipment'] ),
			'debug_rate_log' => ! empty( $input['debug_rate_log'] ),
		);
	}
}
