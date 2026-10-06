<?php

namespace HoseinMomeni\MahexWoo\V2;

final class EventStore {
	public static function register(): void {
		add_action( 'woocommerce_order_status_changed', array( self::class, 'orderStatus' ), 50, 4 );
		add_action( 'woocommerce_new_order', static fn( int $id ) => self::write( 'order.created', 'سفارش ایجاد شد.', $id ), 50 );
		add_action( 'woocommerce_order_refunded', static fn( int $orderId, int $refundId ) => self::write( 'order.refunded', 'بازپرداخت سفارش ثبت شد.', $orderId, 'warning', array( 'refund_id' => $refundId ) ), 50, 2 );
	}

	public static function correlationId( int $orderId = 0 ): string {
		if ( $orderId > 0 ) {
			$order = wc_get_order( $orderId );
			if ( $order ) {
				$existing = (string) $order->get_meta( '_hm_mahex_correlation_id', true );
				if ( '' !== $existing ) return $existing;
				$id = 'mx-' . $orderId . '-' . strtolower( wp_generate_password( 12, false, false ) );
				$order->update_meta_data( '_hm_mahex_correlation_id', $id );
				$order->save_meta_data();
				return $id;
			}
		}
		return 'mx-' . strtolower( wp_generate_password( 18, false, false ) );
	}

	public static function write( string $type, string $message, int $orderId = 0, string $severity = 'info', array $context = array(), int $actorId = 0 ): void {
		global $wpdb;
		$type = sanitize_key( $type );
		if ( '' === $type ) return;
		$severity = in_array( $severity, array( 'info','warning','error','critical' ), true ) ? $severity : 'info';
		$wpdb->insert( $wpdb->prefix . 'hm_mahex_v2_events', array(
			'correlation_id' => self::correlationId( $orderId ),
			'order_id' => max( 0, $orderId ),
			'actor_id' => $actorId ?: get_current_user_id(),
			'event_type' => substr( $type, 0, 100 ),
			'severity' => $severity,
			'message' => substr( wp_strip_all_tags( $message ), 0, 255 ),
			'context' => wp_json_encode( self::sanitizeContext( $context ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
			'created_at' => current_time( 'mysql', true ),
		), array( '%s','%d','%d','%s','%s','%s','%s','%s' ) );
	}

	public static function recent( int $limit = 100, int $orderId = 0 ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'hm_mahex_v2_events';
		$limit = max( 1, min( 500, $limit ) );
		if ( $orderId > 0 ) return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE order_id=%d ORDER BY id DESC LIMIT %d", $orderId, $limit ), ARRAY_A ) ?: array();
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table ORDER BY id DESC LIMIT %d", $limit ), ARRAY_A ) ?: array();
	}

	public static function prune(): void {
		global $wpdb;
		$days = Config::int( 'event_retention_days', 365, 30, 3650 );
		$table = $wpdb->prefix . 'hm_mahex_v2_events';
		$wpdb->query( $wpdb->prepare( "DELETE FROM $table WHERE created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY) LIMIT 10000", $days ) );
	}

	public static function orderStatus( int $orderId, string $from, string $to, \WC_Order $order ): void {
		self::write( 'order.status_changed', sprintf( 'وضعیت سفارش از %s به %s تغییر کرد.', $from, $to ), $orderId, 'info', array( 'from' => $from, 'to' => $to ) );
	}

	private static function sanitizeContext( array $context ): array {
		$out = array();
		foreach ( array_slice( $context, 0, 50, true ) as $k => $v ) {
			$key = sanitize_key( (string) $k );
			if ( preg_match( '/token|secret|password|phone|address/i', $key ) ) { $out[ $key ] = '[redacted]'; continue; }
			if ( is_scalar( $v ) || null === $v ) $out[ $key ] = is_string( $v ) ? substr( $v, 0, 500 ) : $v;
			elseif ( is_array( $v ) ) $out[ $key ] = array_slice( $v, 0, 20 );
		}
		return $out;
	}
}
