<?php

namespace HoseinMomeni\MahexWoo\V2;

final class ProblemCenter {
	public static function scan(): array {
		if ( ! Config::bool( 'problem_scan_enabled', true ) ) return array();
		$found = array();
		$hours = Config::int( 'problem_stale_hours', 48, 6, 720 );
		$orders = wc_get_orders( array( 'limit' => 200, 'orderby' => 'date', 'order' => 'DESC', 'status' => array( 'processing','on-hold' ) ) );
		foreach ( $orders as $order ) {
			if ( ! $order instanceof \WC_Order ) continue;
			$id = $order->get_id();
			$created = $order->get_date_created();
			$ageHours = $created ? ( time() - $created->getTimestamp() ) / HOUR_IN_SECONDS : 0;
			$packing = (string) $order->get_meta( '_hm_mahex_packing_status', true );
			if ( $ageHours >= $hours && ! in_array( $packing, array( 'packed','ready_to_ship' ), true ) ) {
				$found[] = self::upsert( $id, 'stale_processing', 'warning', 'سفارش مدت زیادی در انتظار آماده‌سازی است.', sprintf( 'بیش از %d ساعت از ثبت سفارش گذشته است.', $hours ) );
			}
			$verification = $order->get_meta( '_hm_mahex_packing_verification', true );
			if ( is_array( $verification ) && ( ! empty( $verification['missing'] ) || ! empty( $verification['wrong'] ) ) ) {
				$found[] = self::upsert( $id, 'packing_mismatch', 'critical', 'مغایرت بسته‌بندی ثبت شده است.', 'کالای جاافتاده یا کالای اشتباه در آخرین کنترل بسته‌بندی وجود دارد.' );
			}
			$postcode = preg_replace( '/\D/', '', (string) $order->get_shipping_postcode() ) ?: '';
			if ( '' !== $postcode && 10 !== strlen( $postcode ) ) {
				$found[] = self::upsert( $id, 'postcode_invalid', 'warning', 'کدپستی نیاز به بررسی دارد.', 'کدپستی سفارش ۱۰ رقمی نیست.' );
			}
			$freeShipping = 'yes' === (string) $order->get_meta( '_hm_mahex_free_shipping', true );
			if ( ! $freeShipping ) foreach ( $order->get_items( 'shipping' ) as $shippingItem ) { if ( 'yes' === (string) $shippingItem->get_meta( 'hm_mahex_free_shipping', true ) ) { $freeShipping = true; break; } }
			if ( (float) $order->get_shipping_total() <= 0 && (float) $order->get_total() > 0 && ! $freeShipping ) {
				$found[] = self::upsert( $id, 'zero_shipping', 'info', 'هزینه ارسال صفر است.', 'در صورت عمدی نبودن ارسال رایگان، نرخ سفارش را بررسی کنید.' );
			}
		}
		self::closeMissing( array_filter( array_map( static fn( $v ) => is_array( $v ) ? (string) ( $v['fingerprint'] ?? '' ) : '', $found ) ) );
		return $found;
	}

	public static function open( int $limit = 100 ): array {
		global $wpdb;
		$t = $wpdb->prefix . 'hm_mahex_v2_problems';
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE status='open' ORDER BY FIELD(severity,'critical','error','warning','info'),last_seen DESC LIMIT %d", max( 1, min( 500, $limit ) ) ), ARRAY_A ) ?: array();
	}

	public static function resolve( int $id ): bool {
		global $wpdb;
		return false !== $wpdb->update( $wpdb->prefix . 'hm_mahex_v2_problems', array( 'status'=>'resolved','resolved_at'=>current_time( 'mysql', true ) ), array( 'id'=>$id ), array( '%s','%s' ), array( '%d' ) );
	}

	private static function upsert( int $orderId, string $type, string $severity, string $title, string $details ): array {
		global $wpdb;
		$t = $wpdb->prefix . 'hm_mahex_v2_problems';
		$fingerprint = hash( 'sha256', $orderId . '|' . $type );
		$now = current_time( 'mysql', true );
		$existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE fingerprint=%s", $fingerprint ), ARRAY_A );
		if ( $existing ) {
			$wpdb->update( $t, array( 'status'=>'open','severity'=>$severity,'title'=>$title,'details'=>$details,'last_seen'=>$now,'resolved_at'=>null ), array( 'id'=>(int)$existing['id'] ), array( '%s','%s','%s','%s','%s','%s' ), array( '%d' ) );
		} else {
			$wpdb->insert( $t, array( 'fingerprint'=>$fingerprint,'order_id'=>$orderId,'problem_type'=>$type,'severity'=>$severity,'status'=>'open','title'=>$title,'details'=>$details,'first_seen'=>$now,'last_seen'=>$now ), array( '%s','%d','%s','%s','%s','%s','%s','%s','%s' ) );
		}
		return array( 'fingerprint'=>$fingerprint,'order_id'=>$orderId,'problem_type'=>$type,'severity'=>$severity,'title'=>$title );
	}

	private static function closeMissing( array $activeFingerprints ): void {
		global $wpdb;
		$t = $wpdb->prefix . 'hm_mahex_v2_problems';
		if ( ! $activeFingerprints ) {
			$wpdb->query( "UPDATE $t SET status='resolved',resolved_at=UTC_TIMESTAMP() WHERE status='open' AND problem_type IN ('stale_processing','packing_mismatch','postcode_invalid','zero_shipping')" );
			return;
		}
		$escaped = array_map( static fn( $v ) => "'" . esc_sql( $v ) . "'", $activeFingerprints );
		$wpdb->query( "UPDATE $t SET status='resolved',resolved_at=UTC_TIMESTAMP() WHERE status='open' AND problem_type IN ('stale_processing','packing_mismatch','postcode_invalid','zero_shipping') AND fingerprint NOT IN (" . implode( ',', $escaped ) . ')' );
	}
}
