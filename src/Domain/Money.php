<?php

namespace HoseinMomeni\MahexWoo\Domain;

use InvalidArgumentException;
use OverflowException;

final class Money {
	private readonly string $currency;

	public function __construct(
		private readonly int $amount,
		string $currency
	) {
		if ( '' === trim( $currency ) ) {
			throw new InvalidArgumentException( 'Currency must not be empty.' );
		}
		$this->currency = strtoupper( trim( $currency ) );
	}

	public function amount(): int {
		return $this->amount;
	}

	public function currency(): string {
		return $this->currency;
	}

	public function add( self $other ): self {
		$this->assertSameCurrency( $other );
		return new self( self::checkedAdd( $this->amount, $other->amount ), $this->currency );
	}

	public function subtract( self $other ): self {
		$this->assertSameCurrency( $other );
		if ( PHP_INT_MIN === $other->amount ) {
			throw new OverflowException( 'Money subtraction overflow.' );
		}
		return new self( self::checkedAdd( $this->amount, -$other->amount ), $this->currency );
	}

	public function multiply( int $factor ): self {
		return new self( self::checkedMultiply( $this->amount, $factor ), $this->currency );
	}

	private function assertSameCurrency( self $other ): void {
		if ( $this->currency() !== $other->currency() ) {
			throw new InvalidArgumentException( 'Money currencies must match.' );
		}
	}

	private static function checkedAdd( int $left, int $right ): int {
		if ( ( $right > 0 && $left > PHP_INT_MAX - $right ) || ( $right < 0 && $left < PHP_INT_MIN - $right ) ) {
			throw new OverflowException( 'Money addition overflow.' );
		}
		return $left + $right;
	}

	private static function checkedMultiply( int $left, int $right ): int {
		if ( 0 === $left || 0 === $right ) {
			return 0;
		}
		$overflow = ( $left > 0 && $right > 0 && $left > intdiv( PHP_INT_MAX, $right ) )
			|| ( $left > 0 && $right < 0 && $right < intdiv( PHP_INT_MIN, $left ) )
			|| ( $left < 0 && $right > 0 && $left < intdiv( PHP_INT_MIN, $right ) )
			|| ( $left < 0 && $right < 0 && $left < intdiv( PHP_INT_MAX, $right ) );
		if ( $overflow ) {
			throw new OverflowException( 'Money multiplication overflow.' );
		}
		return $left * $right;
	}
}
