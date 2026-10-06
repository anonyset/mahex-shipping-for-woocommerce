<?php

namespace HoseinMomeni\MahexWoo\V2;

final class ShiftManager {
	public static function current( int $userId = 0 ): ?array {
		global $wpdb;
		$userId = $userId ?: get_current_user_id();
		$t = $wpdb->prefix . 'hm_mahex_v2_shifts';
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE user_id=%d AND status='open' ORDER BY id DESC LIMIT 1", $userId ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	public static function start( string $station = 'default', int $userId = 0 ): int {
		global $wpdb;
		$userId = $userId ?: get_current_user_id();
		if ( self::current( $userId ) ) return 0;
		$wpdb->insert( $wpdb->prefix . 'hm_mahex_v2_shifts', array( 'user_id'=>$userId,'station'=>substr(sanitize_text_field($station),0,100),'status'=>'open','started_at'=>current_time('mysql',true) ), array( '%d','%s','%s','%s' ) );
		$id = (int) $wpdb->insert_id;
		EventStore::write( 'operator.shift_started', 'شیفت اپراتور شروع شد.', 0, 'info', array( 'shift_id'=>$id,'station'=>$station ), $userId );
		return $id;
	}

	public static function end( int $userId = 0 ): bool {
		global $wpdb;
		$userId = $userId ?: get_current_user_id();
		$current = self::current( $userId );
		if ( ! $current ) return false;
		$ok = false !== $wpdb->update( $wpdb->prefix . 'hm_mahex_v2_shifts', array( 'status'=>'closed','ended_at'=>current_time('mysql',true) ), array( 'id'=>(int)$current['id'] ), array( '%s','%s' ), array( '%d' ) );
		if ( $ok ) EventStore::write( 'operator.shift_ended', 'شیفت اپراتور پایان یافت.', 0, 'info', array( 'shift_id'=>(int)$current['id'] ), $userId );
		return $ok;
	}

	public static function recordPacking( int $userId, int $items, bool $error = false ): void {
		global $wpdb;
		$current = self::current( $userId );
		if ( ! $current ) return;
		$t = $wpdb->prefix . 'hm_mahex_v2_shifts';
		$wpdb->query( $wpdb->prepare( "UPDATE $t SET packed_orders=packed_orders+1,packed_items=packed_items+%d,errors=errors+%d WHERE id=%d", max(0,$items), $error?1:0, (int)$current['id'] ) );
	}

	public static function performance( int $days = 30 ): array {
		global $wpdb;
		$t = $wpdb->prefix . 'hm_mahex_v2_shifts';
		return $wpdb->get_results( $wpdb->prepare( "SELECT user_id,COUNT(*) shifts,SUM(packed_orders) packed_orders,SUM(packed_items) packed_items,SUM(errors) errors,SUM(TIMESTAMPDIFF(MINUTE,started_at,COALESCE(ended_at,UTC_TIMESTAMP()))) minutes FROM $t WHERE started_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL %d DAY) GROUP BY user_id ORDER BY packed_orders DESC", max(1,min(3650,$days)) ), ARRAY_A ) ?: array();
	}
}
