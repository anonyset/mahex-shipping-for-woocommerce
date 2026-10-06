<?php

namespace HoseinMomeni\MahexWoo\Shipments;

defined( 'ABSPATH' ) || exit;

final class ShipmentActions {
	public const CREATE_ACTION  = 'hm_mahex_create_shipment';
	public const REFRESH_ACTION = 'hm_mahex_refresh_shipment';
	public const CANCEL_ACTION  = 'hm_mahex_cancel_shipment';
	public const REISSUE_ACTION = 'hm_mahex_reissue_shipment';
	public const NONCE_ACTION   = 'hm_mahex_order_shipment';

	public static function register(): void {
		foreach ( array( self::CREATE_ACTION => 'create', self::REFRESH_ACTION => 'refresh', self::CANCEL_ACTION => 'cancel', self::REISSUE_ACTION => 'reissue' ) as $action => $method ) {
			add_action( 'admin_post_' . $action, array( self::class, $method ) );
		}
	}

	public static function create(): void { self::handle( 'create' ); }
	public static function refresh(): void { self::handle( 'refresh' ); }
	public static function cancel(): void { self::handle( 'cancel' ); }
	public static function reissue(): void { self::handle( 'reissue' ); }

	private static function handle( string $operation ): void {
		$order_id = isset( $_POST['order_id'] ) && is_scalar( $_POST['order_id'] ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0;
		$nonce = isset( $_POST['_hm_mahex_nonce'] ) && is_string( $_POST['_hm_mahex_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_hm_mahex_nonce'] ) ) : '';
		$order = $order_id ? wc_get_order( $order_id ) : false;
		if ( ! $order || ! current_user_can( 'edit_shop_order', $order_id ) || ! wp_verify_nonce( $nonce, self::NONCE_ACTION . ':' . $order_id ) ) {
			wp_die( esc_html__( 'درخواست مرسوله معتبر نیست یا دسترسی کافی ندارید.', 'mahex-shipping-for-woocommerce' ), esc_html__( 'درخواست نامعتبر', 'mahex-shipping-for-woocommerce' ), array( 'response' => 403 ) );
		}
		$lock = new OptionOperationLock();
		if ( ! $lock->acquire( $order_id ) ) self::redirect( $order, 'locked' );
		$result = 'updated';
		try {
			$service = new ShipmentService();
			$current = ( new OrderShipmentStore() )->get( $order );
			switch ( $operation ) {
				case 'create':
					if ( ! $service->needsCreation( $order ) ) { $result = 'exists'; break; }
					$service->create( $order, get_current_user_id() ); $result = $current ? 'updated' : 'updated';
					break;
				case 'refresh':
					if ( ! $service->refresh( $order, get_current_user_id() ) ) $result = 'missing';
					break;
				case 'cancel':
					if ( ! $service->cancel( $order, get_current_user_id() ) ) $result = 'missing'; else $result = 'canceled';
					break;
				case 'reissue':
					$service->create( $order, get_current_user_id(), true ); $result = 'reissued';
					break;
			}
		} catch ( \Throwable $error ) {
			$result = 'error';
			if ( function_exists( 'wc_get_logger' ) ) wc_get_logger()->error( 'Mahex shipment operation failed.', array( 'source' => 'hm-mahex-shipping', 'operation' => $operation, 'order_id' => $order_id, 'exception' => get_class( $error ), 'message' => $error->getMessage() ) );
		} finally {
			$lock->release( $order_id );
		}
		self::redirect( $order, $result );
	}

	private static function redirect( \WC_Order $order, string $result ): never {
		wp_safe_redirect( add_query_arg( 'hm_mahex_result', $result, $order->get_edit_order_url() ) );
		exit;
	}
}
