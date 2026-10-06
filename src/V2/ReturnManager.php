<?php

namespace HoseinMomeni\MahexWoo\V2;

final class ReturnManager {
	public static function register(): void {
		add_action( 'woocommerce_order_status_refunded', static fn( int $id ) => self::ensure( $id, 'refund', 'بازپرداخت سفارش' ), 30 );
	}

	public static function ensure( int $orderId, string $reason = '', string $note = '' ): int {
		global $wpdb;
		$t = $wpdb->prefix . 'hm_mahex_v2_returns';
		$existing = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $t WHERE order_id=%d AND status IN ('open','received') ORDER BY id DESC LIMIT 1", $orderId ) );
		if ( $existing ) return $existing;
		$cost = Config::int( 'return_cost_default_irr', 0, 0, PHP_INT_MAX );
		$now = current_time( 'mysql', true );
		$wpdb->insert( $t, array( 'order_id'=>$orderId,'status'=>'open','reason'=>substr(sanitize_text_field($reason),0,120),'return_cost'=>$cost,'note'=>sanitize_textarea_field($note),'created_by'=>get_current_user_id(),'created_at'=>$now,'updated_at'=>$now ), array( '%d','%s','%s','%f','%s','%d','%s','%s' ) );
		$id = (int) $wpdb->insert_id;
		EventStore::write( 'return.opened', 'پرونده برگشتی برای سفارش ایجاد شد.', $orderId, 'warning', array( 'return_id'=>$id,'reason'=>$reason ) );
		return $id;
	}

	public static function update( int $id, string $status, float $cost, string $reason, string $note ): bool {
		global $wpdb;
		$status = in_array( $status, array( 'open','received','closed','cancelled' ), true ) ? $status : 'open';
		return false !== $wpdb->update( $wpdb->prefix . 'hm_mahex_v2_returns', array( 'status'=>$status,'return_cost'=>max(0,$cost),'reason'=>substr(sanitize_text_field($reason),0,120),'note'=>sanitize_textarea_field($note),'updated_at'=>current_time('mysql',true) ), array( 'id'=>$id ), array( '%s','%f','%s','%s','%s' ), array( '%d' ) );
	}

	public static function recent( int $limit=100 ): array { global $wpdb; $t=$wpdb->prefix.'hm_mahex_v2_returns'; return $wpdb->get_results($wpdb->prepare("SELECT * FROM $t ORDER BY updated_at DESC LIMIT %d",max(1,min(500,$limit))),ARRAY_A)?:array(); }
}
