<?php
/**
 * Plugin Name: Mahex Shipping for WooCommerce
 * Description: سامانه محلی و حرفه‌ای مدیریت ارسال ووکامرس با نرخ‌گذاری، بسته‌بندی، انبار، بارنامه، عملیات، گزارش و رهگیری داخلی.
 * Version: 3.6.0
 * Author: Hosein Momeni
 * Author URI: https://postyekrooz.ir/plugins
 * Text Domain: mahex-shipping-for-woocommerce
 * Requires at least: 7.1
 * Requires PHP: 8.1
 * WC requires at least: 11.0
 * License: GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

define( 'HM_MAHEX_VERSION', '3.6.0' );
define( 'HM_MAHEX_FILE', __FILE__ );

if ( is_readable( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
} else {
	spl_autoload_register(
		static function ( string $class ): void {
			$prefix = 'HoseinMomeni\\MahexWoo\\';
			if ( 0 !== strncmp( $class, $prefix, strlen( $prefix ) ) ) {
				return;
			}
			$relative = substr( $class, strlen( $prefix ) );
			$file     = __DIR__ . '/src/' . str_replace( '\\', '/', $relative ) . '.php';
			if ( is_readable( $file ) ) {
				require_once $file;
			}
		}
	);
}

\HoseinMomeni\MahexWoo\Update\GitHubUpdater::register();
\HoseinMomeni\MahexWoo\Update\UpdateSafety::register();
\HoseinMomeni\MahexWoo\Checkout\Compatibility::register();
register_activation_hook( __FILE__, array( \HoseinMomeni\MahexWoo\V3\Bootstrap::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \HoseinMomeni\MahexWoo\V3\Bootstrap::class, 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function (): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', static function (): void {
				echo '<div class="notice notice-error"><p>' . esc_html__( 'افزونه حمل ماهکس برای اجرا به ووکامرس نیاز دارد.', 'mahex-shipping-for-woocommerce' ) . '</p></div>';
			} );
			return;
		}
		require_once __DIR__ . '/src/Shipping/MahexShippingMethod.php';
		require_once __DIR__ . '/src/Admin/ProductFields.php';
		require_once __DIR__ . '/src/Admin/Dashboard.php';

		\HoseinMomeni\MahexWoo\Admin\ProductFields::register();
		\HoseinMomeni\MahexWoo\Admin\Dashboard::register();
		\HoseinMomeni\MahexWoo\Admin\ProCenter::register();
		\HoseinMomeni\MahexWoo\Admin\PageStyles::register();
		\HoseinMomeni\MahexWoo\Admin\PackagingProfiles::register();
		\HoseinMomeni\MahexWoo\Rates\Admin\PricingSettingsAdmin::register();
		\HoseinMomeni\MahexWoo\Rates\Admin\RateRulesAdmin::register();
		\HoseinMomeni\MahexWoo\Shipping\ShippingMethodRegistry::register();
		\HoseinMomeni\MahexWoo\Orders\Bootstrap::register();
		\HoseinMomeni\MahexWoo\Shipments\Automation::register();
		\HoseinMomeni\MahexWoo\Inventory\PackagingInventory::register();
		\HoseinMomeni\MahexWoo\Documents\OrderDocumentController::register();
		\HoseinMomeni\MahexWoo\Documents\PublicTracking::register();
		\HoseinMomeni\MahexWoo\Bulk\Bootstrap::register();
		\HoseinMomeni\MahexWoo\Enterprise\Bootstrap::register();
		\HoseinMomeni\MahexWoo\V1\Bootstrap::register();
		\HoseinMomeni\MahexWoo\V2\Bootstrap::register();
		\HoseinMomeni\MahexWoo\V25\Bootstrap::register();
		\HoseinMomeni\MahexWoo\V3\Bootstrap::register();
		\HoseinMomeni\MahexWoo\V31\Operations::register();
		\HoseinMomeni\MahexWoo\V31\Finance::register();
		\HoseinMomeni\MahexWoo\V31\FinanceRates::register();
		\HoseinMomeni\MahexWoo\V32\Workspace::register();
		\HoseinMomeni\MahexWoo\V32\Rules::register();
		\HoseinMomeni\MahexWoo\V32\Board::register();
		\HoseinMomeni\MahexWoo\V32\Designer::register();
		\HoseinMomeni\MahexWoo\V32\Packing::register();
		\HoseinMomeni\MahexWoo\V33\Access::register();
		\HoseinMomeni\MahexWoo\V33\RuleVersions::register();
		\HoseinMomeni\MahexWoo\V33\Management::register();
		\HoseinMomeni\MahexWoo\V33\Station::register();
		\HoseinMomeni\MahexWoo\V33\BoardTools::register();
		\HoseinMomeni\MahexWoo\V33\PrintCenter::register();
		\HoseinMomeni\MahexWoo\V33\Returns::register();
		\HoseinMomeni\MahexWoo\V33\NotificationCenter::register();
		\HoseinMomeni\MahexWoo\V33\UpgradeSandbox::register();
		\HoseinMomeni\MahexWoo\V34\DispatchWorkbench::register();
		\HoseinMomeni\MahexWoo\V34\ServicePolicies::register();
		\HoseinMomeni\MahexWoo\V35\LocalHandover::register();
		\HoseinMomeni\MahexWoo\V35\OrderQuality::register();
		\HoseinMomeni\MahexWoo\V36\OfflineDispatchWaves::register();
		\HoseinMomeni\MahexWoo\V36\QualitySampling::register();

		( new \HoseinMomeni\MahexWoo\Privacy\WordPressPrivacyIntegration( new \HoseinMomeni\MahexWoo\Privacy\WooCommercePersonalDataRepository() ) )->register();
	}
);
