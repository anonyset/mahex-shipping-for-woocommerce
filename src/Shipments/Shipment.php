<?php

namespace HoseinMomeni\MahexWoo\Shipments;

final class Shipment {
	public const STATUSES = array( 'created', 'picked_up', 'in_transit', 'out_for_delivery', 'delivered', 'returned', 'failed', 'canceled' );

	public function __construct(
		public readonly string $shipment_id,
		public readonly string $tracking_code,
		public readonly string $waybill_number,
		public readonly string $status,
		public readonly array $package_snapshot,
		public readonly string $created_at,
		public readonly string $updated_at,
		public readonly int $revision = 1
	) {
		if ( '' === $shipment_id || '' === $tracking_code || '' === $waybill_number ) {
			throw new \InvalidArgumentException( 'Shipment identifiers cannot be empty.' );
		}
		if ( ! in_array( $status, self::STATUSES, true ) ) {
			throw new \InvalidArgumentException( 'Unknown shipment status.' );
		}
	}

	public function to_array(): array {
		return array(
			'shipment_id'     => $this->shipment_id,
			'tracking_code'   => $this->tracking_code,
			'waybill_number'  => $this->waybill_number,
			'status'          => $this->status,
			'package_snapshot'=> $this->package_snapshot,
			'created_at'      => $this->created_at,
			'updated_at'      => $this->updated_at,
			'revision'        => $this->revision,
		);
	}

	public static function from_array( array $data ): self {
		return new self(
			(string) ( $data['shipment_id'] ?? '' ),
			(string) ( $data['tracking_code'] ?? '' ),
			(string) ( $data['waybill_number'] ?? '' ),
			(string) ( $data['status'] ?? 'created' ),
			is_array( $data['package_snapshot'] ?? null ) ? $data['package_snapshot'] : array(),
			(string) ( $data['created_at'] ?? '' ),
			(string) ( $data['updated_at'] ?? '' ),
			max( 1, (int) ( $data['revision'] ?? 1 ) )
		);
	}
}
