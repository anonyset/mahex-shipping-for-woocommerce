<?php

namespace HoseinMomeni\MahexWoo\Enterprise;

use HoseinMomeni\MahexWoo\Shipping\PricingSettings;

final class WarehouseRepository {
	/** @return array<string,array<string,mixed>> */
	public static function all(): array {
		$rows = get_option( Config::WAREHOUSES, array() );
		if ( is_array( $rows ) && $rows ) return $rows;
		$base = get_option( 'hm_mahex_settings', array() );
		$base = is_array( $base ) ? $base : array();
		return array( 'default' => array(
			'id' => 'default', 'name' => 'انبار اصلی', 'province' => PricingSettings::province_name( (string) ( $base['origin_state'] ?? '' ) ),
			'city' => sanitize_text_field( (string) ( $base['origin_city'] ?? '' ) ), 'address' => sanitize_text_field( (string) ( $base['origin_address'] ?? '' ) ),
			'postcode' => preg_replace( '/\D/', '', (string) ( $base['postal_code'] ?? '' ) ) ?: '', 'lat' => 0.0, 'lng' => 0.0, 'prep_days' => 0,
			'coverage_provinces' => array(), 'coverage_cities' => array(), 'enabled' => true,
		) );
	}

	public static function get( string $id ): ?array { $all = self::all(); return isset( $all[ $id ] ) && is_array( $all[ $id ] ) ? $all[ $id ] : null; }

	public function save( array $rows ): void { update_option( Config::WAREHOUSES, self::sanitizeRows( $rows ), false ); }

	/** @return array<string,array<string,mixed>> */
	public static function sanitizeRows( mixed $input ): array {
		$out = array();
		if ( ! is_array( $input ) ) return $out;
		foreach ( array_slice( $input, 0, 50 ) as $row ) {
			if ( ! is_array( $row ) ) continue;
			$id = sanitize_key( (string) ( $row['id'] ?? '' ) );
			$name = sanitize_text_field( (string) ( $row['name'] ?? '' ) );
			if ( '' === $id || '' === $name ) continue;
			$out[ $id ] = array(
				'id' => $id, 'name' => $name, 'province' => sanitize_text_field( (string) ( $row['province'] ?? '' ) ), 'city' => sanitize_text_field( (string) ( $row['city'] ?? '' ) ),
				'address' => sanitize_text_field( (string) ( $row['address'] ?? '' ) ), 'postcode' => preg_replace( '/\D/', '', (string) ( $row['postcode'] ?? '' ) ) ?: '',
				'lat' => max( -90, min( 90, (float) ( $row['lat'] ?? 0 ) ) ), 'lng' => max( -180, min( 180, (float) ( $row['lng'] ?? 0 ) ) ),
				'prep_days' => max( 0, min( 30, absint( $row['prep_days'] ?? 0 ) ) ),
				'coverage_provinces' => self::list( $row['coverage_provinces'] ?? '' ), 'coverage_cities' => self::list( $row['coverage_cities'] ?? '' ),
				'enabled' => ! empty( $row['enabled'] ),
			);
		}
		return $out;
	}

	private static function list( mixed $value ): array {
		if ( is_array( $value ) ) $parts = $value; else $parts = preg_split( '/[,،\n\r]+/u', (string) $value ) ?: array();
		return array_values( array_unique( array_filter( array_map( static fn( $v ) => trim( sanitize_text_field( (string) $v ) ), $parts ) ) ) );
	}
}
