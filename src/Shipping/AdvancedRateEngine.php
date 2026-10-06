<?php

namespace HoseinMomeni\MahexWoo\Shipping;

use HoseinMomeni\MahexWoo\Pro\FeatureSettings;

final class AdvancedRateEngine {
	public static function billableWeightGrams( int $grams ): int {
		$minimum = FeatureSettings::int( 'min_billable_weight_g', 1000, 1, 1000000 );
		$step = FeatureSettings::int( 'weight_step_g', 1000, 1, 100000 );
		$grams = max( $minimum, $grams );
		return (int) ( ceil( $grams / $step ) * $step );
	}

	/** @return array{freight:float,breakdown:list<string>} */
	public static function adjust( float $freight, array $package, string $province, string $city, string $postcode ): array {
		$freight = max( 0.0, $freight );
		$breakdown = array();
		$mode = (string) FeatureSettings::get( 'zone_mode', 'surcharge' );
		$zone = self::zone( $province, $city, $postcode );
		$key = $zone . '_adjustment';
		$zone_amount = FeatureSettings::float( $key, 0.0 );
		if ( $zone_amount > 0 ) {
			if ( 'fixed' === $mode ) {
				$freight = $zone_amount;
				$breakdown[] = 'zone:' . $zone . ':fixed=' . (int) $zone_amount;
			} else {
				$freight += $zone_amount;
				$breakdown[] = 'zone:' . $zone . ':+' . (int) $zone_amount;
			}
		}

		foreach ( self::postcodeRules() as $rule ) {
			if ( '' !== $rule['prefix'] && str_starts_with( preg_replace( '/\D/', '', $postcode ) ?: '', $rule['prefix'] ) ) {
				$freight = 'fixed' === $rule['mode'] ? $rule['amount'] : $freight + $rule['amount'];
				$breakdown[] = 'postcode:' . $rule['prefix'] . ':' . $rule['mode'] . '=' . (int) $rule['amount'];
				break;
			}
		}

		$contents = isset( $package['contents_cost'] ) && is_numeric( $package['contents_cost'] ) ? PricingSettings::from_store_currency( max( 0.0, (float) $package['contents_cost'] ) ) : 0.0;
		$low_threshold = FeatureSettings::float( 'low_cart_fee_threshold_irr', 0 );
		$low_fee = FeatureSettings::float( 'low_cart_fee_irr', 0 );
		if ( $low_threshold > 0 && $contents < $low_threshold && $low_fee > 0 ) {
			$freight += $low_fee;
			$breakdown[] = 'low-cart:+' . (int) $low_fee;
		}
		$high_threshold = FeatureSettings::float( 'high_cart_discount_threshold_irr', 0 );
		$high_discount = FeatureSettings::float( 'high_cart_discount_percent', 0, 0, 100 );
		if ( $high_threshold > 0 && $contents >= $high_threshold && $high_discount > 0 ) {
			$freight *= ( 1 - $high_discount / 100 );
			$breakdown[] = 'high-cart:-' . rtrim( rtrim( number_format( $high_discount, 2, '.', '' ), '0' ), '.' ) . '%';
		}

		$roles = array();
		if ( function_exists( 'wp_get_current_user' ) ) {
			$user = wp_get_current_user();
			$roles = is_array( $user->roles ?? null ) ? $user->roles : array();
		}
		$vip_roles = FeatureSettings::csv( 'vip_roles' );
		$vip_discount = FeatureSettings::float( 'vip_discount_percent', 0, 0, 100 );
		if ( $vip_discount > 0 && array_intersect( $roles, $vip_roles ) ) {
			$freight *= ( 1 - $vip_discount / 100 );
			$breakdown[] = 'vip:-' . $vip_discount . '%';
		}
		foreach ( self::roleAdjustments() as $role => $percent ) {
			if ( in_array( $role, $roles, true ) && 0.0 !== $percent ) {
				$freight *= ( 1 + $percent / 100 );
				$breakdown[] = 'role:' . $role . ':' . ( $percent >= 0 ? '+' : '' ) . $percent . '%';
				break;
			}
		}

		$seasonal = FeatureSettings::float( 'seasonal_surcharge_percent', 0, 0, 500 );
		if ( $seasonal > 0 && self::seasonActive() ) {
			$freight *= ( 1 + $seasonal / 100 );
			$breakdown[] = 'seasonal:+' . $seasonal . '%';
		}
		return array( 'freight' => max( 0.0, round( $freight ) ), 'breakdown' => $breakdown );
	}

