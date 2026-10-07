<?php

namespace HoseinMomeni\MahexWoo\Orders;

use HoseinMomeni\MahexWoo\Shipments\OrderShipmentStore;
use HoseinMomeni\MahexWoo\Shipments\Shipment;
use HoseinMomeni\MahexWoo\Shipments\ShipmentActions;
use HoseinMomeni\MahexWoo\Shipments\ShipmentService;

defined( 'ABSPATH' ) || exit;

final class OrderPanel {
	private const STATUS_LABELS = array(
		'created'          => 'ثبت شده',
		'picked_up'        => 'جمع‌آوری شده',
		'in_transit'       => 'در مسیر',
		'out_for_delivery' => 'در حال تحویل',
		'delivered'        => 'تحویل شده',
		'returned'         => 'برگشتی',
		'failed'           => 'ناموفق',
		'canceled'         => 'لغوشده',
	);

	public static function register(): void {
		add_action( 'add_meta_boxes_shop_order', array( self::class, 'add_legacy_meta_box' ) );
		add_action( 'add_meta_boxes_woocommerce_page_wc-orders', array( self::class, 'add_hpos_meta_box' ) );
	}

	public static function add_legacy_meta_box(): void {
		add_meta_box( 'hm-mahex-order', 'مرسوله ماهکس', array( self::class, 'render' ), 'shop_order', 'side', 'high' );
	}

	public static function add_hpos_meta_box(): void {
		add_meta_box( 'hm-mahex-order', 'مرسوله ماهکس', array( self::class, 'render' ), 'woocommerce_page_wc-orders', 'side', 'high' );
	}

	public static function render( $subject ): void {
		$order = $subject instanceof \WC_Order ? $subject : wc_get_order( $subject instanceof \WP_Post ? $subject->ID : 0 );
		if ( ! $order ) {
			return;
		}

		$store = new OrderShipmentStore();
		$shipment = $store->get( $order );
		$shipments = $store->getAll( $order );
		echo '<div dir="rtl" class="hm-mahex-order-panel">';
		self::result_notice();
		if ( $shipment ) {
			if ( count( $shipments ) > 1 ) echo '<p><strong>' . esc_html( sprintf( '%d مرسوله برای این سفارش چندانباره', count( $shipments ) ) ) . '</strong></p>';
			foreach ( $shipments as $index => $parcel ) { if ( count($shipments)>1 ) echo '<h4 style="margin-bottom:6px">بسته ' . esc_html( (string) ($index+1) ) . '</h4>'; self::details( $parcel ); }
			self::risk( $order );
			self::financials( $order );
		} else {
			echo '<p>' . esc_html__( 'برای این سفارش هنوز مرسوله‌ای ثبت نشده است.', 'mahex-shipping-for-woocommerce' ) . '</p>';
		}
		if ( $shipment ) {
			if ( ( new ShipmentService() )->needsCreation( $order ) ) self::action_form( $order, ShipmentActions::CREATE_ACTION );
			self::action_form( $order, ShipmentActions::REFRESH_ACTION );
			if ( 'canceled' !== $shipment->status ) self::action_form( $order, ShipmentActions::CANCEL_ACTION );
			self::action_form( $order, ShipmentActions::REISSUE_ACTION );
			self::audit( $order );
		} else {
			self::action_form( $order, ShipmentActions::CREATE_ACTION );
		}
		echo '<p style="margin-bottom:0;color:#646970"><small>' . esc_html__( 'سند و وضعیت مرسوله به‌صورت محلی داخل فروشگاه مدیریت می‌شود.', 'mahex-shipping-for-woocommerce' ) . '</small></p></div>';
	}

