<?php

namespace HoseinMomeni\MahexWoo\Packaging;

use InvalidArgumentException;
use OverflowException;

final class ConfigurableVolumetricWeightCalculator implements VolumetricWeightCalculatorInterface {
	public function __construct( private readonly int $divisor ) {
		if ( $divisor <= 0 ) {
			throw new InvalidArgumentException( 'Volumetric divisor must be positive.' );
		}
	}

	public function calculateGrams( int $lengthMm, int $widthMm, int $heightMm ): int {
		if ( $lengthMm <= 0 || $widthMm <= 0 || $heightMm <= 0 ) {
			throw new InvalidArgumentException( 'Package dimensions must be positive.' );
		}
		if ( $lengthMm > intdiv( PHP_INT_MAX, $widthMm ) ) {
			throw new OverflowException( 'Package volume overflow.' );
		}
		$area = $lengthMm * $widthMm;
		if ( $area > intdiv( PHP_INT_MAX, $heightMm ) ) {
			throw new OverflowException( 'Package volume overflow.' );
		}
		$volumeMm3 = $area * $heightMm;
		return intdiv( $volumeMm3, $this->divisor ) + ( 0 === $volumeMm3 % $this->divisor ? 0 : 1 );
	}
}
