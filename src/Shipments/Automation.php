<?php

namespace HoseinMomeni\MahexWoo\Shipments;

use HoseinMomeni\MahexWoo\Pro\FeatureSettings;
use HoseinMomeni\MahexWoo\Enterprise\Config as EnterpriseConfig;
use HoseinMomeni\MahexWoo\Enterprise\QueueStore;

/** Local-only shipment automation for v1.0.0. */
final class Automation {
	private const LEGACY_SYNC_HOOK = 'hm_mahex_sync_active_shipments';

	public static function register(): void {
		add_action( 'woocommerce_order_status_processing', array( self::class, 'maybeAutoCreate' ) );
		add_action( 'woocommerce_order_status_completed', array( self::class, 'maybeAutoCreate' ) );
		add_action( 'init', array( self::class, 'clearLegacySync' ) );
	}

	public static function clearLegacySync(): void {
		if ( wp_next_scheduled( self::LEGACY_SYNC_HOOK ) ) wp_clear_scheduled_hook( self::LEGACY_SYNC_HOOK );
	}

	public static function maybeAutoCreate( int $orderId ): void {
		if ( ! FeatureSettings::bool( 'auto_create_shipment' ) ) return;
		$order = wc_get_order( $orderId );
		if ( ! $order ) return;
		$service = new ShipmentService();
		if ( ! $service->needsCreation( $order ) ) return;
		if ( EnterpriseConfig::bool( 'queue_enabled', true ) ) {
			QueueStore::enqueueUnique( 'shipment_create', array( 'order_id' => $orderId ), 100 );
			return;
		}
		try {
			$service->create( $order, 0 );
		} catch ( \Throwable $e ) {
			if ( function_exists( 'wc_get_logger' ) ) wc_get_logger()->error( 'Local automatic shipment creation failed.', array( 'source'=>'hm-mahex-automation','order_id'=>$orderId,'exception'=>get_class($e) ) );
		}
	}
}
