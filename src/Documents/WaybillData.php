<?php

namespace HoseinMomeni\MahexWoo\Documents;

/** Immutable, presentation-ready waybill data. Monetary values are formatted strings. */
final class WaybillData {
	public function __construct(
		public readonly string $waybill_number,
		public readonly string $tracking_code,
		public readonly string $created_at,
		public readonly string $origin,
		public readonly string $destination,
		public readonly string $sender_name,
		public readonly string $recipient_name,
		public readonly string $recipient_address,
		public readonly string $recipient_phone_masked,
		public readonly string $contents,
		public readonly int $item_count,
		public readonly string $weight,
		public readonly string $dimensions,
		public readonly string $shipping_cost,
		public readonly string $packaging_cost,
		public readonly string $cod_amount = '۰',
		public readonly string $barcode_number = '',
		public readonly string $sender_address = '',
		public readonly string $sender_phone = '',
	) {
		foreach ( array( $waybill_number, $tracking_code, $created_at, $origin, $destination ) as $required ) {
			if ( '' === trim( $required ) ) {
				throw new \InvalidArgumentException( 'Required waybill values cannot be empty.' );
			}
		}
		if ( $item_count < 1 ) {
			throw new \InvalidArgumentException( 'Waybill item count must be positive.' );
		}
		if ( '' !== $barcode_number && 1 !== preg_match( '/^\d{10}$/D', $barcode_number ) ) {
			throw new \InvalidArgumentException( 'Barcode number must contain exactly ten ASCII digits.' );
		}
	}

	public function total_label(): string {
		return $this->shipping_cost . ' + ' . $this->packaging_cost;
	}

	public function qr_payload(): string {
		if ( function_exists( 'home_url' ) && function_exists( 'add_query_arg' ) ) {
			return add_query_arg( 'hm_mahex_track', $this->tracking_code, home_url( '/' ) );
		}
		$json = json_encode( array( 'v' => 1, 'waybill' => $this->waybill_number, 'tracking' => $this->tracking_code, 'barcode' => $this->barcode_number ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		if ( false === $json ) throw new \RuntimeException( 'Could not encode QR payload.' );
		return $json;
	}

}
