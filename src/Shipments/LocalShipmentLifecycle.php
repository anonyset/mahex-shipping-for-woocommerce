<?php

namespace HoseinMomeni\MahexWoo\Shipments;

final class LocalShipmentLifecycle {
	public function create( int $order_id, array $package_snapshot, \DateTimeImmutable $now ): Shipment {
		if ( $order_id < 1 ) {
			throw new \InvalidArgumentException( 'A positive order id is required.' );
		}

		$nonce     = isset( $package_snapshot['_reissue_nonce'] ) ? ':' . (string) $package_snapshot['_reissue_nonce'] : '';
		$parcel    = ':' . (string) ( $package_snapshot['_parcel_index'] ?? 0 ) . ':' . (string) ( $package_snapshot['_warehouse_id'] ?? '' );
		$seed      = strtoupper( substr( hash( 'sha256', 'hm-mahex:' . $order_id . $parcel . $nonce ), 0, 10 ) );
		$timestamp = $now->format( DATE_ATOM );

		return new Shipment(
			'MHX-' . $order_id . '-' . substr( $seed, 0, 6 ),
			'MHX' . str_pad( (string) $order_id, 8, '0', STR_PAD_LEFT ) . substr( $seed, 0, 4 ),
			'MWB-' . $order_id . '-' . substr( $seed, 4, 6 ),
			'created',
			$package_snapshot,
			$timestamp,
			$timestamp
		);
	}

	public function refresh( Shipment $shipment, \DateTimeImmutable $now ): Shipment {
		// Imported carrier statuses must only change through explicit operator input.
		if (!empty($shipment->package_snapshot['_manual_carrier_reference'])) return $shipment;
		$flow = array( 'created', 'picked_up', 'in_transit', 'out_for_delivery', 'delivered' );
		$position = array_search( $shipment->status, $flow, true );
		if ( false === $position ) return $shipment;
		$next = min( count( $flow ) - 1, (int) $position + 1 );
		return new Shipment(
			$shipment->shipment_id,
			$shipment->tracking_code,
			$shipment->waybill_number,
			$flow[ $next ],
			$shipment->package_snapshot,
			$shipment->created_at,
			$now->format( DATE_ATOM ),
			$shipment->revision + ( $next === $position ? 0 : 1 )
		);
	}
}


