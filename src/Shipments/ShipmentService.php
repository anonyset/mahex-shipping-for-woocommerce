<?php

namespace HoseinMomeni\MahexWoo\Shipments;

use HoseinMomeni\MahexWoo\Enterprise\Config as EnterpriseConfig;
use HoseinMomeni\MahexWoo\Enterprise\OrderWarehousePlanner;
use HoseinMomeni\MahexWoo\V1\ActivityLog;

/**
 * Version 1.0 shipment service.
 *
 * Deliberately local-only: no network provider is consulted. This keeps
 * waybills, tracking lifecycle and multi-warehouse parcels deterministic and
 * fully controlled by WooCommerce.
 */
final class ShipmentService {
	public function __construct(
		private readonly OrderShipmentStore $store = new OrderShipmentStore(),
		private readonly LocalShipmentLifecycle $local = new LocalShipmentLifecycle(),
	) {}

	public function needsCreation( \WC_Order $order ): bool {
		$existing = count( $this->store->getAll( $order ) );
		$plans = EnterpriseConfig::bool( 'enabled', true ) && EnterpriseConfig::bool( 'multi_warehouse', true ) ? OrderWarehousePlanner::plan( $order ) : array();
		return $existing < max( 1, count( $plans ) );
	}

	public function create( \WC_Order $order, int $actorId = 0, bool $force = false ): Shipment {
		$existing = $this->store->getAll( $order );
		$plans = EnterpriseConfig::bool( 'enabled', true ) && EnterpriseConfig::bool( 'multi_warehouse', true ) ? OrderWarehousePlanner::plan( $order ) : array();
		if ( count( $plans ) <= 1 ) {
			$current = $existing[0] ?? null;
			if ( $current && ! $force ) return $current;
			$shipment = $this->createOne( $order, $force, $plans[0] ?? null, 0 );
			$this->store->save( $order, $shipment, $force ? 'local_reissue' : 'local_create', $actorId );
			ActivityLog::write( $force ? 'shipment.reissued' : 'shipment.created', $force ? 'مرسوله محلی مجدداً صادر شد.' : 'مرسوله محلی ساخته شد.', $order->get_id(), array( 'tracking' => $shipment->tracking_code ), $actorId );
			return $shipment;
		}

		if ( ! $force && count( $existing ) >= count( $plans ) ) return $existing[0];
		$working = $force ? array() : $existing;
		foreach ( $plans as $index => $plan ) {
			if ( ! $force && isset( $working[ $index ] ) ) continue;
			$working[ $index ] = $this->createOne( $order, $force, $plan, $index );
			ksort( $working );
			$this->store->saveAll( $order, array_values( $working ), $force ? 'multi_local_reissue_partial' : 'multi_local_create_partial', $actorId );
		}
		$shipments = array_values( $working );
		$this->store->saveAll( $order, $shipments, $force ? 'multi_local_reissue' : 'multi_local_create', $actorId );
		ActivityLog::write( $force ? 'shipment.multi_reissued' : 'shipment.multi_created', 'مرسوله‌های چندبسته‌ای محلی ذخیره شدند.', $order->get_id(), array( 'parcels' => count( $shipments ) ), $actorId );
		return $shipments[0];
	}

	public function refresh( \WC_Order $order, int $actorId = 0 ): ?Shipment {
		$all = $this->store->getAll( $order ); if ( ! $all ) return null;
		$nextAll = array(); $now = new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) );
		foreach ( $all as $current ) $nextAll[] = $this->local->refresh( $current, $now );
		if ( count( $nextAll ) > 1 ) $this->store->saveAll( $order, $nextAll, 'multi_local_refresh', $actorId ); else $this->store->save( $order, $nextAll[0], 'local_refresh', $actorId );
		ActivityLog::write( 'shipment.refreshed', 'وضعیت محلی مرسوله به‌روزرسانی شد.', $order->get_id(), array( 'parcels' => count( $nextAll ) ), $actorId );
		return $nextAll[0];
	}

	public function cancel( \WC_Order $order, int $actorId = 0 ): ?Shipment {
		$all = $this->store->getAll( $order ); if ( ! $all ) return null;
		$nextAll = array();
		foreach ( $all as $current ) {
			if ( 'canceled' === $current->status ) { $nextAll[] = $current; continue; }
			$now = gmdate( 'c' );
			$nextAll[] = new Shipment( $current->shipment_id, $current->tracking_code, $current->waybill_number, 'canceled', $current->package_snapshot, $current->created_at, $now, $current->revision + 1 );
		}
		if ( count( $nextAll ) > 1 ) $this->store->saveAll( $order, $nextAll, 'multi_local_cancel', $actorId ); else $this->store->save( $order, $nextAll[0], 'local_cancel', $actorId );
		ActivityLog::write( 'shipment.canceled', 'مرسوله محلی لغو شد.', $order->get_id(), array( 'parcels' => count( $nextAll ) ), $actorId );
		return $nextAll[0];
	}

	private function createOne( \WC_Order $order, bool $force, ?array $plan, int $index ): Shipment {
		$now = new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) );
		$snapshot = $this->packageSnapshot( $order, $plan, $index );
		if ( $force ) $snapshot['_reissue_nonce'] = $now->format( 'YmdHis.u' );
		return $this->local->create( $order->get_id(), $snapshot, $now );
	}

	private function packageSnapshot( \WC_Order $order, ?array $plan, int $index ): array {
		$items = array();
		if ( is_array( $plan['items'] ?? null ) ) $items = $plan['items'];
		else foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			$items[] = array( 'product_id'=>$item->get_product_id(),'variation_id'=>$item->get_variation_id(),'name'=>$item->get_name(),'quantity'=>$item->get_quantity(),'total'=>(float)$item->get_total(),'weight'=>$product?$product->get_weight():'','dimensions'=>$product?array('length'=>$product->get_length(),'width'=>$product->get_width(),'height'=>$product->get_height()):array() );
		}
		$warehouse = is_array( $plan['warehouse'] ?? null ) ? $plan['warehouse'] : array();
		return array(
			'items'=>$items,'item_count'=>(int)($plan['item_count']??$order->get_item_count()),
			'declared_value'=>(float)array_sum(array_map(static fn($i)=>is_array($i)?(float)($i['total']??0):0,$items)) ?: $order->get_total(),
			'currency'=>$order->get_currency(),'weight_unit'=>get_option('woocommerce_weight_unit','kg'),'dimension_unit'=>get_option('woocommerce_dimension_unit','cm'),
			'_parcel_index'=>$index,'_warehouse_id'=>(string)($warehouse['id']??''),'warehouse'=>$warehouse,
		);
	}
}
