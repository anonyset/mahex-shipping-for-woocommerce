<?php

namespace HoseinMomeni\MahexWoo\Documents;

use HoseinMomeni\MahexWoo\Barcode\OrderBarcodeRepository;
use HoseinMomeni\MahexWoo\Shipments\OrderShipmentStore;

/** Builds one isolated waybill per WC_Order using CRUD methods (HPOS compatible). */
final class OrderWaybillFactory {
	public function __construct( private readonly OrderBarcodeRepository $numbers = new OrderBarcodeRepository() ) {}

	public function from_order( \WC_Order $order ): WaybillData {
		$barcode  = $this->numbers->for_order( $order );
		$shipment = ( new OrderShipmentStore() )->get( $order );
		$waybill  = $shipment?->waybill_number ?: (string) $order->get_meta( '_hm_mahex_waybill_number', true );
		$tracking = $shipment?->tracking_code ?: (string) $order->get_meta( '_hm_mahex_tracking_code', true );
		if ( '' === trim( $waybill ) ) {
			$waybill = 'MHX-' . $barcode;
		}
		if ( '' === trim( $tracking ) ) {
			$tracking = $barcode;
		}

		$items       = array();
		$count       = 0;
		$weight_kg   = 0.0;
		$dimensions  = array();
		foreach ( $order->get_items() as $item ) {
			$quantity = max( 1, (int) $item->get_quantity() );
			$count   += $quantity;
			$product  = $item->get_product();
			$description = $product ? trim( (string) $product->get_meta( '_hm_mahex_contents_description', true ) ) : '';
			if ( '' === $description && $product && $product->is_type( 'variation' ) ) {
				$parent = wc_get_product( $product->get_parent_id() );
				$description = $parent ? trim( (string) $parent->get_meta( '_hm_mahex_contents_description', true ) ) : '';
			}
			$items[] = $description ?: (string) $item->get_name();
			if ( $product ) {
				$raw_weight = (float) $product->get_weight();
				if ( $raw_weight > 0 ) {
					$weight_kg += (float) wc_get_weight( $raw_weight, 'kg' ) * $quantity;
				}
				$l = (float) $product->get_length();
				$w = (float) $product->get_width();
				$h = (float) $product->get_height();
				if ( $l > 0 && $w > 0 && $h > 0 ) {
					$dimensions[] = wc_format_decimal( $l ) . '×' . wc_format_decimal( $w ) . '×' . wc_format_decimal( $h ) . ' ' . get_option( 'woocommerce_dimension_unit', 'cm' );
				}
			}
		}

		$created = $order->get_date_created();
		$recipient_address = trim( implode( '، ', array_filter( array(
			$order->get_shipping_address_1() ?: $order->get_billing_address_1(),
			$order->get_shipping_address_2() ?: $order->get_billing_address_2(),
			$order->get_shipping_city() ?: $order->get_billing_city(),
			$order->get_shipping_state() ?: $order->get_billing_state(),
			$order->get_shipping_postcode() ?: $order->get_billing_postcode(),
		) ) ) );
		$destination = $order->get_shipping_city() ?: $order->get_billing_city();
		if ( '' === $destination ) {
			$destination = $order->get_shipping_state() ?: $order->get_billing_state();
		}

		$settings = get_option( 'hm_mahex_settings', array() );
		$settings = is_array( $settings ) ? $settings : array();
		$sender_address = trim( (string) ( $settings['origin_address'] ?? '' ) );
		if ( '' === $sender_address ) {
			$sender_address = trim( implode( '، ', array_filter( array( get_option( 'woocommerce_store_address', '' ), get_option( 'woocommerce_store_address_2', '' ), get_option( 'woocommerce_store_city', '' ) ) ) ) );
		}
		$origin      = trim( (string) ( $settings['origin_city'] ?? '' ) ) ?: (string) get_option( 'woocommerce_store_city', 'فروشگاه' );
		$sender_name = trim( (string) ( $settings['sender_name'] ?? '' ) ) ?: (string) get_bloginfo( 'name' );
		$sender_phone = trim( (string) ( $settings['sender_phone'] ?? '' ) ) ?: (string) get_option( 'woocommerce_store_phone', '' );
		$recipient_phone = method_exists( $order, 'get_shipping_phone' ) ? (string) $order->get_shipping_phone() : '';
		$recipient_phone = $recipient_phone ?: (string) $order->get_billing_phone();

		$chargeable_kg = (float) $this->shipping_meta( $order, 'hm_mahex_chargeable_weight_kg', 0 );
		$weight_label  = wc_format_decimal( $chargeable_kg > 0 ? $chargeable_kg : $weight_kg, 3 ) . ' kg';
		if ( $chargeable_kg > 0 ) {
			$weight_label .= ' (قابل پرداخت)';
		}
		$dimension_label = array() !== $dimensions ? implode( ' | ', array_slice( array_values( array_unique( $dimensions ) ), 0, 3 ) ) : '—';
		$packaging = (float) $this->shipping_meta( $order, 'hm_mahex_packaging', 0 );
		$packaging_label = wp_strip_all_tags( wc_price( $packaging, array( 'currency' => $order->get_currency() ) ) );

		return new WaybillData(
			$waybill,
			$tracking,
			$created ? $created->date_i18n( 'Y/m/d H:i' ) : wp_date( 'Y/m/d H:i' ),
			$origin,
			$destination ?: 'نامشخص',
			$sender_name,
			trim( $order->get_formatted_shipping_full_name() ?: $order->get_formatted_billing_full_name() ),
			$recipient_address ?: 'ثبت نشده',
			$recipient_phone,
			implode( '، ', array_values( array_unique( $items ) ) ) ?: 'کالا',
			max( 1, $count ),
			$weight_label,
			$dimension_label,
			wp_strip_all_tags( wc_price( (float) $order->get_shipping_total(), array( 'currency' => $order->get_currency() ) ) ),
			$packaging_label,
			$order->get_payment_method() === 'cod' ? wp_strip_all_tags( wc_price( (float) $order->get_total(), array( 'currency' => $order->get_currency() ) ) ) : '0',
			$barcode,
			$sender_address,
			$sender_phone
		);
	}

	private function shipping_meta( \WC_Order $order, string $key, mixed $default = '' ): mixed {
		foreach ( $order->get_items( 'shipping' ) as $shipping_item ) {
			$value = $shipping_item->get_meta( $key, true );
			if ( '' !== $value && null !== $value ) {
				return $value;
			}
		}
		return $default;
	}
}
