<?php

namespace HoseinMomeni\MahexWoo\Inventory;

use HoseinMomeni\MahexWoo\Admin\PackagingProfiles;
use HoseinMomeni\MahexWoo\Pro\FeatureSettings;
use HoseinMomeni\MahexWoo\V1\InventoryLedger;

final class PackagingInventory {
	private const CONSUMED_META = '_hm_mahex_packaging_stock_consumed';

	public static function register(): void {
		add_action( 'woocommerce_order_status_processing', array( self::class, 'consumeForOrder' ), 20 );
		add_action( 'woocommerce_order_status_cancelled', array( self::class, 'restoreForOrder' ), 20 );
		add_action( 'woocommerce_order_status_refunded', array( self::class, 'restoreForOrder' ), 20 );
		add_action( 'admin_notices', array( self::class, 'lowStockNotice' ) );
	}

	public static function consumeForOrder( int $orderId ): void {
		$order = wc_get_order( $orderId );
		if ( ! $order ) return;
		$already = $order->get_meta( self::CONSUMED_META, true );
		if ( 'yes' === $already || ( is_array( $already ) && $already ) ) return;
		$profiles = PackagingProfiles::all();
		$usage = array();
		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			if ( ! $product ) continue;
			$profileId = (string) $product->get_meta( '_hm_mahex_profile_id', true );
			$mode = (string) $product->get_meta( '_hm_mahex_packaging_mode', true );
			if ( '' === $profileId && $product->is_type( 'variation' ) ) {
				$parent = wc_get_product( $product->get_parent_id() );
				if ( $parent ) { $profileId = (string) $parent->get_meta( '_hm_mahex_profile_id', true ); $mode = (string) $parent->get_meta( '_hm_mahex_packaging_mode', true ); }
			}
			if ( 'profile' !== $mode || '' === $profileId || ! isset( $profiles[ $profileId ] ) ) continue;
			$usage[ $profileId ] = (int) ( $usage[ $profileId ] ?? 0 ) + max( 1, (int) $item->get_quantity() );
		}
		foreach ( $usage as $id => $qty ) {
			$stock = max( 0, (int) ( $profiles[ $id ]['stock'] ?? 0 ) );
			$profiles[ $id ]['stock'] = max( 0, $stock - $qty );
		}
		if ( $usage ) {
			update_option( PackagingProfiles::OPTION_NAME, $profiles, false );
			foreach ( $usage as $id => $qty ) InventoryLedger::record( (string) $id, -max(0,(int)$qty), 'out', 'order_consumption', $orderId );
		}
		$order->update_meta_data( self::CONSUMED_META, $usage );
		$order->save_meta_data();
	}

	public static function restoreForOrder( int $orderId ): void {
		$order = wc_get_order( $orderId );
		if ( ! $order ) return;
		$usage = $order->get_meta( self::CONSUMED_META, true );
		if ( ! is_array( $usage ) || ! $usage ) return;
		$profiles = PackagingProfiles::all();
		$changed = false;
		foreach ( $usage as $id => $qty ) {
			$id = sanitize_key( (string) $id );
			if ( ! isset( $profiles[ $id ] ) ) continue;
			$profiles[ $id ]['stock'] = max( 0, (int) ( $profiles[ $id ]['stock'] ?? 0 ) ) + max( 0, (int) $qty );
			$changed = true;
		}
		if ( $changed ) {
			update_option( PackagingProfiles::OPTION_NAME, $profiles, false );
			foreach ( $usage as $id => $qty ) InventoryLedger::record( (string) $id, max(0,(int)$qty), 'in', 'order_restore', $orderId );
		}
		$order->delete_meta_data( self::CONSUMED_META );
		$order->save_meta_data();
	}

	public static function lowStock(): array {
		$threshold = FeatureSettings::int( 'packaging_low_stock_threshold', 10, 0, 100000 );
		$out = array();
		foreach ( PackagingProfiles::all() as $profile ) {
			if ( ! is_array( $profile ) ) continue;
			$stock = max( 0, (int) ( $profile['stock'] ?? 0 ) );
			$local = max( 0, (int) ( $profile['min_stock'] ?? 0 ) );
			$limit = $local > 0 ? $local : $threshold;
			if ( $stock <= $limit ) $out[] = array( 'id' => (string) ( $profile['id'] ?? '' ), 'name' => (string) ( $profile['name'] ?? 'بسته' ), 'stock' => $stock );
		}
		return $out;
	}

	public static function lowStockNotice(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) return;
		$low = self::lowStock();
		if ( ! $low ) return;
		$names = array_map( static fn( $p ) => $p['name'] . ' (' . $p['stock'] . ')', array_slice( $low, 0, 5 ) );
		echo '<div class="notice notice-warning"><p><strong>ماهکس:</strong> موجودی بسته‌بندی کم است: ' . esc_html( implode( '، ', $names ) ) . '. <a href="' . esc_url( admin_url( 'admin.php?page=hm-mahex-inventory' ) ) . '">مدیریت موجودی</a></p></div>';
	}
}
