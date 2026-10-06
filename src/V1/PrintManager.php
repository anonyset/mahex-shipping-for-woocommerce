<?php

namespace HoseinMomeni\MahexWoo\V1;

final class PrintManager {
	public static function register(): void {
		add_action( 'hm_mahex_document_printed', array( self::class, 'recordPrinted' ), 10, 3 );
	}

	public static function enqueue( int $orderId, string $type = 'label', int $copies = 1, int $userId = 0 ): int {
		global $wpdb; if ( ! Config::bool( 'print_queue_enabled', true ) ) return 0;
		$type = in_array( $type, array( 'label','waybill','combined','pick','packing' ), true ) ? $type : 'label';
		$wpdb->insert( $wpdb->prefix . 'hm_mahex_print_queue', array( 'order_id'=>$orderId,'document_type'=>$type,'status'=>'pending','copies'=>max(1,min(100,$copies)),'requested_by'=>$userId ?: get_current_user_id(),'created_at'=>current_time('mysql',true) ), array('%d','%s','%s','%d','%d','%s') );
		$id=(int)$wpdb->insert_id; ActivityLog::write('print.queued','سند وارد صف چاپ شد.',$orderId,array('type'=>$type,'queue_id'=>$id)); return $id;
	}

	public static function recordPrinted( int $orderId, string $type = 'label', int $copies = 1 ): void {
		$order = wc_get_order( $orderId ); if ( ! $order ) return;
		$count = max( 0, (int) $order->get_meta( '_hm_mahex_reprint_count', true ) ); $order->update_meta_data('_hm_mahex_reprint_count',$count+max(1,$copies)); $order->save_meta_data();
		ActivityLog::write('print.completed','سند چاپ شد.',$orderId,array('type'=>$type,'copies'=>$copies,'total'=>$count+max(1,$copies)));
	}

	public static function pending( int $limit = 100 ): array { global $wpdb; $t=$wpdb->prefix.'hm_mahex_print_queue'; return $wpdb->get_results($wpdb->prepare("SELECT * FROM $t WHERE status='pending' ORDER BY id ASC LIMIT %d",max(1,min(500,$limit))),ARRAY_A)?:array(); }
	public static function markPrinted( int $id ): bool { global $wpdb; $t=$wpdb->prefix.'hm_mahex_print_queue';$row=$wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE id=%d",$id),ARRAY_A);if(!is_array($row)||'pending'!==($row['status']??''))return false;$ok=false!==$wpdb->update($t,array('status'=>'printed','printed_at'=>current_time('mysql',true)),array('id'=>$id),array('%s','%s'),array('%d'));if($ok)self::recordPrinted((int)$row['order_id'],(string)$row['document_type'],(int)$row['copies']);return $ok; }
}
