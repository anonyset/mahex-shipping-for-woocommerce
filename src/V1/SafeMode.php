<?php

namespace HoseinMomeni\MahexWoo\V1;

final class SafeMode {
	private static bool $armed = false;

	public static function register(): void {
		if ( ! Config::bool( 'safe_mode', true ) ) return;
		if ( ! self::$armed ) {
			self::$armed = true;
			register_shutdown_function( array( self::class, 'shutdown' ) );
		}
		add_filter( 'woocommerce_shipping_methods', array( self::class, 'filterShippingMethods' ), 999 );
		add_action( 'admin_notices', array( self::class, 'notice' ) );
		add_action( 'admin_post_hm_mahex_clear_safe_mode', array( self::class, 'clear' ) );
	}

	public static function active(): bool {
		return is_array( get_option( 'hm_mahex_safe_mode_triggered', null ) );
	}

	public static function filterShippingMethods( array $methods ): array {
		if ( self::active() ) unset( $methods['hm_mahex'] );
		return $methods;
	}

	public static function shutdown(): void {
		$error = error_get_last();
		if ( ! is_array( $error ) || ! in_array( (int) ( $error['type'] ?? 0 ), array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR ), true ) ) return;
		$file = (string) ( $error['file'] ?? '' );
		$root = defined( 'HM_MAHEX_FILE' ) ? dirname( HM_MAHEX_FILE ) : '';
		if ( '' === $root || ! str_starts_with( wp_normalize_path( $file ), wp_normalize_path( $root ) ) ) return;
		update_option( 'hm_mahex_safe_mode_triggered', array(
			'reason' => 'fatal', 'message' => substr( (string) ( $error['message'] ?? 'Fatal error' ), 0, 500 ),
			'file' => basename( $file ), 'line' => (int) ( $error['line'] ?? 0 ), 'at' => gmdate( DATE_ATOM ),
		), false );
	}

	public static function notice(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! self::active() ) return;
		$data = get_option( 'hm_mahex_safe_mode_triggered', array() );
		$url = wp_nonce_url( admin_url( 'admin-post.php?action=hm_mahex_clear_safe_mode' ), 'hm_mahex_clear_safe_mode' );
		echo '<div class="notice notice-error"><p><strong>Mahex 1.0 Safe Mode:</strong> روش ارسال ماهکس موقتاً غیرفعال شده است تا Checkout از کار نیفتد. ' . esc_html( (string) ( $data['message'] ?? '' ) ) . ' <a class="button" href="' . esc_url( $url ) . '">خروج از Safe Mode</a></p></div>';
	}

	public static function clear(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) wp_die( 'Forbidden', '', array( 'response' => 403 ) );
		check_admin_referer( 'hm_mahex_clear_safe_mode' );
		delete_option( 'hm_mahex_safe_mode_triggered' );
		ActivityLog::write( 'safe_mode.cleared', 'Safe Mode توسط مدیر پاک شد.' );
		wp_safe_redirect( wp_get_referer() ?: admin_url( 'admin.php?page=hm-mahex-v1' ) );
		exit;
	}
}