	private static function result_notice(): void {
		$result = isset( $_GET['hm_mahex_result'] ) && is_string( $_GET['hm_mahex_result'] ) ? sanitize_key( wp_unslash( $_GET['hm_mahex_result'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$messages = array(
			'updated' => 'اطلاعات مرسوله با موفقیت به‌روزرسانی شد.',
			'exists'  => 'مرسوله قبلاً ثبت شده است؛ ثبت تکراری انجام نشد.',
			'missing' => 'ابتدا مرسوله را ثبت کنید.',
			'locked'  => 'عملیات دیگری روی این مرسوله در حال اجراست؛ دوباره تلاش کنید.',
			'error'   => 'عملیات مرسوله انجام نشد. گزارش خطا در لاگ ووکامرس ثبت شد.',
			'canceled' => 'مرسوله در سیستم فروشگاه لغو شد.',
			'reissued' => 'مرسوله با شناسه جدید مجدداً صادر شد.',
		);
		if ( isset( $messages[ $result ] ) ) {
			echo '<div class="notice notice-info inline"><p>' . esc_html( $messages[ $result ] ) . '</p></div>';
		}
	}

	private static function details( Shipment $shipment ): void {
		$rows = array(
			'شناسه مرسوله' => $shipment->shipment_id,
			'شماره بارنامه' => $shipment->waybill_number,
			'کد رهگیری'     => $shipment->tracking_code,
			'وضعیت'         => self::STATUS_LABELS[ $shipment->status ] ?? $shipment->status,
			'نسخه وضعیت'    => (string) $shipment->revision,
		);
		echo '<dl style="display:grid;grid-template-columns:1fr 1fr;gap:6px;margin:0 0 12px">';
		foreach ( $rows as $label => $value ) {
			echo '<dt style="font-weight:600">' . esc_html( $label ) . '</dt><dd style="margin:0;word-break:break-all">' . esc_html( $value ) . '</dd>';
		}
		echo '</dl>';

		$snapshot = $shipment->package_snapshot;
		printf(
			'<p><strong>%s</strong> %s<br><strong>%s</strong> %s %s</p>',
			esc_html__( 'اقلام:', 'mahex-shipping-for-woocommerce' ),
			esc_html( (string) ( $snapshot['item_count'] ?? 0 ) ),
			esc_html__( 'ارزش اظهارشده:', 'mahex-shipping-for-woocommerce' ),
			esc_html( (string) ( $snapshot['declared_value'] ?? '0' ) ),
			esc_html( (string) ( $snapshot['currency'] ?? '' ) )
		);
	}

	private static function risk( \WC_Order $order ): void {
		$score = (int) $order->get_meta( '_hm_mahex_risk_score', true );
		$reasons = $order->get_meta( '_hm_mahex_risk_reasons', true );
		$ndr = $order->get_meta( '_hm_mahex_ndr', true );
		if ( $score > 0 ) echo '<p><strong>ریسک تحویل:</strong> ' . esc_html( (string) $score ) . '/100' . ( is_array($reasons)&&$reasons ? '<br><small>' . esc_html( implode( '، ', array_map('strval',$reasons) ) ) . '</small>' : '' ) . '</p>';
		if ( is_array( $ndr ) && ! empty( $ndr['opened_at'] ) ) echo '<p><strong>NDR:</strong> ' . esc_html( (string) ( $ndr['reason'] ?? 'unknown' ) ) . '<br><small>' . esc_html( (string) ( $ndr['suggestion'] ?? '' ) ) . '</small></p>';
	}

	private static function financials( \WC_Order $order ): void {
		if (!\HoseinMomeni\MahexWoo\V33\Access::can('finance', $order->get_id())) return;
		$shipping = (float) $order->get_shipping_total();
		$actual = $order->get_meta( '_hm_mahex_actual_carrier_cost', true );
		$percent = \HoseinMomeni\MahexWoo\Pro\FeatureSettings::float( 'carrier_cost_percent', 80, 0, 100 );
		$carrier = is_numeric( $actual ) && (float) $actual >= 0 ? (float) $actual : $shipping * $percent / 100;
		$profit = $shipping - $carrier;
		$breakdown = '';
		foreach ( $order->get_items( 'shipping' ) as $shipping_item ) {
			$value = $shipping_item->get_meta( 'hm_mahex_rate_breakdown', true );
			if ( is_string( $value ) && '' !== $value ) { $breakdown = $value; break; }
		}
		echo '<hr><p><strong>درآمد ارسال:</strong> ' . wp_kses_post( wc_price( $shipping, array( 'currency' => $order->get_currency() ) ) ) . '<br><strong>هزینه واقعی/تخمینی حمل:</strong> ' . wp_kses_post( wc_price( $carrier, array( 'currency' => $order->get_currency() ) ) ) . '<br><strong>سود تخمینی:</strong> ' . wp_kses_post( wc_price( $profit, array( 'currency' => $order->get_currency() ) ) ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="hm_mahex_save_actual_cost"><input type="hidden" name="order_id" value="' . esc_attr( (string) $order->get_id() ) . '">';
		wp_nonce_field( 'hm_mahex_save_actual_cost' );
		echo '<p><label>هزینه واقعی حمل <input type="number" min="0" step="0.01" name="actual_cost" value="' . esc_attr( is_numeric( $actual ) ? (string) $actual : '' ) . '" style="width:100%"></label></p>';
		submit_button( 'ذخیره هزینه واقعی', 'secondary', 'submit', false );
		echo '</form>';
		if ( '' !== $breakdown ) {
			$data = json_decode( $breakdown, true );
			if ( is_array( $data ) && $data ) echo '<details style="margin-top:8px"><summary>ریز محاسبه نرخ</summary><code style="display:block;white-space:pre-wrap;direction:ltr;text-align:left">' . esc_html( implode( "\n", array_map( 'strval', $data ) ) ) . '</code></details>';
		}
	}

	private static function audit( \WC_Order $order ): void {
		$audit = $order->get_meta( OrderShipmentStore::AUDIT_META, true );
		if ( ! is_array( $audit ) || array() === $audit ) return;
		echo '<details style="margin-top:10px"><summary style="cursor:pointer;font-weight:600">تاریخچه عملیات</summary><ol style="margin-right:18px">';
		foreach ( array_slice( array_reverse( $audit ), 0, 8 ) as $row ) {
			$actor = isset( $row['actor_id'] ) ? get_userdata( (int) $row['actor_id'] ) : false;
			$name = $actor ? $actor->display_name : 'سیستم';
			echo '<li><code>' . esc_html( (string) ( $row['action'] ?? '' ) ) . '</code> · ' . esc_html( (string) ( $row['status'] ?? '' ) ) . ' · ' . esc_html( $name ) . '<br><small>' . esc_html( (string) ( $row['occurred_at'] ?? '' ) ) . '</small></li>';
		}
		echo '</ol></details>';
	}

	private static function action_form( \WC_Order $order, string $action ): void {
		$labels = array( ShipmentActions::CREATE_ACTION => 'ساخت مرسوله', ShipmentActions::REFRESH_ACTION => 'به‌روزرسانی وضعیت', ShipmentActions::CANCEL_ACTION => 'لغو مرسوله', ShipmentActions::REISSUE_ACTION => 'صدور مجدد' );
		$label = $labels[ $action ] ?? 'عملیات مرسوله';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="' . esc_attr( $action ) . '">';
		echo '<input type="hidden" name="order_id" value="' . esc_attr( (string) $order->get_id() ) . '">';
		wp_nonce_field( ShipmentActions::NONCE_ACTION . ':' . $order->get_id(), '_hm_mahex_nonce' );
		$type = ShipmentActions::CANCEL_ACTION === $action ? 'secondary' : ( ShipmentActions::REISSUE_ACTION === $action ? 'secondary' : 'primary' );
		submit_button( $label, $type, 'submit', false );
		echo '</form>';
	}
}
