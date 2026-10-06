<?php

namespace HoseinMomeni\MahexWoo\V2;

use HoseinMomeni\MahexWoo\Shipments\Shipment;

final class ParcelStore {
	public static function register(): void {
		add_action( 'hm_mahex_shipment_saved', array( self::class, 'sync' ), 20, 3 );
	}

	public static function sync( \WC_Order $order, Shipment $shipment, string $action ): void {
		global $wpdb;
		$snapshot = $shipment->package_snapshot;
		$key = sanitize_key( $shipment->shipment_id );
		if ( '' === $key ) $key = 'parcel-' . substr( hash( 'sha256', $shipment->tracking_code ), 0, 12 );
		$dimensions = self::dimensions( $snapshot );
		$weight = self::weight( $snapshot );
		$wpdb->replace( $wpdb->prefix . 'hm_mahex_v2_parcels', array(
			'order_id'=>$order->get_id(),'parcel_key'=>$key,'tracking'=>$shipment->tracking_code,'status'=>$shipment->status,
			'warehouse_id'=>sanitize_key((string)($snapshot['_warehouse_id']??'')),'weight_g'=>$weight,
			'length_mm'=>$dimensions[0],'width_mm'=>$dimensions[1],'height_mm'=>$dimensions[2],
			'cost'=>0,'created_at'=>self::mysql($shipment->created_at),'updated_at'=>self::mysql($shipment->updated_at),
		), array('%d','%s','%s','%s','%s','%d','%d','%d','%d','%f','%s','%s') );
		EventStore::write( 'parcel.' . sanitize_key( $action ), 'اطلاعات مرسوله در Parcel Store همگام شد.', $order->get_id(), 'info', array( 'tracking'=>$shipment->tracking_code,'status'=>$shipment->status,'parcel'=>$key ) );
	}

	public static function backfill( int $batch = 250 ): array {
		if ( ! function_exists( 'wc_get_orders' ) ) return array( 'processed'=>0, 'page'=>0, 'done'=>true );
		$page = max( 1, (int) get_option( 'hm_mahex_v2_parcel_backfill_page', 1 ) );
		$batch = max( 25, min( 1000, $batch ) );
		$result = wc_get_orders( array( 'limit'=>$batch, 'page'=>$page, 'paginate'=>true, 'orderby'=>'ID', 'order'=>'ASC', 'return'=>'objects' ) );
		$orders = is_object( $result ) && isset( $result->orders ) ? (array) $result->orders : ( is_array( $result ) ? $result : array() );
		$processed = 0;
		foreach ( $orders as $order ) {
			if ( ! $order instanceof \WC_Order ) continue;
			$rows = $order->get_meta( '_hm_mahex_shipments', true );
			if ( ! is_array( $rows ) || ! $rows ) { $one=$order->get_meta('_hm_mahex_shipment',true);$rows=is_array($one)&&$one?array($one):array(); }
			foreach ( $rows as $row ) { if ( ! is_array( $row ) ) continue; try { self::sync( $order, \HoseinMomeni\MahexWoo\Shipments\Shipment::from_array( $row ), 'backfill' ); $processed++; } catch ( \Throwable $e ) {} }
		}
		$done = count( $orders ) < $batch || ( is_object($result) && isset($result->max_num_pages) && $page >= (int)$result->max_num_pages );
		if ( $done ) update_option( 'hm_mahex_v2_parcel_backfill_done', 1, false ); else update_option( 'hm_mahex_v2_parcel_backfill_page', $page + 1, false );
		return array( 'processed'=>$processed, 'page'=>$page, 'done'=>$done );
	}

	public static function forOrder( int $orderId ): array { global $wpdb;$t=$wpdb->prefix.'hm_mahex_v2_parcels';return $wpdb->get_results($wpdb->prepare("SELECT * FROM $t WHERE order_id=%d ORDER BY id ASC",$orderId),ARRAY_A)?:array(); }
	public static function findTracking( string $tracking ): ?array { global $wpdb;$t=$wpdb->prefix.'hm_mahex_v2_parcels';$row=$wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE tracking=%s ORDER BY id DESC LIMIT 1",sanitize_text_field($tracking)),ARRAY_A);return is_array($row)?$row:null; }
	private static function weight(array $s): int { $total=0;foreach((array)($s['items']??array()) as $i){if(!is_array($i))continue;$w=$i['weight']??0;$q=max(1,(int)($i['quantity']??1));if(is_numeric($w))$total+=(int)round((float)wc_get_weight((float)$w,'g',get_option('woocommerce_weight_unit','kg')))*$q;}return max(0,$total); }
	private static function dimensions(array $s): array { $l=$w=$h=0;foreach((array)($s['items']??array()) as $i){$d=is_array($i['dimensions']??null)?$i['dimensions']:array();$l=max($l,(int)round((float)wc_get_dimension((float)($d['length']??0),'mm',get_option('woocommerce_dimension_unit','cm'))));$w=max($w,(int)round((float)wc_get_dimension((float)($d['width']??0),'mm',get_option('woocommerce_dimension_unit','cm'))));$h+=max(0,(int)round((float)wc_get_dimension((float)($d['height']??0),'mm',get_option('woocommerce_dimension_unit','cm'))));}return array($l,$w,$h); }
	private static function mysql(string $iso): string { $ts=strtotime($iso);return $ts?gmdate('Y-m-d H:i:s',$ts):current_time('mysql',true); }
}
