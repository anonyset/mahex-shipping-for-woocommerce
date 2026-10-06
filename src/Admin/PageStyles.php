<?php

namespace HoseinMomeni\MahexWoo\Admin;

defined( 'ABSPATH' ) || exit;

/** Adds focused layout refinements to the rate and destination pages. */
final class PageStyles {
	public static function register(): void {
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_assets' ) );
	}

	public static function enqueue_assets( string $hook ): void {
		if ( false === strpos( $hook, 'hm-mahex' ) ) {
			return;
		}

		$asset_path = dirname( HM_MAHEX_FILE ) . '/assets/admin/rates-locations.css';
		$version    = is_readable( $asset_path ) ? (string) filemtime( $asset_path ) : HM_MAHEX_VERSION;

		wp_enqueue_style(
			'hm-mahex-pages',
			plugins_url( 'assets/admin/rates-locations.css', HM_MAHEX_FILE ),
			array( 'hm-mahex-admin' ),
			$version
		);
	}
}
