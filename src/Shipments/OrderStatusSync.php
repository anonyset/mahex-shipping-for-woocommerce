<?php

namespace HoseinMomeni\MahexWoo\Shipments;

use HoseinMomeni\MahexWoo\Pro\FeatureSettings;

final class OrderStatusSync {
	public static function apply( \WC_Order $order, Shipment $shipment ): void {
		if ( ! FeatureSettings::bool( 'sync_order_status' ) ) return;
		$map = self::mappings();
		$target = $map[ $shipment->status ] ?? '';
		if ( '' === $target ) return;
		$target = preg_replace( '/^wc-/', '', sanitize_key( $target ) ) ?: '';
		if ( '' === $target || $order->has_status( $target ) ) return;
		$allowed = array_map(
			static fn( string $status ): string => preg_replace( '/^wc-/', '', $status ) ?: $status,
			array_keys( wc_get_order_statuses() )
		);
		if ( ! in_array( $target, $allowed, true ) ) return;
		$order->update_status( $target, sprintf( 'Mahex shipment status synced: %s', $shipment->status ), false );
	}

	/** @return array<string,string> */
	public static function mappings(): array {
		$defaults = array(
			'created' => '',
			'picked_up' => 'processing',
			'in_transit' => 'processing',
			'out_for_delivery' => 'processing',
			'delivered' => 'completed',
			'returned' => 'on-hold',
			'failed' => 'on-hold',
			'canceled' => 'cancelled',
		);
		$raw = (string) FeatureSettings::get( 'order_status_mappings', '' );
		if ( '' === trim( $raw ) ) return $defaults;
		$out = $defaults;
		foreach ( preg_split( '/\r\n|\r|\n/', $raw ) ?: array() as $line ) {
			$parts = array_map( 'trim', explode( '|', $line, 2 ) );
			if ( 2 !== count( $parts ) ) continue;
			$shipmentStatus = sanitize_key( $parts[0] );
			if ( ! in_array( $shipmentStatus, Shipment::STATUSES, true ) ) continue;
			$out[ $shipmentStatus ] = sanitize_key( $parts[1] );
		}
		return $out;
	}
}
