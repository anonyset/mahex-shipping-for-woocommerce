<?php

namespace HoseinMomeni\MahexWoo\Barcode;

/** Creates a stable, validated ten digit identifier from a WooCommerce order id. */
final class NumericBarcodeNumber {
	public function for_order_id( int $order_id ): string {
		if ( $order_id < 1 || $order_id > 999999999 ) {
			throw new \InvalidArgumentException( 'Order id is outside the supported barcode range.' );
		}
		$body = str_pad( (string) $order_id, 9, '0', STR_PAD_LEFT );
		return $body . $this->check_digit( $body );
	}

	public function from_sequence( int $sequence ): string {
		if ( $sequence < 1 || $sequence > 999999999 ) throw new \InvalidArgumentException( 'Sequence is outside the supported barcode range.' );
		$body = str_pad( (string) $sequence, 9, '0', STR_PAD_LEFT );
		return $body . $this->check_digit( $body );
	}

	public function is_valid( string $number ): bool {
		return 1 === preg_match( '/^\d{10}$/D', $number )
			&& (int) $number[9] === $this->check_digit( substr( $number, 0, 9 ) );
	}

	private function check_digit( string $body ): int {
		$sum = 0;
		foreach ( str_split( $body ) as $index => $digit ) {
			$value = (int) $digit * ( 0 === $index % 2 ? 2 : 1 );
			$sum += $value > 9 ? $value - 9 : $value;
		}
		return ( 10 - ( $sum % 10 ) ) % 10;
	}
}
