<?php

namespace HoseinMomeni\MahexWoo\V1;

use HoseinMomeni\MahexWoo\Shipments\OrderShipmentStore;

final class Diagnostics {
	public static function scan(): array {
		global $wpdb;
		$issues = array();
		if ( version_compare( PHP_VERSION, '8.1', '<' ) ) $issues[] = self::issue( 'php_version', 'critical', 'نسخه PHP کمتر از 8.1 است.', false );
		if ( ! class_exists( 'WooCommerce' ) ) $issues[] = self::issue( 'woocommerce_missing', 'critical', 'WooCommerce فعال نیست.', false );
		if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) $issues[] = self::issue( 'cron_disabled', 'warning', 'WP-Cron غیرفعال است؛ عملیات زمان‌بندی‌شده نیاز به cron واقعی سرور دارد.', false );
		foreach ( array( 'hm_mahex_events','hm_mahex_inventory_ledger','hm_mahex_print_queue','hm_mahex_order_index','hm_mahex_packing_sessions' ) as $suffix ) {
			$table = $wpdb->prefix . $suffix;
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) $issues[] = self::issue( 'missing_table:' . $suffix, 'critical', 'جدول ' . $suffix . ' وجود ندارد.', true );
		}
		$duplicates = self::duplicateTrackings();
		if ( $duplicates ) $issues[] = self::issue( 'duplicate_tracking', 'warning', count( $duplicates ) . ' کد رهگیری تکراری پیدا شد.', false, array( 'samples' => array_slice( $duplicates, 0, 10 ) ) );
		$empty = self::emptyTrackingIndexes();
		if ( $empty > 0 ) $issues[] = self::issue( 'empty_tracking_index', 'warning', $empty . ' سفارش مرسوله دارد ولی ایندکس رهگیری آن ناقص است.', true );
		if ( SafeMode::active() ) $issues[] = self::issue( 'safe_mode', 'warning', 'Safe Mode فعال است.', false );
		if ( ! $issues ) $issues[] = self::issue( 'ok', 'success', 'مشکل شناخته‌شده‌ای پیدا نشد.', false );
		return $issues;
	}

	public static function repair( string $code ): bool {
		if ( str_starts_with( $code, 'missing_table:' ) ) {
			Schema::install();
			ActivityLog::write( 'diagnostic.repair', 'جداول نسخه 1 بازسازی شدند.', 0, array( 'code' => $code ) );
			return true;
		}
		if ( 'empty_tracking_index' === $code ) {
			$count = self::rebuildTrackingIndexes();
			ActivityLog::write( 'diagnostic.repair', 'ایندکس‌های رهگیری بازسازی شدند.', 0, array( 'count' => $count ) );
			return true;
		}
		return false;
	}

	public static function rebuildOrderIndex( int $limit = 1000 ): int {
		$orders = wc_get_orders( array( 'limit' => max( 1, min( 5000, $limit ) ), 'orderby' => 'date', 'order' => 'DESC', 'status' => array_keys( wc_get_order_statuses() ) ) );
		$count = 0;
		foreach ( $orders as $order ) if ( $order instanceof \WC_Order ) { OrderIndex::upsert( $order ); ++$count; }
		return $count;
	}

	private static function issue( string $code, string $severity, string $message, bool $repairable, array $context = array() ): array {
		return compact( 'code', 'severity', 'message', 'repairable', 'context' );
	}

	private static function duplicateTrackings(): array {
		global $wpdb;
		$meta = OrderShipmentStore::TRACKING_META;
		$postsMeta = $wpdb->postmeta;
		$values = $wpdb->get_col( $wpdb->prepare( "SELECT meta_value FROM $postsMeta WHERE meta_key=%s AND meta_value<>'' GROUP BY meta_value HAVING COUNT(*)>1 LIMIT 100", $meta ) );
		return array_values( array_filter( array_map( 'strval', is_array( $values ) ? $values : array() ) ) );
	}

	private static function emptyTrackingIndexes(): int {
		$orders = wc_get_orders( array( 'limit' => 500, 'meta_key' => OrderShipmentStore::STATUS_META, 'meta_compare' => 'EXISTS' ) );
		$count = 0;
		foreach ( $orders as $order ) if ( $order instanceof \WC_Order && '' === trim( (string) $order->get_meta( OrderShipmentStore::TRACKING_META, true ) ) ) ++$count;
		return $count;
	}

	private static function rebuildTrackingIndexes(): int {
		$store = new OrderShipmentStore();
		$orders = wc_get_orders( array( 'limit' => 1000, 'meta_key' => OrderShipmentStore::STATUS_META, 'meta_compare' => 'EXISTS' ) );
		$count = 0;
		foreach ( $orders as $order ) {
			if ( ! $order instanceof \WC_Order ) continue;
			$all = $store->getAll( $order );
			if ( ! $all ) continue;
			$first = $all[0];
			if ( '' === trim( (string) $order->get_meta( OrderShipmentStore::TRACKING_META, true ) ) ) {
				$order->update_meta_data( OrderShipmentStore::TRACKING_META, $first->tracking_code );
				$order->save_meta_data();
				++$count;
			}
		}
		return $count;
	}
}
