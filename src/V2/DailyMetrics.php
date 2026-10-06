<?php

namespace HoseinMomeni\MahexWoo\V2;

final class DailyMetrics {
	public static function rebuildDate( string $date ): array {
		global $wpdb;
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) $date = gmdate( 'Y-m-d' );
		$idx = $wpdb->prefix . 'hm_mahex_order_index';
		$sessions = $wpdb->prefix . 'hm_mahex_packing_sessions';
		$start = $date . ' 00:00:00'; $end = gmdate( 'Y-m-d H:i:s', strtotime( $date . ' +1 day' ) );
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT COUNT(*) orders_count, COALESCE(SUM(shipping_total),0) shipping_revenue, COALESCE(SUM(packaging_total),0) packaging_revenue, COALESCE(SUM(carrier_cost),0) carrier_cost, COALESCE(SUM(profit),0) profit, SUM(CASE WHEN shipment_status='returned' THEN 1 ELSE 0 END) returned_count, COALESCE(SUM(CASE WHEN shipment_status='returned' THEN carrier_cost ELSE 0 END),0) return_cost FROM $idx WHERE created_at >= %s AND created_at < %s",
			$start, $end
		), ARRAY_A ) ?: array();
		$pack = $wpdb->get_row( $wpdb->prepare(
			"SELECT SUM(CASE WHEN status='packed' THEN 1 ELSE 0 END) packed_count, SUM(CASE WHEN status='failed' THEN 1 ELSE 0 END) packing_errors FROM $sessions WHERE created_at >= %s AND created_at < %s",
			$start, $end
		), ARRAY_A ) ?: array();
		$data = array(
			'metric_date' => $date,
			'orders_count' => (int) ( $row['orders_count'] ?? 0 ),
			'shipping_revenue' => (float) ( $row['shipping_revenue'] ?? 0 ),
			'packaging_revenue' => (float) ( $row['packaging_revenue'] ?? 0 ),
			'carrier_cost' => (float) ( $row['carrier_cost'] ?? 0 ),
			'profit' => (float) ( $row['profit'] ?? 0 ),
			'returned_count' => (int) ( $row['returned_count'] ?? 0 ),
			'return_cost' => (float) ( $row['return_cost'] ?? 0 ),
			'packed_count' => (int) ( $pack['packed_count'] ?? 0 ),
			'packing_errors' => (int) ( $pack['packing_errors'] ?? 0 ),
			'updated_at' => current_time( 'mysql', true ),
		);
		$wpdb->replace( $wpdb->prefix . 'hm_mahex_v2_daily_metrics', $data, array( '%s','%d','%f','%f','%f','%f','%d','%f','%d','%d','%s' ) );
		return $data;
	}

	public static function rebuildRecent( int $days = 35 ): void {
		$days = max( 1, min( 365, $days ) );
		for ( $i = 0; $i < $days; $i++ ) self::rebuildDate( gmdate( 'Y-m-d', strtotime( "-$i days" ) ) );
	}

	public static function range( int $days = 30 ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'hm_mahex_v2_daily_metrics';
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE metric_date >= DATE_SUB(UTC_DATE(), INTERVAL %d DAY) ORDER BY metric_date ASC", max( 1, min( 3650, $days ) ) ), ARRAY_A ) ?: array();
	}
}
