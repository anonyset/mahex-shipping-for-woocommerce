<?php

namespace HoseinMomeni\MahexWoo\V1;

use HoseinMomeni\MahexWoo\Admin\PackagingProfiles;

final class InventoryLedger {
	public static function record( string $profileId, int $quantity, string $direction, string $reason, int $orderId = 0, int $actorId = -1 ): void {
		global $wpdb;
		$profiles = PackagingProfiles::all();
		if ( ! isset( $profiles[ $profileId ] ) || ! is_array( $profiles[ $profileId ] ) ) return;
		if ( -1 === $actorId ) $actorId = function_exists( 'get_current_user_id' ) ? get_current_user_id() : 0;
		$direction = in_array( $direction, array( 'in','out','adjust' ), true ) ? $direction : 'adjust';
		$wpdb->insert( $wpdb->prefix . 'hm_mahex_inventory_ledger', array(
			'profile_id' => substr( sanitize_key( $profileId ), 0, 64 ), 'order_id' => max( 0, $orderId ), 'actor_id' => max( 0, $actorId ),
			'direction' => $direction, 'quantity' => $quantity, 'balance_after' => max( 0, (int) ( $profiles[ $profileId ]['stock'] ?? 0 ) ),
			'unit_cost_irr' => max( 0, (int) ( $profiles[ $profileId ]['unit_cost_irr'] ?? 0 ) ), 'reason' => substr( sanitize_text_field( $reason ), 0, 120 ),
			'created_at' => current_time( 'mysql', true ),
		), array( '%s','%d','%d','%s','%d','%d','%d','%s','%s' ) );
	}

	public static function adjust( string $profileId, int $newStock, string $reason, int $actorId = 0 ): bool {
		$profiles = PackagingProfiles::all();
		if ( ! isset( $profiles[ $profileId ] ) || ! is_array( $profiles[ $profileId ] ) ) return false;
		$old = max( 0, (int) ( $profiles[ $profileId ]['stock'] ?? 0 ) ); $newStock = max( 0, $newStock );
		$profiles[ $profileId ]['stock'] = $newStock; update_option( PackagingProfiles::OPTION_NAME, $profiles, false );
		self::record( $profileId, $newStock - $old, 'adjust', $reason, 0, $actorId );
		ActivityLog::write( 'inventory.adjust', 'موجودی بسته‌بندی اصلاح شد.', 0, array( 'profile_id'=>$profileId,'old'=>$old,'new'=>$newStock,'reason'=>$reason ), $actorId );
		return true;
	}

	public static function recent( int $limit = 100 ): array {
		global $wpdb; $limit = max( 1, min( 1000, $limit ) ); $table = $wpdb->prefix . 'hm_mahex_inventory_ledger';
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table ORDER BY id DESC LIMIT %d", $limit ), ARRAY_A ) ?: array();
	}

	public static function valuation(): int {
		$total = 0; foreach ( PackagingProfiles::all() as $p ) if ( is_array( $p ) ) $total += max(0,(int)($p['stock']??0))*max(0,(int)($p['unit_cost_irr']??0)); return $total;
	}

	public static function consumptionReport( int $days = 30 ): array {
		global $wpdb; $days = max( 1, min( 3650, $days ) ); $table = $wpdb->prefix . 'hm_mahex_inventory_ledger';
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT profile_id,SUM(ABS(quantity)) qty,COUNT(*) movements FROM $table WHERE direction='out' AND created_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL %d DAY) GROUP BY profile_id ORDER BY qty DESC", $days ), ARRAY_A ) ?: array();
		$profiles = PackagingProfiles::all(); foreach ( $rows as &$r ) $r['name'] = (string) ( $profiles[$r['profile_id']]['name'] ?? $r['profile_id'] ); unset($r); return $rows;
	}

	public static function lowAndCritical(): array {
		$low = array(); $critical = array(); $globalCritical = Config::int( 'critical_stock_threshold', 3, 0, 100000 );
		foreach ( PackagingProfiles::all() as $id => $p ) {
			if ( ! is_array($p) ) continue; $stock=max(0,(int)($p['stock']??0)); $min=max(0,(int)($p['min_stock']??0));
			$row=array('id'=>(string)$id,'name'=>(string)($p['name']??$id),'stock'=>$stock,'min_stock'=>$min);
			if ( $stock <= $globalCritical ) $critical[]=$row; elseif ( $min>0 && $stock <= $min ) $low[]=$row;
		}
		return array('low'=>$low,'critical'=>$critical);
	}
}
