<?php

namespace HoseinMomeni\MahexWoo\Shipping;

/** Canonical, defensive reader for the pricing settings saved by the admin UI. */
final class PricingSettings {
	public const OPTION_NAME = 'hm_mahex_pricing_settings';
	private const IRR_PER_TOMAN = 10;

	/** @return array<string,mixed> */
	public static function all(): array {
		$value = function_exists( 'get_option' ) ? get_option( self::OPTION_NAME, array() ) : array();
		return \HoseinMomeni\MahexWoo\V31\FinanceRates::settings( is_array( $value ) ? $value : array() );
	}

	public static function value( string $key, $default = '' ) {
		$settings = self::all();
		return $settings[ $key ] ?? $default;
	}

	/** Return an amount in the plugin's canonical storage unit (IRR). */
	public static function money( string $key, float $default = 0.0 ): float {
		$value = self::value( $key, $default );
		if ( ! is_numeric( $value ) ) {
			return max( 0.0, $default );
		}
		$value = (float) $value;
		return is_finite( $value ) ? max( 0.0, $value ) : max( 0.0, $default );
	}

	/** Resolve a WooCommerce Iran state code to the Persian province name. */
	public static function province_name( string $province ): string {
		$raw  = trim( $province );
		$code = strtoupper( preg_replace( '/^IR[-_]/i', '', $raw ) ?? '' );
		$aliases = array(
			'KHZ' => 'خوزستان',
			'THR' => 'تهران',
			'ILM' => 'ایلام',
			'BHR' => 'بوشهر',
			'ADL' => 'اردبیل',
			'ESF' => 'اصفهان',
			'YZD' => 'یزد',
			'KRH' => 'کرمانشاه',
			'KRN' => 'کرمان',
			'HDN' => 'همدان',
			'GZN' => 'قزوین',
			'ZJN' => 'زنجان',
			'LRS' => 'لرستان',
			'ABZ' => 'البرز',
			'EAZ' => 'آذربایجان شرقی',
			'WAZ' => 'آذربایجان غربی',
			'CHB' => 'چهارمحال و بختیاری',
			'SKH' => 'خراسان جنوبی',
			'RKH' => 'خراسان رضوی',
			'NKH' => 'خراسان شمالی',
			'SMN' => 'سمنان',
			'FRS' => 'فارس',
			'QHM' => 'قم',
			'KRD' => 'کردستان',
			'KBD' => 'کهگیلویه و بویراحمد',
			'GLS' => 'گلستان',
			'GIL' => 'گیلان',
			'MZN' => 'مازندران',
			'MKZ' => 'مرکزی',
			'HRZ' => 'هرمزگان',
			'SBN' => 'سیستان و بلوچستان',
		);
		return $aliases[ $code ] ?? $raw;
	}

	/**
	 * Return a configured fixed province freight rate, or the provided default if
	 * no positive province override exists.
	 */
	public static function province_rate( string $province, float $default ): float {
		$rates = self::value( 'province_rates', array() );
		if ( ! is_array( $rates ) ) {
			return $default;
		}

		$raw = trim( $province );
		if ( isset( $rates[ $raw ] ) && is_numeric( $rates[ $raw ] ) && (float) $rates[ $raw ] > 0 ) {
			return max( 0.0, (float) $rates[ $raw ] );
		}

		$key = self::province_name( $raw );
		return isset( $rates[ $key ] ) && is_numeric( $rates[ $key ] ) && (float) $rates[ $key ] > 0
			? max( 0.0, (float) $rates[ $key ] )
			: $default;
	}

	public static function enabled( string $key, bool $default = false ): bool {
		return in_array( self::value( $key, $default ? 'yes' : 'no' ), array( true, 1, '1', 'yes', 'on' ), true );
	}

	public static function int_value( string $key, int $default, int $min = 0, int $max = PHP_INT_MAX ): int {
		$value = self::value( $key, $default );
		if ( ! is_scalar( $value ) || ! is_numeric( $value ) ) {
			return $default;
		}
		$value = (int) $value;
		return $value >= $min && $value <= $max ? $value : $default;
	}

	/** Convert canonical IRR to the active WooCommerce store currency. */
	public static function to_store_currency( float $amount_irr ): float {
		$amount_irr = max( 0.0, $amount_irr );
		$currency   = function_exists( 'get_woocommerce_currency' ) ? strtoupper( (string) get_woocommerce_currency() ) : 'IRR';
		return 'IRT' === $currency ? $amount_irr / self::IRR_PER_TOMAN : $amount_irr;
	}

	/** Convert a WooCommerce store-currency amount into canonical IRR. */
	public static function from_store_currency( float $amount ): float {
		$amount   = max( 0.0, $amount );
		$currency = function_exists( 'get_woocommerce_currency' ) ? strtoupper( (string) get_woocommerce_currency() ) : 'IRR';
		return 'IRT' === $currency ? $amount * self::IRR_PER_TOMAN : $amount;
	}
}
