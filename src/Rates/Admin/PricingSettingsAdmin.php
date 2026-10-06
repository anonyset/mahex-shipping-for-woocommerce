<?php

namespace HoseinMomeni\MahexWoo\Rates\Admin;

use HoseinMomeni\MahexWoo\Rates\PricingSettings;

/** Admin settings UI and protected handlers; plugin bootstrap owns registration. */
final class PricingSettingsAdmin {
	private const CAPABILITY = 'manage_woocommerce';
	private const PAGE_SLUG  = 'hm-mahex-pricing';

	public static function register(): void {
		add_action( 'admin_menu', array( self::class, 'add_menu' ) );
		add_action( 'admin_post_hm_mahex_save_pricing_settings', array( self::class, 'save' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_assets' ) );
	}

	public static function add_menu(): void {
		add_submenu_page(
			'hm-mahex',
			'تنظیمات قیمت — ماهکس',
			'تنظیمات قیمت',
			self::CAPABILITY,
			self::PAGE_SLUG,
			array( self::class, 'render' )
		);
	}

	public static function enqueue_assets( string $hook ): void {
		if ( false === strpos( $hook, self::PAGE_SLUG ) ) {
			return;
		}
		$path    = dirname( HM_MAHEX_FILE ) . '/assets/admin/rates-settings.css';
		$version = is_readable( $path ) ? (string) filemtime( $path ) : HM_MAHEX_VERSION;
		wp_enqueue_style( 'hm-mahex-rates-settings', plugins_url( 'assets/admin/rates-settings.css', HM_MAHEX_FILE ), array(), $version );
	}

	public static function render(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'شما اجازه دسترسی به این صفحه را ندارید.', 'mahex-shipping-for-woocommerce' ), '', array( 'response' => 403 ) );
		}
		$settings = self::settings();
		$notice   = isset( $_GET['hm_pricing_notice'] ) ? sanitize_key( wp_unslash( $_GET['hm_pricing_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
		<div class="wrap hm-mahex hmx-pricing" dir="rtl">
			<h1>تنظیمات قیمت و وزن ماهکس</h1>
			<?php self::notice( $notice ); ?>
			<div class="notice notice-info inline"><p>نسخه 1.0.0 کاملاً محلی است؛ همه نرخ‌ها از تنظیمات فروشگاه، قوانین قیمت و موتور بسته‌بندی محاسبه می‌شوند.</p></div>

			<form class="hmx-pricing-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="hm_mahex_save_pricing_settings">
				<?php wp_nonce_field( 'hm_mahex_save_pricing_settings' ); ?>
				<section class="hmx-pricing-card">
					<h2>روش محاسبهٔ کرایه</h2>
					<div class="hmx-pricing-grid">
						<?php self::select( 'pricing_mode', 'منبع قیمت حمل', 'manual', array( 'manual' => 'محلی / دستی' ) ); ?>
						<?php self::select( 'currency_unit', 'واحد مبلغ‌های واردشده', $settings['currency_unit'], array( 'irr' => 'ریال', 'toman' => 'تومان' ) ); ?>
						<?php self::select( 'product_availability', 'نمایش روش ماهکس برای محصولات', $settings['product_availability'], array( 'all_products' => 'همه محصولات فیزیکی', 'enabled_only' => 'فقط اگر همه اقلام سبد برای ماهکس فعال شده‌اند' ) ); ?>
						<?php self::amount( 'manual_base_cost', 'کرایه پایه', $settings ); ?>
						<?php self::amount( 'manual_cost_per_kg', 'هزینهٔ هر کیلوگرم', $settings ); ?>
						<?php self::amount( 'fallback_cost', 'کرایه جایگزین هنگام خطا', $settings ); ?>
						<?php self::amount( 'free_shipping_threshold', 'ارسال رایگان از مبلغ سفارش', $settings ); ?>
					</div>
					<p class="description">همهٔ مبالغ در پایگاه داده به ریال ذخیره می‌شوند. صفر برای آستانهٔ ارسال رایگان یعنی غیرفعال.</p>
				</section>

				<section class="hmx-pricing-card">
					<h2>وزن حجمی و داده‌های ناقص محصول</h2>
					<div class="hmx-pricing-grid">
						<?php self::integer( 'volumetric_divisor', 'ضریب وزن حجمی (mm³ → g)', $settings, 1000, 50000 ); ?>
						<?php self::integer( 'fallback_weight_g', 'وزن جایگزین محصول بدون وزن (گرم)', $settings, 1, 1000000 ); ?>
						<?php self::integer( 'fallback_length_mm', 'طول جایگزین (میلی‌متر)', $settings, 1, 10000 ); ?>
						<?php self::integer( 'fallback_width_mm', 'عرض جایگزین (میلی‌متر)', $settings, 1, 10000 ); ?>
						<?php self::integer( 'fallback_height_mm', 'ارتفاع جایگزین (میلی‌متر)', $settings, 1, 10000 ); ?>
					</div>
					<p class="description">وزن قابل پرداخت از بیشترینِ وزن واقعی و حجمی ساخته می‌شود. برای فرمول رایج ابعاد سانتی‌متر ÷ ۵۰۰۰، مقدار این فیلد نیز ۵۰۰۰ است.</p>
				</section>

				<section class="hmx-pricing-card">
					<h2>نرخ دستی به تفکیک استان</h2>
					<p>نرخ هر استان با واحد انتخاب‌شده وارد می‌شود؛ صفر یعنی برای آن استان نرخ جداگانه تعریف نشده و فرمول پایه/وزن استفاده می‌شود.</p>
					<div class="hmx-province-grid">
						<?php foreach ( self::provinces() as $province ) : $canonical = (int) ( $settings['province_rates'][ $province ] ?? 0 ); ?>
							<label class="hmx-pricing-field"><span><?php echo esc_html( $province ); ?></span><input type="number" min="0" step="<?php echo 'toman' === $settings['currency_unit'] ? '0.1' : '1'; ?>" inputmode="decimal" name="hm_mahex_pricing_settings[province_rates][<?php echo esc_attr( $province ); ?>]" value="<?php echo esc_attr( PricingSettings::fromRials( $canonical, $settings['currency_unit'] ) ); ?>"></label>
						<?php endforeach; ?>
					</div>
				</section>

				<section class="hmx-pricing-card">
					<h2>بسته‌بندی و بیمه</h2>
					<div class="hmx-pricing-grid">
						<?php self::select( 'packaging_mode', 'منبع هزینهٔ بسته‌بندی', 'manual', array( 'manual' => 'محلی / پروفایل بسته‌بندی' ) ); ?>
						<?php self::select( 'packaging_application', 'اعمال هزینه بسته‌بندی', $settings['packaging_application'], array( 'always' => 'برای همه سفارش‌ها', 'required_products' => 'فقط اگر محصول نیازمند بسته‌بندی است', 'never' => 'هیچ‌وقت' ) ); ?>
						<?php self::amount( 'manual_packaging_cost', 'مبلغ دستی بسته‌بندی', $settings ); ?>
						<?php self::select( 'insurance_mode', 'منبع هزینهٔ بیمه', 'manual', array( 'manual' => 'محلی / دستی' ) ); ?>
						<?php self::select( 'insurance_application', 'اعمال هزینه بیمه', $settings['insurance_application'], array( 'always' => 'برای همه سفارش‌ها', 'required_products' => 'فقط اگر محصول بیمه اجباری دارد', 'never' => 'هیچ‌وقت' ) ); ?>
						<?php self::amount( 'manual_insurance_cost', 'مبلغ دستی بیمه', $settings ); ?>
					</div>
					<p class="description">حالت «فقط محصول لازم دارد» مستقیماً از تیک‌های صفحه محصول/تنوع استفاده می‌کند.</p>
				</section>
				<?php submit_button( 'ذخیره تنظیمات قیمت', 'primary', 'submit', true ); ?>
			</form>
		</div>
		<?php
	}

	public static function save(): void {
		self::guard( 'hm_mahex_save_pricing_settings' );
		$input = isset( $_POST['hm_mahex_pricing_settings'] ) && is_array( $_POST['hm_mahex_pricing_settings'] ) ? wp_unslash( $_POST['hm_mahex_pricing_settings'] ) : array();
		$next  = PricingSettings::sanitize( $input );
		update_option( PricingSettings::OPTION_NAME, $next, false );
		self::redirect( 'saved' );
	}

	/** @return array<string,mixed> */
	private static function settings(): array {
		return PricingSettings::normalizeStored( get_option( PricingSettings::OPTION_NAME, array() ) );
	}

	private static function select( string $name, string $label, string $value, array $choices ): void {
		?>
		<label class="hmx-pricing-field"><span><?php echo esc_html( $label ); ?></span><select name="hm_mahex_pricing_settings[<?php echo esc_attr( $name ); ?>]"><?php foreach ( $choices as $key => $caption ) : ?><option value="<?php echo esc_attr( $key ); ?>" <?php selected( $value, $key ); ?>><?php echo esc_html( $caption ); ?></option><?php endforeach; ?></select></label>
		<?php
	}

	/** @param array<string,mixed> $settings */
	private static function amount( string $name, string $label, array $settings ): void {
		?>
		<label class="hmx-pricing-field"><span><?php echo esc_html( $label ); ?> (<?php echo 'toman' === $settings['currency_unit'] ? 'تومان' : 'ریال'; ?>)</span><input type="number" min="0" step="<?php echo 'toman' === $settings['currency_unit'] ? '0.1' : '1'; ?>" inputmode="decimal" name="hm_mahex_pricing_settings[<?php echo esc_attr( $name ); ?>]" value="<?php echo esc_attr( PricingSettings::fromRials( (int) $settings[ $name ], $settings['currency_unit'] ) ); ?>"></label>
		<?php
	}

	/** @param array<string,mixed> $settings */
	private static function integer( string $name, string $label, array $settings, int $min, int $max ): void {
		?>
		<label class="hmx-pricing-field"><span><?php echo esc_html( $label ); ?></span><input type="number" min="<?php echo esc_attr( (string) $min ); ?>" max="<?php echo esc_attr( (string) $max ); ?>" step="1" inputmode="numeric" name="hm_mahex_pricing_settings[<?php echo esc_attr( $name ); ?>]" value="<?php echo esc_attr( (string) (int) $settings[ $name ] ); ?>"></label>
		<?php
	}

	/** @return list<string> */
	private static function provinces(): array {
		return array( 'آذربایجان شرقی', 'آذربایجان غربی', 'اردبیل', 'اصفهان', 'البرز', 'ایلام', 'بوشهر', 'تهران', 'چهارمحال و بختیاری', 'خراسان جنوبی', 'خراسان رضوی', 'خراسان شمالی', 'خوزستان', 'زنجان', 'سمنان', 'سیستان و بلوچستان', 'فارس', 'قزوین', 'قم', 'کردستان', 'کرمان', 'کرمانشاه', 'کهگیلویه و بویراحمد', 'گلستان', 'گیلان', 'لرستان', 'مازندران', 'مرکزی', 'هرمزگان', 'همدان', 'یزد' );
	}

	private static function guard( string $nonceAction ): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'دسترسی غیرمجاز است.', 'mahex-shipping-for-woocommerce' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( $nonceAction );
	}

	private static function redirect( string $notice ): void {
		wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE_SLUG, 'hm_pricing_notice' => sanitize_key( $notice ) ), admin_url( 'admin.php' ) ) );
		exit;
	}

	private static function notice( string $notice ): void {
		$messages = array(
			'saved'               => array( 'success', 'تنظیمات قیمت ذخیره شد.' ),
		);
		if ( isset( $messages[ $notice ] ) ) {
			printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $messages[ $notice ][0] ), esc_html( $messages[ $notice ][1] ) );
		}
	}
}
