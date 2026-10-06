<?php

namespace HoseinMomeni\MahexWoo\Domain;

use InvalidArgumentException;

final class PersianNumberNormalizer {
	private const DIGITS = array(
		'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
		'۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
		'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
		'٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
	);

	public static function normalize( string $value ): string {
		return strtr( $value, self::DIGITS + array( '٫' => '.', '٬' => ',', '−' => '-' ) );
	}

	public static function integer( string $value ): int {
		$normalized = str_replace( array( ',', ' ', "\xC2\xA0" ), '', trim( self::normalize( $value ) ) );
		if ( 1 !== preg_match( '/^-?\d+$/D', $normalized ) ) {
			throw new InvalidArgumentException( 'Value is not a valid integer.' );
		}
		$validated = filter_var( $normalized, FILTER_VALIDATE_INT );
		if ( false === $validated ) {
			throw new InvalidArgumentException( 'Integer is outside the supported range.' );
		}
		return $validated;
	}
}
