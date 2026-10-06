<?php

namespace HoseinMomeni\MahexWoo\Enterprise;

final class Config {
	public const OPTION = 'hm_mahex_enterprise_settings';
	public const WAREHOUSES = 'hm_mahex_warehouses';

	public static function defaults(): array {
		return array(
			'enabled' => true,
			'multi_warehouse' => true,
			'service_policy' => 'balanced',
			'service_choices' => false,
			'local_services' => "economy|اقتصادی|3|0|0\nexpress|سریع|1|0|250000",
			'excluded_provinces' => '',
			'excluded_cities' => '',
			'max_service_weight_g' => 100000,
			'fallback_enabled' => true,
			'fallback_label' => 'ارسال جایگزین فروشگاه',
			'fallback_cost_irr' => 950000,
			'sla_default_days' => 3,
			'sla_rules' => "تهران||1\n||3",
			'holidays' => '',
			'weekend_days' => '5',
			'cutoff_time' => '14:00',
			'require_postcode' => true,
			'require_address2' => false,
			'require_plate' => false,
			'require_phone' => true,
			'validate_postcode' => true,
			'city_aliases' => '',
			'city_province_rules' => '',
			'blacklist_phones' => '',
			'blacklist_postcodes' => '',
			'whitelist_phones' => '',
			'risk_hold_threshold' => 80,
			'queue_enabled' => true,
			'queue_max_attempts' => 5,
			'health_email_enabled' => false,
			'health_email' => '',
			'operator_role_enabled' => true,
		);
	}

	public static function all(): array {
		$stored = function_exists( 'get_option' ) ? get_option( self::OPTION, array() ) : array();
		$defaults = self::defaults();
		$stored = is_array( $stored ) ? array_intersect_key( $stored, $defaults ) : array();
		return array_replace( $defaults, $stored );
	}

	public static function get( string $key, mixed $default = null ): mixed {
		$all = self::all();
		return array_key_exists( $key, $all ) ? $all[ $key ] : $default;
	}

	public static function bool( string $key, bool $default = false ): bool {
		return in_array( self::get( $key, $default ), array( true, 1, '1', 'yes', 'on' ), true );
	}

	public static function int( string $key, int $default = 0, int $min = 0, int $max = PHP_INT_MAX ): int {
		$v = self::get( $key, $default );
		if ( ! is_numeric( $v ) ) return $default;
		return max( $min, min( $max, (int) $v ) );
	}

	public static function csv( string $key ): array {
		$parts = preg_split( '/[,،\n\r]+/u', (string) self::get( $key, '' ) ) ?: array();
		return array_values( array_unique( array_filter( array_map( static fn( $v ) => trim( sanitize_text_field( $v ) ), $parts ) ) ) );
	}

	public static function sanitize( mixed $input ): array {
		$input = is_array( $input ) ? $input : array();
		$text = static fn( string $k, int $max = 10000 ) => substr( sanitize_textarea_field( (string) ( $input[ $k ] ?? '' ) ), 0, $max );
		$bool = static fn( string $k ) => ! empty( $input[ $k ] );
		$time = preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', (string) ( $input['cutoff_time'] ?? '' ) ) ? (string) $input['cutoff_time'] : '14:00';
		$policy = in_array( (string) ( $input['service_policy'] ?? '' ), array( 'balanced', 'cheapest', 'fastest' ), true ) ? (string) $input['service_policy'] : 'balanced';
		$email = sanitize_email( (string) ( $input['health_email'] ?? '' ) );
		return array(
			'enabled' => $bool( 'enabled' ), 'multi_warehouse' => $bool( 'multi_warehouse' ),
			'service_policy' => $policy, 'service_choices' => $bool( 'service_choices' ),
			'local_services' => $text( 'local_services' ), 'excluded_provinces' => $text( 'excluded_provinces' ), 'excluded_cities' => $text( 'excluded_cities' ),
			'max_service_weight_g' => max( 1, min( 5000000, absint( $input['max_service_weight_g'] ?? 100000 ) ) ),
			'fallback_enabled' => $bool( 'fallback_enabled' ), 'fallback_label' => sanitize_text_field( (string) ( $input['fallback_label'] ?? 'ارسال جایگزین فروشگاه' ) ),
			'fallback_cost_irr' => max( 0, (int) ( $input['fallback_cost_irr'] ?? 950000 ) ),
			'sla_default_days' => max( 0, min( 30, absint( $input['sla_default_days'] ?? 3 ) ) ), 'sla_rules' => $text( 'sla_rules' ), 'holidays' => $text( 'holidays' ),
			'weekend_days' => preg_replace( '/[^1-7, ]/', '', (string) ( $input['weekend_days'] ?? '5' ) ) ?: '5', 'cutoff_time' => $time,
			'require_postcode' => $bool( 'require_postcode' ), 'require_address2' => $bool( 'require_address2' ), 'require_plate' => $bool( 'require_plate' ), 'require_phone' => $bool( 'require_phone' ), 'validate_postcode' => $bool( 'validate_postcode' ),
			'city_aliases' => $text( 'city_aliases' ), 'city_province_rules' => $text( 'city_province_rules' ),
			'blacklist_phones' => preg_replace( '/[^0-9۰-۹٠-٩,،\n\r+ ]/u', '', (string) ( $input['blacklist_phones'] ?? '' ) ) ?: '',
			'blacklist_postcodes' => preg_replace( '/[^0-9۰-۹٠-٩,،\n\r ]/u', '', (string) ( $input['blacklist_postcodes'] ?? '' ) ) ?: '',
			'whitelist_phones' => preg_replace( '/[^0-9۰-۹٠-٩,،\n\r+ ]/u', '', (string) ( $input['whitelist_phones'] ?? '' ) ) ?: '',
			'risk_hold_threshold' => max( 0, min( 100, absint( $input['risk_hold_threshold'] ?? 80 ) ) ),
			'queue_enabled' => $bool( 'queue_enabled' ), 'queue_max_attempts' => max( 1, min( 20, absint( $input['queue_max_attempts'] ?? 5 ) ) ),
			'health_email_enabled' => $bool( 'health_email_enabled' ), 'health_email' => $email,
			'operator_role_enabled' => $bool( 'operator_role_enabled' ),
		);
	}
}
