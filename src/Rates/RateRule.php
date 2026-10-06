<?php

namespace HoseinMomeni\MahexWoo\Rates;

use HoseinMomeni\MahexWoo\Domain\Money;
use InvalidArgumentException;

final class RateRule {
	public const FIXED = 'fixed';
	public const SURCHARGE = 'surcharge';

	public function __construct(
		public readonly string $id,
		public readonly int $priority,
		public readonly string $mode,
		public readonly Money $amount,
		public readonly ?string $province = null,
		public readonly ?string $city = null
	) {
		if ( ! in_array( $mode, array( self::FIXED, self::SURCHARGE ), true ) ) {
			throw new InvalidArgumentException( 'Unsupported rate rule mode.' );
		}
	}

	public function matches( string $province, string $city ): bool {
		$province = self::normalizeLocation( $province );
		$city     = self::normalizeLocation( $city );
		return ( null === $this->province || self::normalizeLocation( $this->province ) === $province )
			&& ( null === $this->city || self::normalizeLocation( $this->city ) === $city );
	}

	private static function normalizeLocation( string $value ): string {
		$value = strtr( trim( $value ), array( 'ي' => 'ی', 'ى' => 'ی', 'ك' => 'ک', "\u{200C}" => ' ' ) );
		$value = preg_replace( '/\s+/u', ' ', $value );
		return trim( (string) $value );
	}
}
