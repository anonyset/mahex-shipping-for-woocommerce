<?php

namespace HoseinMomeni\MahexWoo\Checkout;

final class RatePresenter {
	public static function register(): void {
		add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue' ) );
		add_filter( 'woocommerce_cart_shipping_method_full_label', array( self::class, 'label' ), 20, 2 );
	}

	public static function enqueue(): void {
		if ( ! function_exists( 'is_checkout' ) || ( ! is_checkout() && ! is_cart() ) ) {
			return;
		}
		wp_enqueue_style( 'hm-mahex-checkout', plugins_url( 'assets/frontend/checkout.css', HM_MAHEX_FILE ), array(), HM_MAHEX_VERSION );
	}

	/**
	 * Adds only trusted local markup. The same server-rendered label is consumed by
	 * classic checkout and by WooCommerce's Store API/Blocks shipping renderer.
	 */
	public static function label( string $label, $method ): string {
		if ( ! is_object( $method ) || ! method_exists( $method, 'get_method_id' ) || 'hm_mahex' !== $method->get_method_id() ) {
			return $label;
		}

		$meta = method_exists( $method, 'get_meta_data' ) ? $method->get_meta_data() : array();
		$label = str_replace( array( ' (آزمایشی)', ' آزمایشی' ), '', $label );
		$source = (string) ( $meta['hm_mahex_source'] ?? 'manual' );
		$rows = array(
			__( 'حمل', 'mahex-shipping-for-woocommerce' ) => (float) ( $meta['hm_mahex_freight'] ?? 0 ),
			__( 'بسته‌بندی', 'mahex-shipping-for-woocommerce' ) => (float) ( $meta['hm_mahex_packaging'] ?? 0 ),
			__( 'بیمه', 'mahex-shipping-for-woocommerce' ) => (float) ( $meta['hm_mahex_insurance'] ?? 0 ),
		);
		if (method_exists($method, 'get_cost')) {
			$rows[__('خدمات / تعدیل', 'mahex-shipping-for-woocommerce')] = (float) $method->get_cost() - array_sum($rows);
		}
		$details = '';
		foreach ( $rows as $name => $amount ) {
			$details .= sprintf( '<span class="hm-mahex-rate__part"><span>%s</span><b>%s</b></span>', esc_html( $name ), wp_kses_post( wc_price( $amount ) ) );
		}
		$eta = sanitize_text_field( (string) ( $meta['hm_mahex_eta'] ?? '' ) );
		$service = sanitize_text_field( (string) ( $meta['hm_mahex_service_name'] ?? '' ) );
		if ( '' !== $eta ) $details .= '<span class="hm-mahex-rate__part hm-mahex-rate__eta"><span>' . esc_html__( 'زمان تحویل', 'mahex-shipping-for-woocommerce' ) . '</span><b>' . esc_html( $eta ) . '</b></span>';
		if ( '' !== $service ) $details .= '<span class="hm-mahex-rate__part"><span>' . esc_html__( 'سرویس', 'mahex-shipping-for-woocommerce' ) . '</span><b>' . esc_html( $service ) . '</b></span>';

		$logo = plugins_url( self::logo_asset(), HM_MAHEX_FILE );
		return sprintf(
			'<span class="hm-mahex-rate" dir="rtl"><span class="hm-mahex-rate__header"><img class="hm-mahex-rate__logo" src="%s" alt="%s" width="112" height="67" loading="lazy"><span class="hm-mahex-rate__badge hm-mahex-rate__badge--%s">%s</span></span><span class="hm-mahex-rate__title">%s</span><span class="hm-mahex-rate__breakdown" aria-label="%s">%s</span></span>',
			esc_url( $logo ),
			esc_attr__( 'ماهکس', 'mahex-shipping-for-woocommerce' ),
			esc_attr( 'official' === $source ? 'official' : 'store' ),
			esc_html( self::source_label( $source ) ),
			wp_kses_post( $label ),
			esc_attr__( 'جزئیات هزینه ارسال', 'mahex-shipping-for-woocommerce' ),
			$details
		);
	}

	public static function logo_asset(): string {
		return 'assets/brand/mahex-reference.png';
	}

	public static function source_label( string $source ): string {
		return 'official' === $source
			? __( 'نرخ رسمی ماهکس', 'mahex-shipping-for-woocommerce' )
			: __( 'نرخ فروشگاه', 'mahex-shipping-for-woocommerce' );
	}
}
