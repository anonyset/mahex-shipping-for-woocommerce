<?php

namespace HoseinMomeni\MahexWoo\Rates;

/** Normalizes the price settings shared by the admin and checkout. */
final class PricingSettings {
	public const OPTION_NAME = 'hm_mahex_pricing_settings';
	public const IRR_PER_TOMAN = 10;

	/** @return array<string,mixed> */
	public static function defaults(): array {
		return array(
			'pricing_mode'           => 'manual',
			'allow_customer_choice'  => false,
			'product_availability'    => 'all_products',
			'currency_unit'          => 'irr',
			'manual_base_cost'       => 850000,
			'manual_cost_per_kg'     => 75000,
			'fallback_cost'          => 925000,
			'free_shipping_threshold'=> 0,
			'packaging_mode'         => 'manual',
			'packaging_application'  => 'always',
			'manual_packaging_cost'  => 0,
			'insurance_mode'         => 'manual',
			'insurance_application'  => 'always',
			'manual_insurance_cost'  => 0,
			'volumetric_divisor'     => 5000,
			'fallback_weight_g'      => 500,
			'fallback_length_mm'     => 100,
			'fallback_width_mm'      => 100,
			'fallback_height_mm'     => 100,
			'province_rates'         => array(),
		);
	}

	/**
	 * Sanitize values submitted by the admin UI and persist all money amounts in
	 * canonical rials (IRR). This method must not be used to read already-stored
	 * values because a UI unit of toman intentionally converts inputs ×10.
	 *
	 * @param mixed $input Raw option value from an HTML form.
	 * @return array<string,mixed>
	 */
	public static function sanitize( $input ): array {
		$input       = is_array( $input ) ? $input : array();
		$unitRaw     = is_scalar( $input['currency_unit'] ?? null ) ? (string) $input['currency_unit'] : '';
		$unit        = 'toman' === strtolower( $unitRaw ) ? 'toman' : 'irr';
		$maxInput    = 'toman' === $unit ? intdiv( PHP_INT_MAX, self::IRR_PER_TOMAN ) : PHP_INT_MAX;
		$amount      = static fn ( $value ): int => self::toRials( $value, $unit, $maxInput );
		$provinceRaw = isset( $input['province_rates'] ) && is_array( $input['province_rates'] ) ? $input['province_rates'] : array();
		$province    = array();
		foreach ( $provinceRaw as $name => $value ) {
			$name = self::sanitizeProvince( (string) $name );
			if ( '' !== $name ) {
				$province[ $name ] = $amount( $value );
			}
		}


		return array(
			'pricing_mode'            => 'manual',
			'allow_customer_choice'   => false,
			'product_availability'     => self::oneOf( $input['product_availability'] ?? '', array( 'all_products', 'enabled_only' ), 'all_products' ),
			'currency_unit'           => $unit,
			'manual_base_cost'        => $amount( $input['manual_base_cost'] ?? 0 ),
			'manual_cost_per_kg'      => $amount( $input['manual_cost_per_kg'] ?? 0 ),
			'fallback_cost'           => $amount( $input['fallback_cost'] ?? 0 ),
			'free_shipping_threshold' => $amount( $input['free_shipping_threshold'] ?? 0 ),
			'packaging_mode'          => 'manual',
			'packaging_application'   => self::oneOf( $input['packaging_application'] ?? '', array( 'always', 'required_products', 'never' ), 'always' ),
			'manual_packaging_cost'   => $amount( $input['manual_packaging_cost'] ?? 0 ),
			'insurance_mode'          => 'manual',
			'insurance_application'   => self::oneOf( $input['insurance_application'] ?? '', array( 'always', 'required_products', 'never' ), 'always' ),
			'manual_insurance_cost'   => $amount( $input['manual_insurance_cost'] ?? 0 ),
			'volumetric_divisor'      => self::boundedInt( $input['volumetric_divisor'] ?? 5000, 1000, 50000, 5000 ),
			'fallback_weight_g'       => self::boundedInt( $input['fallback_weight_g'] ?? 500, 1, 1000000, 500 ),
			'fallback_length_mm'      => self::boundedInt( $input['fallback_length_mm'] ?? 100, 1, 10000, 100 ),
			'fallback_width_mm'       => self::boundedInt( $input['fallback_width_mm'] ?? 100, 1, 10000, 100 ),
			'fallback_height_mm'      => self::boundedInt( $input['fallback_height_mm'] ?? 100, 1, 10000, 100 ),
			'province_rates'          => $province,
		);
	}