	public static function zone( string $province, string $city, string $postcode ): string {
		$province = self::norm( $province );
		$cityN = self::norm( $city );
		$post = preg_replace( '/\D/', '', $postcode ) ?: '';
		foreach ( FeatureSettings::csv( 'remote_cities' ) as $remote ) {
			if ( '' !== $remote && str_contains( $cityN, self::norm( $remote ) ) ) return 'remote';
		}
		foreach ( FeatureSettings::csv( 'remote_postcode_prefixes' ) as $prefix ) {
			$prefix = preg_replace( '/\D/', '', $prefix ) ?: '';
			if ( '' !== $prefix && str_starts_with( $post, $prefix ) ) return 'remote';
		}
		if ( 'تهران' === $province ) return 'tehran';
		foreach ( FeatureSettings::csv( 'adjacent_provinces' ) as $adjacent ) {
			if ( self::norm( $adjacent ) === $province ) return 'adjacent';
		}
		return 'non_adjacent';
	}

	private static function postcodeRules(): array {
		$lines = preg_split( '/\r\n|\r|\n/', (string) FeatureSettings::get( 'postcode_rules', '' ) ) ?: array();
		$out = array();
		foreach ( $lines as $line ) {
			$parts = array_map( 'trim', explode( '|', $line ) );
			if ( count( $parts ) < 3 ) continue;
			$prefix = preg_replace( '/\D/', '', $parts[0] ) ?: '';
			$mode = in_array( $parts[1], array( 'fixed', 'surcharge' ), true ) ? $parts[1] : 'surcharge';
			$amount = is_numeric( $parts[2] ) ? max( 0.0, (float) $parts[2] ) : 0.0;
			if ( '' !== $prefix && $amount > 0 ) $out[] = compact( 'prefix', 'mode', 'amount' );
		}
		return $out;
	}

	private static function roleAdjustments(): array {
		$lines = preg_split( '/\r\n|\r|\n/', (string) FeatureSettings::get( 'role_adjustments', '' ) ) ?: array();
		$out = array();
		foreach ( $lines as $line ) {
			$parts = array_map( 'trim', explode( '|', $line ) );
			if ( count( $parts ) >= 2 && preg_match( '/^[a-z0-9_-]+$/', $parts[0] ) && is_numeric( $parts[1] ) ) {
				$out[ $parts[0] ] = max( -100.0, min( 500.0, (float) $parts[1] ) );
			}
		}
		return $out;
	}

	private static function seasonActive(): bool {
		$start = (string) FeatureSettings::get( 'seasonal_start', '' );
		$end = (string) FeatureSettings::get( 'seasonal_end', '' );
		if ( '' === $start && '' === $end ) return true;
		$today = function_exists( 'wp_date' ) ? wp_date( 'Y-m-d' ) : gmdate( 'Y-m-d' );
		if ( '' !== $start && $today < $start ) return false;
		if ( '' !== $end && $today > $end ) return false;
		return true;
	}

	private static function norm( string $text ): string {
		$text = trim( str_replace( array( 'ي', 'ك', 'ۀ', 'ة' ), array( 'ی', 'ک', 'ه', 'ه' ), $text ) );
		return preg_replace( '/\s+/u', ' ', $text ) ?: $text;
	}
}
