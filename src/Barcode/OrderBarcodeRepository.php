<?php

namespace HoseinMomeni\MahexWoo\Barcode;

/** Allocates and persists one barcode number for each order using Woo CRUD. */
final class OrderBarcodeRepository {
	public const OPTION_SEQUENCE = 'hm_mahex_waybill_sequence';
	public const OPTION_START = 'hm_mahex_waybill_sequence_start';
	public const DEFAULT_START = 100400;
	private const META = '_hm_mahex_barcode_number';

	public function __construct( private readonly NumericBarcodeNumber $numbers = new NumericBarcodeNumber() ) {}

	public function for_order( \WC_Order $order ): string {
		$current = (string) $order->get_meta( self::META, true );
		if ( $this->numbers->is_valid( $current ) ) return $current;
		$number = $this->numbers->from_sequence( $this->reserve_next() );
		$order->update_meta_data( self::META, $number );
		$order->save_meta_data();
		return $number;
	}

	private function reserve_next(): int {
		global $wpdb;
		$start = max( self::DEFAULT_START, (int) get_option( self::OPTION_START, self::DEFAULT_START ) );
		add_option( self::OPTION_SEQUENCE, (string) ( $start - 1 ), '', false );
		$updated = $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = LAST_INSERT_ID(GREATEST(CAST(option_value AS UNSIGNED) + 1, %d)) WHERE option_name = %s AND GREATEST(CAST(option_value AS UNSIGNED) + 1, %d) <= 999999999", $start, self::OPTION_SEQUENCE, $start ) );
		if ( 1 !== $updated ) throw new \RuntimeException( 'Could not reserve a unique waybill sequence.' );
		$sequence = (int) $wpdb->get_var( 'SELECT LAST_INSERT_ID()' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		wp_cache_delete( self::OPTION_SEQUENCE, 'options' );
		if ( $sequence < $start || $sequence > 999999999 ) throw new \OverflowException( 'Waybill sequence is outside the supported range.' );
		return $sequence;
	}
}
