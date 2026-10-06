<?php

namespace HoseinMomeni\MahexWoo\Documents;

use HoseinMomeni\MahexWoo\Barcode\Code128CBarcodeRenderer;
use HoseinMomeni\MahexWoo\Barcode\OrderBarcodeRepository;

defined( 'ABSPATH' ) || exit;

/** Registerable HPOS/legacy order UI and nonce-protected printable document endpoint. */
final class OrderDocumentController {
	private const ACTION = 'hm_mahex_print_waybill';

	public static function register(): void {
		add_action( 'add_meta_boxes_shop_order', array( self::class, 'legacy_box' ) );
		add_action( 'add_meta_boxes_woocommerce_page_wc-orders', array( self::class, 'hpos_box' ) );
		add_action( 'admin_post_' . self::ACTION, array( self::class, 'print_document' ) );
		add_action( 'admin_menu', array( self::class, 'settings_menu' ), 30 );
		add_action( 'admin_init', array( self::class, 'settings' ) );
		add_filter( 'option_page_capability_hm_mahex_waybill', array( self::class, 'settings_capability' ) );
	}

	public static function legacy_box(): void { add_meta_box( 'hm-mahex-document', 'بارنامه و تگ ماهکس', array( self::class, 'render_box' ), 'shop_order', 'side', 'high' ); }
	public static function hpos_box(): void { add_meta_box( 'hm-mahex-document', 'بارنامه و تگ ماهکس', array( self::class, 'render_box' ), 'woocommerce_page_wc-orders', 'side', 'high' ); }

	public static function render_box( $subject ): void {
		$order = $subject instanceof \WC_Order ? $subject : wc_get_order( $subject instanceof \WP_Post ? $subject->ID : 0 );
		if ( ! $order || ( ! current_user_can( 'edit_shop_order', $order->get_id() ) && ! \HoseinMomeni\MahexWoo\Enterprise\Roles::canPrint() ) ) return;
		echo '<p>این سفارش بارنامه و بارکد ۱۰ رقمی مستقل خود را دارد.</p><p style="display:flex;gap:6px;flex-wrap:wrap">';
		foreach ( array( 'waybill' => 'چاپ بارنامه', 'label' => 'چاپ تگ', 'combined' => 'خروجی ترکیبی' ) as $document => $label ) {
			$url = wp_nonce_url( add_query_arg( array( 'action' => self::ACTION, 'order_id' => $order->get_id(), 'document' => $document ), admin_url( 'admin-post.php' ) ), self::ACTION . ':' . $order->get_id() );
			echo '<a class="button' . ( 'waybill' === $document ? ' button-primary' : '' ) . '" target="_blank" rel="noopener" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</p>';
	}

	public static function print_document(): void {
		$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
		check_admin_referer( self::ACTION . ':' . $order_id );
		if ( ! $order_id || ( ! current_user_can( 'edit_shop_order', $order_id ) && ! \HoseinMomeni\MahexWoo\Enterprise\Roles::canPrint() ) ) wp_die( esc_html__( 'اجازه دسترسی ندارید.', 'mahex-shipping-for-woocommerce' ), '', array( 'response' => 403 ) );
		$order = wc_get_order( $order_id );
		if ( ! $order ) wp_die( esc_html__( 'سفارش پیدا نشد.', 'mahex-shipping-for-woocommerce' ), '', array( 'response' => 404 ) );
		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );
		$type = isset( $_GET['document'] ) && is_string( $_GET['document'] ) ? sanitize_key( wp_unslash( $_GET['document'] ) ) : 'waybill';
		if ( ! in_array( $type, array( 'waybill', 'label', 'combined' ), true ) ) wp_die( esc_html__( 'نوع سند نامعتبر است.', 'mahex-shipping-for-woocommerce' ), '', array( 'response' => 400 ) );
		$renderer = new WaybillRenderer( new Code128CBarcodeRenderer() );
		$data = ( new OrderWaybillFactory() )->from_order( $order );
		$html = 'label' === $type ? $renderer->render_label( $data ) : ( 'combined' === $type ? $renderer->render( $data ) : $renderer->render_waybill( $data ) );
		do_action( 'hm_mahex_document_printed', $order_id, $type, 1 );
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	public static function settings_menu(): void {
		add_submenu_page( 'hm-mahex', 'تنظیمات بارنامه', 'تنظیمات بارنامه', 'manage_woocommerce', 'hm-mahex-waybill-settings', array( self::class, 'settings_page' ) );
	}

	public static function settings(): void {
		register_setting( 'hm_mahex_waybill', OrderBarcodeRepository::OPTION_START, array( 'type' => 'integer', 'sanitize_callback' => array( self::class, 'sanitize_start' ), 'default' => OrderBarcodeRepository::DEFAULT_START ) );
		add_settings_section( 'hm_mahex_waybill_numbering', 'شماره‌گذاری بارنامه', '__return_false', 'hm-mahex-waybill-settings' );
		add_settings_field( OrderBarcodeRepository::OPTION_START, 'شروع شماره‌گذاری', array( self::class, 'start_field' ), 'hm-mahex-waybill-settings', 'hm_mahex_waybill_numbering' );
	}

	public static function settings_capability(): string { return 'manage_woocommerce'; }

	public static function sanitize_start( $value ): int {
		$value = absint( $value );
		if ( $value < OrderBarcodeRepository::DEFAULT_START || $value > 999999999 ) {
			add_settings_error( OrderBarcodeRepository::OPTION_START, 'invalid_sequence', 'عدد شروع باید بین ۱۰۰۴۰۰ و ۹۹۹۹۹۹۹۹۹ باشد.' );
			return (int) get_option( OrderBarcodeRepository::OPTION_START, OrderBarcodeRepository::DEFAULT_START );
		}
		$current = (int) get_option( OrderBarcodeRepository::OPTION_SEQUENCE, 0 );
		if ( $current > 0 && $value <= $current ) {
			add_settings_error( OrderBarcodeRepository::OPTION_START, 'sequence_in_use', 'عدد شروع باید از آخرین شماره تخصیص‌یافته بزرگ‌تر باشد.' );
			return (int) get_option( OrderBarcodeRepository::OPTION_START, OrderBarcodeRepository::DEFAULT_START );
		}
		update_option( OrderBarcodeRepository::OPTION_SEQUENCE, $value - 1, false );
		return $value;
	}

	public static function start_field(): void {
		$value = (int) get_option( OrderBarcodeRepository::OPTION_START, OrderBarcodeRepository::DEFAULT_START );
		echo '<input name="' . esc_attr( OrderBarcodeRepository::OPTION_START ) . '" type="number" min="100400" max="999999999" step="1" value="' . esc_attr( (string) $value ) . '" class="regular-text" dir="ltr"><p class="description">هر سفارش فقط یک شماره مستقل دریافت می‌کند. کاهش شماره پس از تخصیص مجاز نیست.</p>';
	}

	public static function settings_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) wp_die( esc_html__( 'اجازه دسترسی ندارید.', 'mahex-shipping-for-woocommerce' ), '', array( 'response' => 403 ) );
		echo '<div class="wrap" dir="rtl"><h1>تنظیمات بارنامه ماهکس</h1><form method="post" action="options.php">';
		settings_fields( 'hm_mahex_waybill' ); do_settings_sections( 'hm-mahex-waybill-settings' ); submit_button( 'ذخیره تنظیمات' );
		echo '</form></div>';
	}
}