	/**
	 * Validate values already stored in WordPress. Stored money is always IRR,
	 * therefore this method deliberately never applies the toman multiplier.
	 *
	 * @param mixed $stored
	 * @return array<string,mixed>
	 */
	public static function normalizeStored( $stored ): array {
		$stored   = is_array( $stored ) ? $stored : array();
		$defaults = self::defaults();
		$result   = $defaults;
		$result['pricing_mode']           = 'manual';
		$result['allow_customer_choice']  = false;
		$result['product_availability']    = self::oneOf( $stored['product_availability'] ?? '', array( 'all_products', 'enabled_only' ), 'all_products' );
		$result['currency_unit']          = self::oneOf( strtolower( (string) ( $stored['currency_unit'] ?? '' ) ), array( 'irr', 'toman' ), 'irr' );
		$result['packaging_mode']         = 'manual';
		$result['packaging_application']  = self::oneOf( $stored['packaging_application'] ?? '', array( 'always', 'required_products', 'never' ), 'always' );
		$result['insurance_mode']         = 'manual';
		$result['insurance_application']  = self::oneOf( $stored['insurance_application'] ?? '', array( 'always', 'required_products', 'never' ), 'always' );
		foreach ( array( 'manual_base_cost', 'manual_cost_per_kg', 'fallback_cost', 'free_shipping_threshold', 'manual_packaging_cost', 'manual_insurance_cost' ) as $key ) {
			$result[ $key ] = self::storedAmount( $stored[ $key ] ?? $defaults[ $key ] );
		}
		$result['volumetric_divisor'] = self::boundedInt( $stored['volumetric_divisor'] ?? 5000, 1000, 50000, 5000 );
		$result['fallback_weight_g']  = self::boundedInt( $stored['fallback_weight_g'] ?? 500, 1, 1000000, 500 );
		$result['fallback_length_mm'] = self::boundedInt( $stored['fallback_length_mm'] ?? 100, 1, 10000, 100 );
		$result['fallback_width_mm']  = self::boundedInt( $stored['fallback_width_mm'] ?? 100, 1, 10000, 100 );
		$result['fallback_height_mm'] = self::boundedInt( $stored['fallback_height_mm'] ?? 100, 1, 10000, 100 );

		$result['province_rates'] = array();
		if ( isset( $stored['province_rates'] ) && is_array( $stored['province_rates'] ) ) {
			foreach ( $stored['province_rates'] as $name => $value ) {
				$name = self::sanitizeProvince( (string) $name );
				if ( '' !== $name ) {
					$result['province_rates'][ $name ] = self::storedAmount( $value );
				}
			}
		}
		return $result;
	}

	/** Convert an amount entered in the selected UI unit into canonical rials. */
	public static function toRials( $value, string $unit, ?int $maxInput = null ): int {
		if ( ! is_scalar( $value ) ) {
			return 0;
		}
		$raw = str_replace( array( ',', '٬', ' ' ), '', self::asciiDigits( (string) $value ) );
		if ( ! preg_match( '/^(\d*)(?:\.(\d*))?$/', $raw, $matches ) ) {
			return 0;
		}
		$whole    = ltrim( $matches[1] ?? '', '0' );
		$fraction = $matches[2] ?? '';
		if ( '' === $whole && '' === $fraction ) {
			return 0;
		}
		$maxInput = $maxInput ?? ( 'toman' === $unit ? intdiv( PHP_INT_MAX, self::IRR_PER_TOMAN ) : PHP_INT_MAX );
		$max       = (string) max( 0, $maxInput );
		if ( strlen( $whole ) > strlen( $max ) || ( strlen( $whole ) === strlen( $max ) && strcmp( $whole, $max ) > 0 ) ) {
			$whole = $max;
			$fraction = '';
		}
		$amount = (int) ( '' === $whole ? '0' : $whole );
		if ( 'toman' === $unit ) {
			$tenths = isset( $fraction[0] ) ? (int) $fraction[0] : 0;
			$rials  = $amount * self::IRR_PER_TOMAN;
			return $rials > PHP_INT_MAX - $tenths ? PHP_INT_MAX : $rials + $tenths;
		}
		return $amount;
	}

	/** Convert canonical rials into the selected UI unit. */
	public static function fromRials( int $amount, string $unit ): string {
		$amount = max( 0, $amount );
		if ( 'toman' !== $unit ) {
			return (string) $amount;
		}
		$whole     = intdiv( $amount, self::IRR_PER_TOMAN );
		$remainder = $amount % self::IRR_PER_TOMAN;
		return 0 === $remainder ? (string) $whole : $whole . '.' . $remainder;
	}

	private static function storedAmount( mixed $value ): int {
		if ( ! is_scalar( $value ) || ! is_numeric( $value ) ) {
			return 0;
		}
		$value = (float) $value;
		if ( ! is_finite( $value ) || $value <= 0 ) {
			return 0;
		}
		return (int) min( (float) PHP_INT_MAX, floor( $value ) );
	}

	private static function boundedInt( mixed $value, int $min, int $max, int $default ): int {
		if ( ! is_scalar( $value ) ) {
			return $default;
		}
		$value = self::asciiDigits( trim( (string) $value ) );
		if ( ! preg_match( '/^\d+$/', $value ) ) {
			return $default;
		}
		$number = (int) $value;
		return $number >= $min && $number <= $max ? $number : $default;
	}

	private static function oneOf( mixed $value, array $allowed, string $default ): string {
		$value = is_scalar( $value ) ? (string) $value : '';
		return in_array( $value, $allowed, true ) ? $value : $default;
	}

	private static function sanitizeProvince( string $province ): string {
		$province = trim( preg_replace( '/\s+/u', ' ', strip_tags( $province ) ) );
		return function_exists( 'sanitize_text_field' ) ? sanitize_text_field( $province ) : substr( $province, 0, 80 );
	}

	private static function asciiDigits( string $value ): string {
		return strtr( $value, array(
			'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
			'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
			'٫' => '.',
		) );
	}
}
