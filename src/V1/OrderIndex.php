<?php

namespace HoseinMomeni\MahexWoo\V1;

use HoseinMomeni\MahexWoo\Shipments\OrderShipmentStore;

final class OrderIndex {
	public static function register(): void {
		add_action( 'woocommerce_new_order', array( self::class, 'onOrderId' ), 40 );
		add_action( 'woocommerce_update_order', array( self::class, 'onOrderId' ), 40 );
		add_action( 'woocommerce_order_status_changed', array( self::class, 'onOrderId' ), 40 );
		add_action( 'hm_mahex_v1_reindex_daily', array( self::class, 'daily' ) );
		if ( ! wp_next_scheduled( 'hm_mahex_v1_reindex_daily' ) ) wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'hm_mahex_v1_reindex_daily' );
	}

	public static function onOrderId( int $orderId ): void {
		$order = wc_get_order( $orderId );
		if ( $order instanceof \WC_Order ) self::upsert( $order );
	}

	public static function upsert( \WC_Order $order ): void {
		global $wpdb;
		$table = $wpdb->prefix . 'hm_mahex_order_index';
		$store = new OrderShipmentStore();
		$shipment = $store->aggregateShipment( $order ) ?: $store->get( $order );
		$shipping = (float) $order->get_shipping_total();
		$packaging = 0.0;
		foreach ( $order->get_items( 'shipping' ) as $item ) {
			$value = $item->get_meta( 'hm_mahex_packaging', true );
			if ( is_numeric( $value ) ) $packaging += (float) $value;
		}
		$actual = $order->get_meta( '_hm_mahex_actual_carrier_cost', true );
		$carrier = is_numeric( $actual ) ? max( 0.0, (float) $actual ) : 0.0;
		$created = $order->get_date_created();
		$data = array(
			'order_id' => $order->get_id(),
			'order_number' => substr( (string) $order->get_order_number(), 0, 64 ),
			'customer_name' => substr( trim( $order->get_formatted_billing_full_name() ) ?: 'مهمان', 0, 190 ),
			'phone' => substr( (string) $order->get_billing_phone(), 0, 40 ),
			'province' => substr( \HoseinMomeni\MahexWoo\Shipping\PricingSettings::province_name( (string) ( $order->get_shipping_state() ?: $order->get_billing_state() ) ), 0, 100 ),
			'city' => substr( (string) ( $order->get_shipping_city() ?: $order->get_billing_city() ), 0, 100 ),
			'postcode' => substr( (string) ( $order->get_shipping_postcode() ?: $order->get_billing_postcode() ), 0, 30 ),
			'tracking' => substr( $shipment?->tracking_code ?? '', 0, 190 ),
			'shipment_status' => substr( $shipment?->status ?? 'none', 0, 32 ),
			'shipping_total' => $shipping,
			'packaging_total' => $packaging,
			'carrier_cost' => $carrier,
			'profit' => $shipping + $packaging - $carrier,
			'created_at' => $created ? $created->date( 'Y-m-d H:i:s' ) : null,
			'updated_at' => current_time( 'mysql', true ),
		);
		$wpdb->replace( $table, $data, array( '%d','%s','%s','%s','%s','%s','%s','%s','%s','%f','%f','%f','%f','%s','%s' ) );
	}

	public static function daily(): void {
		if ( ! Config::bool( 'large_store_mode', true ) ) return;
		Diagnostics::rebuildOrderIndex( 2000 );
		ActivityLog::prune();
	}

	public static function search( string $query, int $limit = 100 ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'hm_mahex_order_index';
		$query = trim( $query );
		if ( '' === $query ) return array();
		$like = '%' . $wpdb->esc_like( $query ) . '%';
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE order_number LIKE %s OR customer_name LIKE %s OR phone LIKE %s OR tracking LIKE %s OR city LIKE %s OR postcode LIKE %s ORDER BY order_id DESC LIMIT %d", $like, $like, $like, $like, $like, $like, max( 1, min( 500, $limit ) ) ), ARRAY_A ) ?: array();
	}
}
