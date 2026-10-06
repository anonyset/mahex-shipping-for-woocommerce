<?php

namespace HoseinMomeni\MahexWoo\Analytics;

use HoseinMomeni\MahexWoo\Pro\FeatureSettings;
use HoseinMomeni\MahexWoo\Shipments\OrderShipmentStore;

final class ShippingAnalytics {
	/** @return array<string,mixed> */
	public function summarize( string $after = '', string $before = '', int $limit = 500 ): array {
		$args = array( 'limit' => max( 1, min( 2000, $limit ) ), 'orderby' => 'date', 'order' => 'DESC', 'status' => array_keys( wc_get_order_statuses() ) );
		if ( '' !== $after || '' !== $before ) {
			$start = '' !== $after ? $after . ' 00:00:00' : '1970-01-01 00:00:00';
			$end = '' !== $before ? $before . ' 23:59:59' : wp_date( 'Y-m-d 23:59:59' );
			$args['date_created'] = $start . '...' . $end;
		}
		$orders = wc_get_orders( $args );
		$store = new OrderShipmentStore();
		$result = array(
			'orders' => 0, 'shipments' => 0, 'shipping_revenue' => 0.0, 'packaging_revenue' => 0.0,
			'carrier_cost' => 0.0, 'profit' => 0.0, 'statuses' => array(), 'customers' => array(), 'cities' => array(),
			'daily' => array(), 'rows' => array(),
		);
		$estimatePercent = FeatureSettings::float( 'carrier_cost_percent', 80, 0, 100 );
		foreach ( $orders as $order ) {
			if ( ! $order instanceof \WC_Order ) continue;
			++$result['orders'];
			$shipping = (float) $order->get_shipping_total();
			$packaging = (float) $this->shippingMeta( $order, 'hm_mahex_packaging', 0 );
			$shipment = $store->get( $order );
			if ( $shipment ) {
				++$result['shipments'];
				$result['statuses'][ $shipment->status ] = 1 + (int) ( $result['statuses'][ $shipment->status ] ?? 0 );
			}
			$actual = $order->get_meta( '_hm_mahex_actual_carrier_cost', true );
			$carrier = is_numeric( $actual ) && (float) $actual >= 0 ? (float) $actual : $shipping * $estimatePercent / 100;
			$profit = $shipping - $carrier;
			$result['shipping_revenue'] += $shipping;
			$result['packaging_revenue'] += $packaging;
			$result['carrier_cost'] += $carrier;
			$result['profit'] += $profit;
			$city = trim( (string) ( $order->get_shipping_city() ?: $order->get_billing_city() ) );
			$province = \HoseinMomeni\MahexWoo\Shipping\PricingSettings::province_name( (string) ( $order->get_shipping_state() ?: $order->get_billing_state() ) );
			if ( '' !== $city ) $result['cities'][ $city ] = 1 + (int) ( $result['cities'][ $city ] ?? 0 );
			$key = $order->get_customer_id() > 0 ? 'u:' . $order->get_customer_id() : 'e:' . strtolower( trim( (string) $order->get_billing_email() ) );
			$name = trim( $order->get_formatted_billing_full_name() ) ?: trim( (string) $order->get_billing_email() ) ?: 'مهمان';
			if ( ! isset( $result['customers'][ $key ] ) ) $result['customers'][ $key ] = array( 'name' => $name, 'orders' => 0, 'shipping' => 0.0 );
			++$result['customers'][ $key ]['orders'];
			$result['customers'][ $key ]['shipping'] += $shipping;
			$date = $order->get_date_created();
			$day = $date ? $date->date( 'Y-m-d' ) : '';
			if ( '' !== $day ) {
				if ( ! isset( $result['daily'][ $day ] ) ) $result['daily'][ $day ] = array( 'orders' => 0, 'shipping' => 0.0, 'packaging' => 0.0, 'profit' => 0.0 );
				++$result['daily'][ $day ]['orders'];
				$result['daily'][ $day ]['shipping'] += $shipping;
				$result['daily'][ $day ]['packaging'] += $packaging;
				$result['daily'][ $day ]['profit'] += $profit;
			}
			$result['rows'][] = array(
				'id' => $order->get_id(), 'date' => $date ? $date->date_i18n( 'Y/m/d H:i' ) : '', 'name' => $name,
				'city' => $city, 'province' => $province, 'shipping' => $shipping, 'carrier' => $carrier, 'profit' => $profit,
				'status' => $shipment?->status ?? 'none', 'tracking' => $shipment?->tracking_code ?? '',
			);
		}
		arsort( $result['cities'] );
		uasort( $result['customers'], static fn( $a, $b ) => ( $b['orders'] <=> $a['orders'] ) ?: ( $b['shipping'] <=> $a['shipping'] ) );
		ksort( $result['daily'] );
		return $result;
	}

	private function shippingMeta( \WC_Order $order, string $key, mixed $default = '' ): mixed {
		foreach ( $order->get_items( 'shipping' ) as $item ) {
			$value = $item->get_meta( $key, true );
			if ( '' !== $value && null !== $value ) return $value;
		}
		return $default;
	}
}
