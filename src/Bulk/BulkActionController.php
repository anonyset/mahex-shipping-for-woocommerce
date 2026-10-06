<?php

namespace HoseinMomeni\MahexWoo\Bulk;

final class BulkActionController {
	public const ACTION       = 'hm_mahex_bulk_shipments';
	public const NONCE_ACTION = 'hm_mahex_bulk_shipments';

	public function __construct( private readonly BulkQueueService $service ) {}

	public function register(): void {
		if ( function_exists( 'add_action' ) ) {
			add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
		}
	}

	public function handle(): never {
		if ( ! \HoseinMomeni\MahexWoo\Enterprise\Roles::canManage() ) {
			wp_die( esc_html__( 'دسترسی کافی ندارید.', 'mahex-shipping-for-woocommerce' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( self::NONCE_ACTION, '_hm_mahex_nonce' );
		$referer = wp_get_referer() ?: admin_url( 'admin.php?page=hm-mahex-shipments' );

		try {
			$operation  = BulkOperation::from_input( sanitize_key( wp_unslash( $_POST['operation'] ?? '' ) ) );
			$order_ids  = array_map( 'absint', (array) wp_unslash( $_POST['order_ids'] ?? array() ) );
			$request_id = sanitize_text_field( wp_unslash( $_POST['request_id'] ?? '' ) );
			if ( array() === array_filter( $order_ids ) ) {
				throw new \InvalidArgumentException( 'No orders selected.' );
			}
			$progress = $this->service->enqueue( $operation, $order_ids, $request_id );
			$args = array(
				'hm_mahex_bulk_status' => 'queued',
				'hm_mahex_bulk_total' => $progress->total,
				'hm_mahex_bulk_queued' => $progress->queued,
				'hm_mahex_bulk_failed' => $progress->failed,
				'hm_mahex_bulk_duplicates' => $progress->duplicates,
			);
		} catch ( \Throwable $error ) {
			$args = array( 'hm_mahex_bulk_status' => 'invalid' );
		}

		wp_safe_redirect( add_query_arg( $args, remove_query_arg( array( 'hm_mahex_bulk_status', 'hm_mahex_bulk_total', 'hm_mahex_bulk_queued', 'hm_mahex_bulk_failed', 'hm_mahex_bulk_duplicates' ), $referer ) ) );
		exit;
	}
}
