<?php

namespace HoseinMomeni\MahexWoo\V1;

final class Config {
	public const OPTION = 'hm_mahex_v1_settings';
	public const RULES = 'hm_mahex_v1_rules';
	public const SAVED_FILTERS = 'hm_mahex_v1_saved_filters';
	public const DASHBOARD_WIDGETS = 'hm_mahex_v1_dashboard_widgets';

	public static function defaults(): array {
		return array(
			'safe_mode' => true,
			'large_store_mode' => true,
			'profiler_enabled' => false,
			'sensitive_lock' => true,
			'feature_pricing' => true,
			'feature_packing' => true,
			'feature_inventory' => true,
			'feature_operations' => true,
			'feature_customer' => true,
			'feature_reporting' => true,
			'min_shipping_margin_irr' => 0,
			'margin_percent' => 0,
			'margin_fixed_irr' => 0,
			'margin_tiers' => '',
			'max_customer_shipping_irr' => 0,
			'subsidy_percent' => 0,
			'customer_share_percent' => 100,
			'rounding_step_irr' => 1000,
			'psychological_ending_irr' => 0,
			'packing_waste_percent' => 10,
			'packing_max_units' => 500,
			'critical_stock_threshold' => 3,
			'label_font' => 'Tahoma',
			'label_watermark' => '',
			'label_fields' => 'recipient,destination,address,phone,barcode,items,weight,eta,order',
			'label_conditional_cod' => true,
			'a4_columns' => 2,
			'allow_customer_label' => false,
			'account_shipping_card' => true,
			'delivery_preferences' => true,
			'preferred_time' => true,
			'address_book' => true,
			'reorder_shipping' => true,
			'print_queue_enabled' => true,
			'packing_verification' => true,
			'barcode_verification' => false,
			'auto_ready_to_ship' => true,
			'order_columns' => 'order,customer,status,city,total,quick',
			'activity_retention_days' => 180,
			'profile_retention_days' => 30,
			'backup_limit' => 10,
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
		if ( ! is_numeric( $value ) ) return $default;
		return max( $min, min( $max, (int) $value ) );
	}

	public static function float( string $key, float $default = 0.0, float $min = 0.0, float $max = PHP_FLOAT_MAX ): float {
		$value = self::get( $key, $default );
		if ( ! is_numeric( $value ) ) return $default;
		$value = (float) $value;
		return is_finite( $value ) ? max( $min, min( $max, $value ) ) : $default;
	}

	public static function sanitize( mixed $input ): array {
		$input = is_array( $input ) ? $input : array();
		$bool = static fn( string $key ): bool => ! empty( $input[ $key ] );
		$text = static fn( string $key, int $max = 10000 ): string => substr( sanitize_textarea_field( is_scalar( $input[ $key ] ?? '' ) ? (string) $input[ $key ] : '' ), 0, $max );
		$number = static function( string $key, float $default, float $min, float $max ) use ( $input ): float {
			$value = $input[ $key ] ?? $default;
			if ( ! is_numeric( $value ) ) return $default;
			$value = (float) $value;
			return is_finite( $value ) ? max( $min, min( $max, $value ) ) : $default;
		};
		$font = sanitize_text_field( (string) ( $input['label_font'] ?? 'Tahoma' ) );
		if ( ! in_array( $font, array( 'Tahoma', 'Arial', 'Vazirmatn', 'IRANSansX', 'sans-serif' ), true ) ) $font = 'Tahoma';
		return array(
			'safe_mode' => $bool( 'safe_mode' ),
			'large_store_mode' => $bool( 'large_store_mode' ),
			'profiler_enabled' => $bool( 'profiler_enabled' ),
			'sensitive_lock' => $bool( 'sensitive_lock' ),
			'feature_pricing' => $bool( 'feature_pricing' ),
			'feature_packing' => $bool( 'feature_packing' ),
			'feature_inventory' => $bool( 'feature_inventory' ),
			'feature_operations' => $bool( 'feature_operations' ),
			'feature_customer' => $bool( 'feature_customer' ),
			'feature_reporting' => $bool( 'feature_reporting' ),
			'min_shipping_margin_irr' => (int) round( $number( 'min_shipping_margin_irr', 0, 0, 1000000000000 ) ),
			'margin_percent' => $number( 'margin_percent', 0, -100, 1000 ),
			'margin_fixed_irr' => (int) round( $number( 'margin_fixed_irr', 0, -1000000000000, 1000000000000 ) ),
			'margin_tiers' => $text( 'margin_tiers' ),
			'max_customer_shipping_irr' => (int) round( $number( 'max_customer_shipping_irr', 0, 0, 1000000000000 ) ),
			'subsidy_percent' => $number( 'subsidy_percent', 0, 0, 100 ),
			'customer_share_percent' => $number( 'customer_share_percent', 100, 0, 100 ),
			'rounding_step_irr' => max( 1, (int) round( $number( 'rounding_step_irr', 1000, 1, 100000000 ) ) ),
			'psychological_ending_irr' => max( 0, (int) round( $number( 'psychological_ending_irr', 0, 0, 99999999 ) ) ),
			'packing_waste_percent' => $number( 'packing_waste_percent', 10, 0, 80 ),
			'packing_max_units' => max( 1, (int) round( $number( 'packing_max_units', 500, 1, 5000 ) ) ),
			'critical_stock_threshold' => max( 0, (int) round( $number( 'critical_stock_threshold', 3, 0, 100000 ) ) ),
			'label_font' => $font,
			'label_watermark' => esc_url_raw( trim( (string) ( $input['label_watermark'] ?? '' ) ) ),
			'label_fields' => preg_replace( '/[^a-z_,]/', '', strtolower( (string) ( $input['label_fields'] ?? '' ) ) ) ?: '',
			'label_conditional_cod' => $bool( 'label_conditional_cod' ),
			'a4_columns' => max( 1, min( 4, absint( $input['a4_columns'] ?? 2 ) ) ),
			'allow_customer_label' => $bool( 'allow_customer_label' ),
			'account_shipping_card' => $bool( 'account_shipping_card' ),
			'delivery_preferences' => $bool( 'delivery_preferences' ),
			'preferred_time' => $bool( 'preferred_time' ),
			'address_book' => $bool( 'address_book' ),
			'reorder_shipping' => $bool( 'reorder_shipping' ),
			'print_queue_enabled' => $bool( 'print_queue_enabled' ),
			'packing_verification' => $bool( 'packing_verification' ),
			'barcode_verification' => $bool( 'barcode_verification' ),
			'auto_ready_to_ship' => $bool( 'auto_ready_to_ship' ),
			'order_columns' => preg_replace( '/[^a-z_,]/', '', strtolower( (string) ( $input['order_columns'] ?? 'order,customer,status,city,total,quick' ) ) ) ?: 'order,customer,status,city,total,quick',
			'activity_retention_days' => max( 7, min( 3650, absint( $input['activity_retention_days'] ?? 180 ) ) ),
			'profile_retention_days' => max( 1, min( 365, absint( $input['profile_retention_days'] ?? 30 ) ) ),
			'backup_limit' => max( 1, min( 50, absint( $input['backup_limit'] ?? 10 ) ) ),
		);
	}

	public static function flags(): array {
		$s = self::all();
		return array(
			'pricing' => ! empty( $s['feature_pricing'] ),
			'packing' => ! empty( $s['feature_packing'] ),
			'inventory' => ! empty( $s['feature_inventory'] ),
			'operations' => ! empty( $s['feature_operations'] ),
			'customer' => ! empty( $s['feature_customer'] ),
			'reporting' => ! empty( $s['feature_reporting'] ),
		);
	}
}
