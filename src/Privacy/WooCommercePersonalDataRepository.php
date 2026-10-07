<?php

namespace HoseinMomeni\MahexWoo\Privacy;

use HoseinMomeni\MahexWoo\Shipments\OrderShipmentStore;

defined( 'ABSPATH' ) || exit;

final class WooCommercePersonalDataRepository implements PersonalDataRepository {
	public function export_for_email( string $email, int $page, int $page_size ): array {
		$orders = $this->orders( $email, $page, $page_size );
		$data   = array();
		foreach ( $orders as $order ) {
            foreach (['_hm_mahex_v34_dispatch'=>'Dispatch review record','_hm_mahex_v34_service_case'=>'Local service promise/incident record','_hm_mahex_v33_return'=>'Return/claim record','_hm_mahex_v33_station'=>'Packing/handover record','_hm_mahex_v33_sms_consent'=>'SMS consent'] as $key=>$label) {
                $value=$order->get_meta($key,true);
                if ($value!=='' && $value!==[]) $data[]=array('name'=>$label.' #'.$order->get_id(),'value'=>wp_json_encode($value,JSON_UNESCAPED_UNICODE));
            }
			$shipments = $order->get_meta( OrderShipmentStore::MULTI_META, true );
			if ( ! is_array( $shipments ) || empty( $shipments ) ) {
				$single = $order->get_meta( OrderShipmentStore::SHIPMENT_META, true );
				$shipments = is_array( $single ) && ! empty( $single ) ? array( $single ) : array();
			}
			if ( empty( $shipments ) ) {
				continue;
			}
			$data[] = array( 'name' => 'Order', 'value' => '#' . $order->get_id() );
			foreach ( $shipments as $index => $shipment ) {
				if ( ! is_array( $shipment ) ) {
					continue;
				}
				$data[] = array( 'name' => 'Parcel', 'value' => (string) ( $index + 1 ) );
				foreach ( array( 'shipment_id' => 'Shipment ID', 'tracking_code' => 'Tracking code', 'waybill_number' => 'Waybill number', 'status' => 'Shipment status' ) as $key => $label ) {
					if ( isset( $shipment[ $key ] ) && is_scalar( $shipment[ $key ] ) ) {
						$data[] = array( 'name' => $label, 'value' => sanitize_text_field( (string) $shipment[ $key ] ) );
					}
				}
			}
			$risk = $order->get_meta( '_hm_mahex_risk_score', true );
			if ( '' !== $risk ) {
				$data[] = array( 'name' => 'Delivery risk score', 'value' => (string) absint( $risk ) );
			}
			$ndr = $order->get_meta( '_hm_mahex_ndr', true );
			if ( is_array( $ndr ) && ! empty( $ndr['reason'] ) ) {
				$data[] = array( 'name' => 'Non-delivery reason', 'value' => sanitize_text_field( (string) $ndr['reason'] ) );
			}
		}
		return $data;
	}

	public function erase_for_email( string $email, int $page, int $page_size ): array {
		$orders  = $this->orders( $email, $page, $page_size );
		$removed = 0;
		$order_ids = array();
		foreach ( $orders as $order ) {
			$order_ids[] = $order->get_id();
			$keys = array(
				OrderShipmentStore::SHIPMENT_META,
				OrderShipmentStore::MULTI_META,
				OrderShipmentStore::AUDIT_META,
				OrderShipmentStore::TRACKING_META,
				OrderShipmentStore::TRACKING_INDEX,
				OrderShipmentStore::WAYBILL_META,
				OrderShipmentStore::STATUS_META,
				'_hm_mahex_risk_score',
				'_hm_mahex_risk_reasons',
				'_hm_mahex_risk_review',
				'_hm_mahex_ndr',
                '_hm_mahex_v33_return',
                '_hm_mahex_v33_official_documents',
                '_hm_mahex_v33_station',
                '_hm_mahex_v33_sms_consent',
                '_hm_mahex_v34_dispatch',
                '_hm_mahex_v34_service_case',
			);
			$hasData = false;
			foreach ( $keys as $key ) {
				if ( '' !== $order->get_meta( $key, true ) ) $hasData = true;
				$order->delete_meta_data( $key );
			}
			if ( $hasData ) {
				$order->save();
				++$removed;
			}
		}
		if ( $order_ids ) {
            \HoseinMomeni\MahexWoo\V33\NotificationCenter::erase($order_ids);
			global $wpdb;
			$ids = implode( ',', array_map( 'absint', $order_ids ) );
			if ( '' !== $ids ) $wpdb->query( "DELETE FROM {$wpdb->prefix}hm_mahex_tasks WHERE order_id IN ($ids)" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- IDs are absint-only.
		}
		return array(
			'items_removed'  => $removed,
			'items_retained' => 0,
			'messages'       => array(),
			'done'           => count( $orders ) < $page_size,
		);
	}

	/** @return list<\WC_Order> */
	private function orders( string $email, int $page, int $page_size ): array {
		$orders = wc_get_orders(
			array(
				'billing_email' => sanitize_email( $email ),
				'limit'         => max( 1, min( 100, $page_size ) ),
				'page'          => max( 1, $page ),
				'orderby'       => 'ID',
				'order'         => 'ASC',
			)
		);
		return array_values( array_filter( $orders, static fn( $order ): bool => $order instanceof \WC_Order ) );
	}
}
