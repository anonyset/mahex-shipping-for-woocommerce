<?php

namespace HoseinMomeni\MahexWoo\Shipments;

defined( 'ABSPATH' ) || exit;

final class OrderShipmentStore {
	public const SHIPMENT_META = '_hm_mahex_shipment';
	public const AUDIT_META    = '_hm_mahex_shipment_audit';
	public const TRACKING_META = '_hm_mahex_tracking_code';
	public const WAYBILL_META  = '_hm_mahex_waybill_number';
	public const STATUS_META   = '_hm_mahex_shipment_status';
	public const MULTI_META    = '_hm_mahex_shipments';
	public const TRACKING_INDEX = '_hm_mahex_tracking_index';

	public function get( \WC_Order $order ): ?Shipment {
		$data = $order->get_meta( self::SHIPMENT_META, true );
		if ( ! is_array( $data ) || empty( $data['shipment_id'] ) ) {
			return null;
		}
		try {
			return Shipment::from_array( $data );
		} catch ( \InvalidArgumentException ) {
			return null;
		}
	}

	/** @return list<Shipment> */
	public function getAll( \WC_Order $order ): array {
		$rows = $order->get_meta( self::MULTI_META, true );
		$out = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				if ( ! is_array( $row ) ) continue;
				try { $out[] = Shipment::from_array( $row ); } catch ( \InvalidArgumentException ) {}
			}
		}
		if ( ! $out ) { $one = $this->get( $order ); if ( $one ) $out[] = $one; }
		return $out;
	}

	/** @param list<Shipment> $shipments */
	public function saveAll( \WC_Order $order, array $shipments, string $action, int $actor_id ): void {
		$valid = array_values( array_filter( $shipments, static fn( $s ) => $s instanceof Shipment ) );
		if ( ! $valid ) return;
		$order->update_meta_data( self::MULTI_META, array_map( static fn( Shipment $s ) => $s->to_array(), $valid ) );
		$primary = $valid[0];
		$order->update_meta_data( self::SHIPMENT_META, $primary->to_array() );
		$order->update_meta_data( self::TRACKING_META, $primary->tracking_code );
		$order->update_meta_data( self::WAYBILL_META, $primary->waybill_number );
		$order->update_meta_data( self::STATUS_META, self::aggregateStatus( $valid ) );
		$this->rebuildTrackingIndex( $order, $valid );
		$audit = $order->get_meta( self::AUDIT_META, true ); $audit = is_array( $audit ) ? $audit : array();
		$audit[] = array( 'action'=>$action, 'status'=>self::aggregateStatus( $valid ), 'revision'=>$primary->revision, 'actor_id'=>$actor_id, 'occurred_at'=>$primary->updated_at, 'parcel_count'=>count($valid) );
		$order->update_meta_data( self::AUDIT_META, array_slice( $audit, -100 ) );
		$order->save();
		foreach ( $valid as $saved_shipment ) do_action( 'hm_mahex_shipment_saved', $order, $saved_shipment, $action );
	}

	public function save( \WC_Order $order, Shipment $shipment, string $action, int $actor_id ): void {
		$order->delete_meta_data( self::MULTI_META );
		$order->update_meta_data( self::SHIPMENT_META, $shipment->to_array() );
		$order->update_meta_data( self::TRACKING_META, $shipment->tracking_code );
		$order->update_meta_data( self::WAYBILL_META, $shipment->waybill_number );
		$order->update_meta_data( self::STATUS_META, $shipment->status );
		$this->rebuildTrackingIndex( $order, array( $shipment ) );
		$audit   = $order->get_meta( self::AUDIT_META, true );
		$audit   = is_array( $audit ) ? $audit : array();
		$audit[] = array(
			'action'      => $action,
			'status'      => $shipment->status,
			'revision'    => $shipment->revision,
			'actor_id'    => $actor_id,
			'occurred_at' => $shipment->updated_at,
		);
		$order->update_meta_data( self::AUDIT_META, array_slice( $audit, -50 ) );
		$order->save();
		do_action( 'hm_mahex_shipment_saved', $order, $shipment, $action );
	}
	public function aggregateShipment( \WC_Order $order ): ?Shipment {
		$all = $this->getAll( $order );
		if ( empty( $all ) ) return null;
		$primary = $all[0];
		$status = self::aggregateStatus( $all );
		return new Shipment( $primary->shipment_id, $primary->tracking_code, $primary->waybill_number, $status, $primary->package_snapshot, $primary->created_at, $primary->updated_at, $primary->revision );
	}

	/** @param list<Shipment> $shipments */
	public static function aggregateStatus( array $shipments ): string {
		$statuses = array_values( array_map( static fn( Shipment $s ): string => $s->status, array_filter( $shipments, static fn( $s ): bool => $s instanceof Shipment ) ) );
		if ( empty( $statuses ) ) return 'created';
		if ( count( array_filter( $statuses, static fn( string $s ): bool => 'delivered' === $s ) ) === count( $statuses ) ) return 'delivered';
		if ( in_array( 'returned', $statuses, true ) ) return 'returned';
		if ( in_array( 'failed', $statuses, true ) ) return 'failed';
		if ( count( array_filter( $statuses, static fn( string $s ): bool => 'canceled' === $s ) ) === count( $statuses ) ) return 'canceled';
		foreach ( array( 'out_for_delivery', 'in_transit', 'picked_up' ) as $active ) if ( in_array( $active, $statuses, true ) ) return $active;
		return 'created';
	}

	/** @param list<Shipment> $shipments */
	private function rebuildTrackingIndex( \WC_Order $order, array $shipments ): void {
		$order->delete_meta_data( self::TRACKING_INDEX );
		$seen = array();
		foreach ( $shipments as $shipment ) {
			if ( ! $shipment instanceof Shipment || '' === $shipment->tracking_code || isset( $seen[ $shipment->tracking_code ] ) ) continue;
			$order->add_meta_data( self::TRACKING_INDEX, $shipment->tracking_code, false );
			$seen[ $shipment->tracking_code ] = true;
		}
	}

}
