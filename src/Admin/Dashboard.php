<?php

namespace HoseinMomeni\MahexWoo\Admin;

defined( 'ABSPATH' ) || exit;

final class Dashboard {
	private const PAGES = array(
		'hm-mahex'           => 'داشبورد',
		'hm-mahex-settings'  => 'فرستنده و مبدا',
		'hm-mahex-rate'      => 'محاسبه قیمت',
		'hm-mahex-locations' => 'پوشش مقصدها',
		'hm-mahex-shipments' => 'سفارش‌ها و مرسوله‌ها',
		'hm-mahex-waybill'   => 'پیش‌نمایش بارنامه',
		'hm-mahex-reports'   => 'گزارش‌ها',
		'hm-mahex-health'    => 'سلامت سیستم',
	);

	public static function register(): void {
		add_action( 'admin_menu', array( self::class, 'add_menu' ) );
		add_action( 'admin_init', array( self::class, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_assets' ) );
		add_action( 'admin_post_hm_mahex_export_shipments', array( self::class, 'export_shipments' ) );
	}

	public static function add_menu(): void {
		add_menu_page( 'ماهکس', 'ماهکس', \HoseinMomeni\MahexWoo\Enterprise\Roles::MANAGE, 'hm-mahex', array( self::class, 'render' ), 'dashicons-location-alt', 56 );
		foreach ( self::PAGES as $slug => $title ) {
			add_submenu_page( 'hm-mahex', $title . ' — ماهکس', $title, self::capability_for( $slug ), $slug, array( self::class, 'render' ) );
		}
	}

	public static function register_settings(): void {
		register_setting( 'hm_mahex_settings', 'hm_mahex_settings', array( 'type' => 'array', 'default' => array(), 'sanitize_callback' => array( self::class, 'sanitize_settings' ) ) );
	}

	public static function sanitize_settings( $input ): array {
		$input = is_array( $input ) ? $input : array();
		return array(
			'engine'         => 'local-v1',
			'sender_name'    => sanitize_text_field( $input['sender_name'] ?? '' ),
			'sender_phone'   => preg_replace( '/[^0-9+]/', '', (string) ( $input['sender_phone'] ?? '' ) ),
			'origin_state'   => sanitize_text_field( $input['origin_state'] ?? '' ),
			'origin_city'    => sanitize_text_field( $input['origin_city'] ?? '' ),
			'origin_address' => sanitize_textarea_field( $input['origin_address'] ?? '' ),
			'postal_code'    => preg_replace( '/\D/', '', (string) ( $input['postal_code'] ?? '' ) ),
		);
	}

	public static function enqueue_assets( string $hook ): void {
		if ( false !== strpos( $hook, 'hm-mahex' ) ) {
			$asset_path    = dirname( HM_MAHEX_FILE ) . '/assets/admin/dashboard.css';
			$asset_version = is_readable( $asset_path ) ? (string) filemtime( $asset_path ) : HM_MAHEX_VERSION;
			wp_enqueue_style( 'hm-mahex-admin', self::asset_url( 'assets/admin/dashboard.css' ), array(), $asset_version );
			if ( false !== strpos( $hook, 'hm-mahex-locations' ) ) {
				$locations_path = dirname( HM_MAHEX_FILE ) . '/assets/admin/locations.css';
				wp_enqueue_style( 'hm-mahex-locations', self::asset_url( 'assets/admin/locations.css' ), array( 'hm-mahex-admin' ), is_readable( $locations_path ) ? (string) filemtime( $locations_path ) : HM_MAHEX_VERSION );
			}
		}
	}

	private static function capability_for( string $slug ): string {
		return match ( $slug ) {
			'hm-mahex-settings', 'hm-mahex-locations' => \HoseinMomeni\MahexWoo\Enterprise\Roles::SETTINGS,
			'hm-mahex-reports', 'hm-mahex-health' => \HoseinMomeni\MahexWoo\Enterprise\Roles::REPORT,
			default => \HoseinMomeni\MahexWoo\Enterprise\Roles::MANAGE,
		};
	}

	private static function asset_url( string $relative ): string {
		return plugins_url( ltrim( $relative, '/' ), HM_MAHEX_FILE );
	}

	public static function render(): void {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : 'hm-mahex'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! current_user_can( self::capability_for( $page ) ) && ! current_user_can( 'manage_woocommerce' ) ) { wp_die( esc_html__( 'شما اجازه دسترسی به این صفحه را ندارید.', 'mahex-shipping-for-woocommerce' ) ); }
		?>
		<div class="wrap hm-mahex" dir="rtl">
			<?php self::header( $page ); ?>
			<?php
			switch ( $page ) {
				case 'hm-mahex-settings': self::settings(); break;
				case 'hm-mahex-rate': self::rate(); break;
				case 'hm-mahex-locations': self::locations(); break;
				case 'hm-mahex-shipments': self::shipments(); break;
				case 'hm-mahex-waybill': self::waybill(); break;
				case 'hm-mahex-reports': self::reports(); break;
				case 'hm-mahex-health': self::health(); break;
				default: self::overview();
			}
			?>
		</div>
		<?php
	}

	private static function header( string $page ): void {
		$pricing = get_option( 'hm_mahex_pricing_settings', array() );
		$status = 'موتور محلی نسخه 1 فعال';
		?>
		<header class="hmx-header"><img src="<?php echo esc_url( self::asset_url( 'assets/brand/mahex-reference.png' ) ); ?>" alt="Mahex"><div><h1><?php echo esc_html( self::PAGES[ $page ] ?? 'داشبورد' ); ?></h1><p>افزونه حمل‌ونقل ماهکس برای ووکامرس</p></div><span class="hmx-pill"><?php echo esc_html( $status ); ?></span></header>
		<nav class="hmx-nav" aria-label="بخش‌های افزونه"><?php foreach ( self::PAGES as $slug => $label ) : ?><a class="<?php echo $page === $slug ? 'is-active' : ''; ?>" <?php echo $page === $slug ? 'aria-current="page"' : ''; ?> href="<?php echo esc_url( admin_url( 'admin.php?page=' . $slug ) ); ?>"><?php echo esc_html( $label ); ?></a><?php endforeach; ?></nav>
		<?php
	}

	private static function overview(): void {
		$product_count = wp_count_posts( 'product' )->publish ?? 0;
		$order_count   = self::order_count();
		?>
		<section class="hmx-reference-hero">
			<div class="hmx-reference-brand"><img src="<?php echo esc_url( self::asset_url( 'assets/brand/mahex-reference.png' ) ); ?>" alt="Mahex"><div><span>از فروشگاه شما تا سراسر ایران</span><h2><em>افزونه حرفه‌ای</em> ماهکس برای ووکامرس</h2><p>اتصال یکپارچه فروشگاه، محاسبه هزینه و مدیریت چرخه ارسال</p></div></div>
			<div class="hmx-feature-badge"><strong>۱۰</strong><span>ماژول مدیریتی</span><small>برای فروشگاه‌های آنلاین</small></div>
			<div class="hmx-reference-kpis"><span><b><?php echo esc_html( number_format_i18n( $product_count ) ); ?></b> محصول</span><span><b><?php echo esc_html( number_format_i18n( $order_count ) ); ?></b> سفارش</span><span><b>HPOS</b> سازگار</span><span><b>RTL</b> فارسی</span></div>
		</section>

		<div class="hmx-demo-grid">
			<a class="hmx-demo-card" href="<?php echo esc_url( admin_url( 'admin.php?page=hm-mahex-settings' ) ); ?>"><span class="hmx-card-no">۱</span><h3>اتصال و نمایش در تسویه‌حساب</h3><p class="hmx-subtitle">انتخاب سرویس ارسال برای مشتری</p><div class="hmx-shipping-option"><i></i><span><b>پست پیشتاز</b><small>نرخ تنظیم‌شده فروشگاه</small></span><span>📦</span></div><div class="hmx-shipping-option is-selected"><i></i><span><b>ماهکس</b><small>ارسال سریع و مطمئن</small></span><img src="<?php echo esc_url( self::asset_url( 'assets/brand/mahex-reference.png' ) ); ?>" alt=""></div><div class="hmx-shipping-option"><i></i><span><b>تیپاکس</b><small>پرداخت در مقصد</small></span><span>🚚</span></div><span class="hmx-solid-button">تکمیل سفارش</span></a>

			<a class="hmx-demo-card" href="<?php echo esc_url( admin_url( 'edit.php?post_type=product' ) ); ?>"><span class="hmx-card-no">۲</span><h3>تنظیمات بسته‌بندی محصول</h3><p class="hmx-subtitle">کنترل وزن، ابعاد و خدمات</p><label class="hmx-mini-check"><span>نیاز به بسته‌بندی</span><i>✓</i></label><div class="hmx-mini-select">کارتن معمولی <span>⌄</span></div><div class="hmx-dimensions"><span><b>طول</b>۳۰</span><span><b>عرض</b>۲۰</span><span><b>ارتفاع</b>۱۰</span></div><label class="hmx-mini-check"><span>بیمه کالا</span><i></i></label><label class="hmx-mini-check"><span>پرداخت در محل (COD)</span><i>✓</i></label><label class="hmx-mini-check"><span>کالای شکستنی</span><i></i></label></a>

			<a class="hmx-demo-card" href="<?php echo esc_url( admin_url( 'admin.php?page=hm-mahex-rate' ) ); ?>"><span class="hmx-card-no">۳</span><h3>محاسبه نرخ فروشگاه</h3><p class="hmx-subtitle">محاسبه محلی با وزن واقعی و حجمی</p><div class="hmx-mini-select">مبدا: تهران <span>⌄</span></div><div class="hmx-mini-select">مقصد: اصفهان <span>⌄</span></div><div class="hmx-dimensions"><span><b>وزن</b>۲ کیلو</span><span><b>بسته</b>معمولی</span></div><span class="hmx-blue-button">محاسبه قیمت 🔍</span><dl class="hmx-cost-list"><dt>کرایه پایه</dt><dd>طبق تنظیمات</dd><dt>وزن حجمی</dt><dd>فعال</dd><dt>قوانین مقصد</dt><dd>قابل تنظیم</dd></dl></a>

			<a class="hmx-demo-card" href="<?php echo esc_url( admin_url( 'admin.php?page=hm-mahex-locations' ) ); ?>"><span class="hmx-card-no">۴</span><h3>مدیریت مقصد و پوشش‌دهی</h3><p class="hmx-subtitle">انتخاب استان، شهر و روستا</p><div class="hmx-map"><span>●</span><span>●</span><span>●</span><span>●</span><b>نقشه پوشش ایران</b></div><div class="hmx-coverage-row"><span>تهران</span><span>تهران</span><i class="is-ok">فعال</i></div><div class="hmx-coverage-row"><span>اصفهان</span><span>کاشان</span><i class="is-ok">فعال</i></div><div class="hmx-coverage-row"><span>گیلان</span><span>ماسال</span><i class="is-warn">محدود</i></div></a>

			<a class="hmx-demo-card" href="<?php echo esc_url( admin_url( 'admin.php?page=hm-mahex-shipments' ) ); ?>"><span class="hmx-card-no">۵</span><h3>مدیریت سفارش و بارنامه</h3><p class="hmx-subtitle">عملیات سریع روی مرسوله</p><div class="hmx-order"><strong>سفارش #۱۲۴۸</strong><span class="hmx-state hmx-state--ok">آماده ارسال</span><dl><dt>مشتری</dt><dd>آقای محمدی</dd><dt>مقصد</dt><dd>اصفهان</dd><dt>مبلغ</dt><dd>۲٬۴۰۰٬۰۰۰</dd><dt>وزن</dt><dd>۲ کیلوگرم</dd></dl></div><span class="hmx-blue-button">ساخت مرسوله</span><span class="hmx-outline-button">محاسبه مجدد کرایه</span><span class="hmx-outline-button">چاپ بارنامه</span></a>

			<a class="hmx-demo-card hmx-paper-card" href="<?php echo esc_url( admin_url( 'admin.php?page=hm-mahex-waybill' ) ); ?>"><span class="hmx-card-no">۶</span><h3>پیش‌نمایش بارنامه و تگ</h3><p class="hmx-subtitle">قالب حرفه‌ای سند و تگ</p><div class="hmx-mini-paper"><header><img src="<?php echo esc_url( self::asset_url( 'assets/brand/mahex-reference.png' ) ); ?>" alt=""><b>||| |||| |||</b></header><div><span>مبدا: تهران</span><span>مقصد: قم</span></div><p>فرستنده: فروشگاه</p><p>گیرنده: مشتری</p><table><tr><th>نوع</th><th>تعداد</th><th>وزن</th></tr><tr><td>بسته</td><td>۱</td><td>۲ کیلو</td></tr></table><footer>MHX-1000-2026 <b>▦</b></footer></div></a>

			<a class="hmx-demo-card" href="<?php echo esc_url( admin_url( 'admin.php?page=hm-mahex-waybill' ) ); ?>"><span class="hmx-card-no">۷</span><h3>تولید بارکد و لیبل</h3><p class="hmx-subtitle">بارکد داخلی یکتا و برچسب چاپی</p><div class="hmx-mini-select">نوع بارکد: سریال داخلی <span>⌄</span></div><div class="hmx-serial">MHX- <strong>1000</strong></div><label class="hmx-mini-check"><span>تولید خودکار</span><i>✓</i></label><label class="hmx-mini-check"><span>چاپ QR Code</span><i>✓</i></label><div class="hmx-label-preview"><img src="<?php echo esc_url( self::asset_url( 'assets/brand/mahex-reference.png' ) ); ?>" alt=""><strong>MHX-1000</strong><b>|||||||||||||</b></div></a>

			<a class="hmx-demo-card" href="<?php echo esc_url( admin_url( 'admin.php?page=hm-mahex-shipments' ) ); ?>"><span class="hmx-card-no">۸</span><h3>عملیات گروهی و اتوماسیون</h3><p class="hmx-subtitle">ثبت، چاپ و به‌روزرسانی صف‌بندی‌شده</p><label class="hmx-mini-check"><span>ثبت گروهی بارنامه</span><i>✓</i></label><label class="hmx-mini-check"><span>چاپ گروهی فاکتور</span><i>✓</i></label><label class="hmx-mini-check"><span>به‌روزرسانی صف‌بندی‌شده</span><i>✓</i></label><span class="hmx-action-row">انتخاب سفارش‌ها <b>☷</b></span><span class="hmx-action-row">ثبت و چاپ گروهی <b>▣</b></span><span class="hmx-action-row">دانلود فایل CSV <b>⇩</b></span></a>

			<a class="hmx-demo-card" href="<?php echo esc_url( admin_url( 'admin.php?page=hm-mahex-shipments' ) ); ?>"><span class="hmx-card-no">۹</span><h3>رهگیری مرسوله</h3><p class="hmx-subtitle">نمایش Timeline وضعیت ثبت‌شده مرسوله</p><ol class="hmx-timeline"><li><b>بارنامه ایجاد شد</b><small>امروز، ۱۴:۳۲</small></li><li><b>تحویل به نماینده</b><small>امروز، ۱۶:۱۰</small></li><li><b>ارسال به مقصد</b><small>فردا، ۰۸:۲۵</small></li><li class="is-pending"><b>تحویل به گیرنده</b><small>در انتظار</small></li></ol><div class="hmx-track-search">10118733501234 <b>جستجو</b></div></a>

			<a class="hmx-demo-card" href="<?php echo esc_url( admin_url( 'admin.php?page=hm-mahex-reports' ) ); ?>"><span class="hmx-card-no">۱۰</span><h3>گزارش‌ها و تسویه حساب‌ها</h3><p class="hmx-subtitle">شاخص‌های مالی و عملیاتی</p><div class="hmx-stat-grid"><span><b><?php echo esc_html( number_format_i18n( $order_count ) ); ?></b> مرسوله</span><span><b>واقعی</b> هزینه ارسال</span><span><b>CSV</b> خروجی مرسوله</span><span><b>۱۰۰</b> سفارش اخیر</span></div><div class="hmx-mini-chart"><i style="--h:65%"></i><i style="--h:88%"></i><i style="--h:52%"></i><i style="--h:76%"></i><i style="--h:42%"></i><i style="--h:69%"></i></div></a>
		</div>

		<section class="hmx-feature-matrix"><header><strong>۱۰ ماژول مدیریتی</strong><span>ده گروه اصلی برای مدیریت نرخ، بسته‌بندی، سفارش، اسناد و عملیات محلی</span></header><div><?php foreach ( array( 'تسویه‌حساب و سبد خرید', 'محصول و بسته‌بندی', 'موتور قیمت‌گذاری', 'پوشش‌دهی مقصدها', 'سفارش‌ها و بارنامه‌ها', 'رهگیری و ارتباط مشتری', 'فاکتور و بارکد', 'اتوماسیون و عملیات گروهی', 'مالی و گزارش‌ها', 'امنیت و سازگاری' ) as $index => $title ) : ?><article><span><?php echo esc_html( $index + 1 ); ?></span><h4><?php echo esc_html( $title ); ?></h4><ul><li>رابط کاملاً فارسی و RTL</li><li>هماهنگ با ووکامرس و HPOS</li><li>اعتبارسنجی و ثبت امن اطلاعات</li><li>مستقل از سرویس خارجی</li></ul></article><?php endforeach; ?></div></section>
		<?php
	}

	private static function settings(): void {
		$settings = get_option( 'hm_mahex_settings', array() );
		?>
		<div class="hmx-layout"><section class="hmx-panel"><h2>مشخصات فرستنده</h2><form method="post" action="options.php"><?php settings_fields( 'hm_mahex_settings' ); ?><div class="hmx-form-grid">
		<?php self::field( 'sender_name', 'نام فرستنده/فروشگاه', $settings, 'نام فروشگاه' ); self::field( 'sender_phone', 'شماره تماس', $settings, '09120000000' ); self::field( 'origin_state', 'استان مبدا', $settings, 'تهران' ); self::field( 'origin_city', 'شهر مبدا', $settings, 'تهران' ); self::field( 'postal_code', 'کدپستی', $settings, '1000000000' ); ?>
		<label class="hmx-field hmx-field--wide"><span>نشانی مبدا</span><textarea name="hm_mahex_settings[origin_address]" rows="3"><?php echo esc_textarea( $settings['origin_address'] ?? '' ); ?></textarea></label></div><?php submit_button( 'ذخیره تنظیمات' ); ?></form></section>
		<aside class="hmx-panel"><h2>وضعیت اتصال</h2><div class="hmx-status"><i></i><strong>موتور محلی نسخه 1 فعال</strong></div><p>نرخ، بسته‌بندی، بارنامه و رهگیری از داده‌های داخلی فروشگاه مدیریت می‌شوند و هیچ اتصال بیرونی برای نسخه 1 لازم نیست.</p><ul class="hmx-checks"><li>ذخیره امن تنظیمات</li><li>عدم ثبت secret در log</li><li>بدون وابستگی به سرویس خارجی</li></ul></aside></div>
		<?php
	}

	private static function field( string $name, string $label, array $settings, string $placeholder ): void {
		printf( '<label class="hmx-field"><span>%s</span><input type="text" name="hm_mahex_settings[%s]" value="%s" placeholder="%s"></label>', esc_html( $label ), esc_attr( $name ), esc_attr( $settings[ $name ] ?? '' ), esc_attr( $placeholder ) );
	}

	private static function rate(): void {
		$result = null;
		if ( isset( $_POST['hmx_rate_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['hmx_rate_nonce'] ) ), 'hmx_rate' ) ) {
			$number = static function ( string $key, float $default ): float {
				$raw = isset( $_POST[ $key ] ) && is_scalar( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : $default;
				$value = (float) wc_format_decimal( $raw );
				return is_finite( $value ) ? max( 0.0, $value ) : $default;
			};
			$weight = max( 0.001, $number( 'weight', 1 ) );
			$length = max( 1, $number( 'length', 10 ) );
			$width  = max( 1, $number( 'width', 10 ) );
			$height = max( 1, $number( 'height', 10 ) );
			$chargeable = max( $weight, ( $length * $width * $height ) / 5000 );
			$destination = isset( $_POST['destination'] ) ? sanitize_text_field( wp_unslash( $_POST['destination'] ) ) : '';
			$province = \HoseinMomeni\MahexWoo\Shipping\PricingSettings::province_name( $destination );
			$base = \HoseinMomeni\MahexWoo\Shipping\PricingSettings::money( 'manual_base_cost', 850000 );
			$perKg = \HoseinMomeni\MahexWoo\Shipping\PricingSettings::money( 'manual_cost_per_kg', 75000 );
			$freight = $base + ceil( max( 1, $chargeable ) ) * $perKg;
			$freight = \HoseinMomeni\MahexWoo\Shipping\PricingSettings::province_rate( $destination, $freight );
			$rule_id = '';
			try {
				$manager = new \HoseinMomeni\MahexWoo\Rates\Admin\RateRuleManager( new \HoseinMomeni\MahexWoo\Rates\Admin\WordPressOptionRateRuleRepository() );
				foreach ( $manager->active() as $record ) {
					if ( 'IRR' !== $record->currency ) continue;
					$rule = $record->toDomainRule();
					if ( ! $rule->matches( $province, '' ) ) continue;
					$freight = \HoseinMomeni\MahexWoo\Rates\RateRule::FIXED === $rule->mode ? $record->amount : $freight + $record->amount;
					$rule_id = $record->id;
					break;
				}
			} catch ( \Throwable $error ) {}
			$packing = isset( $_POST['packing'] ) ? \HoseinMomeni\MahexWoo\Shipping\PricingSettings::money( 'manual_packaging_cost', 0 ) : 0;
			$insurance = isset( $_POST['insurance'] ) ? \HoseinMomeni\MahexWoo\Shipping\PricingSettings::money( 'manual_insurance_cost', 0 ) : 0;
			$result = array(
				'chargeable' => $chargeable,
				'freight' => \HoseinMomeni\MahexWoo\Shipping\PricingSettings::to_store_currency( $freight ),
				'packing' => \HoseinMomeni\MahexWoo\Shipping\PricingSettings::to_store_currency( $packing ),
				'insurance' => \HoseinMomeni\MahexWoo\Shipping\PricingSettings::to_store_currency( $insurance ),
				'rule' => $rule_id,
			);
		}
		?>
		<div class="hmx-layout"><section class="hmx-panel"><h2>محاسبه قیمت فروشگاه</h2><form method="post"><?php wp_nonce_field( 'hmx_rate', 'hmx_rate_nonce' ); ?><div class="hmx-form-grid"><?php foreach ( array( 'origin' => array( 'مبدا', 'تهران' ), 'destination' => array( 'استان مقصد', 'قم' ), 'weight' => array( 'وزن واقعی (کیلوگرم)', '2' ), 'length' => array( 'طول (cm)', '30' ), 'width' => array( 'عرض (cm)', '20' ), 'height' => array( 'ارتفاع (cm)', '10' ) ) as $name => $data ) : ?><label class="hmx-field"><span><?php echo esc_html( $data[0] ); ?></span><input name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( isset( $_POST[ $name ] ) && is_scalar( $_POST[ $name ] ) ? wp_unslash( $_POST[ $name ] ) : $data[1] ); ?>"></label><?php endforeach; ?><label class="hmx-toggle"><input type="checkbox" name="packing" <?php checked( isset( $_POST['packing'] ) ); ?>> بسته‌بندی</label><label class="hmx-toggle"><input type="checkbox" name="insurance" <?php checked( isset( $_POST['insurance'] ) ); ?>> بیمه</label></div><button class="button button-primary button-hero">محاسبه</button></form></section>
		<aside class="hmx-panel"><h2>نتیجه محاسبه</h2><?php if ( $result ) : $total = $result['freight'] + $result['packing'] + $result['insurance']; ?><dl class="hmx-quote"><dt>وزن قابل پرداخت</dt><dd><?php echo esc_html( number_format_i18n( $result['chargeable'], 2 ) ); ?> کیلوگرم</dd><dt>حمل‌ونقل</dt><dd><?php echo wp_kses_post( wc_price( $result['freight'] ) ); ?></dd><dt>بسته‌بندی</dt><dd><?php echo wp_kses_post( wc_price( $result['packing'] ) ); ?></dd><dt>بیمه</dt><dd><?php echo wp_kses_post( wc_price( $result['insurance'] ) ); ?></dd><?php if ( '' !== $result['rule'] ) : ?><dt>قانون قیمت</dt><dd><?php echo esc_html( $result['rule'] ); ?></dd><?php endif; ?><dt class="hmx-total">قابل پرداخت</dt><dd class="hmx-total"><?php echo wp_kses_post( wc_price( $total ) ); ?></dd></dl><?php else : ?><div class="hmx-empty">مشخصات مرسوله را وارد کنید.</div><?php endif; ?></aside></div>
		<?php
	}

	private static function locations(): void {
		$result = ( new \HoseinMomeni\MahexWoo\Locations\IranFallbackLocationProvider() )->fetch();
		?><section class="hmx-panel"><div class="hmx-panel-head"><div><h2>مقصدها و داده‌های نشانی</h2><p>فهرست داخلی استان/شهر برای ورود نشانی، قوانین نرخ و اعتبارسنجی محلی استفاده می‌شود.</p></div></div><p><strong>منبع:</strong> داده داخلی افزونه · <strong>تعداد:</strong> <?php echo esc_html( (string) count( $result->locations ) ); ?></p><table class="widefat striped hmx-table"><thead><tr><th>استان</th><th>شهر</th><th>روستا</th><th>نوع داده</th></tr></thead><tbody><?php foreach ( $result->locations as $location ) : ?><tr><td><?php echo esc_html( $location->province ); ?></td><td><?php echo esc_html( $location->city ?: '—' ); ?></td><td><?php echo esc_html( $location->village ?? '—' ); ?></td><td><?php echo esc_html( $location->typeLabel() ); ?></td></tr><?php endforeach; ?></tbody></table></section><?php
	}

	private static function shipments(): void {
		$orders = wc_get_orders( array( 'limit' => 30, 'orderby' => 'date', 'order' => 'DESC' ) );
		$status = isset( $_GET['hm_mahex_bulk_status'] ) ? sanitize_key( wp_unslash( $_GET['hm_mahex_bulk_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'queued' === $status ) {
			$queued = isset( $_GET['hm_mahex_bulk_queued'] ) ? absint( $_GET['hm_mahex_bulk_queued'] ) : 0;
			$duplicates = isset( $_GET['hm_mahex_bulk_duplicates'] ) ? absint( $_GET['hm_mahex_bulk_duplicates'] ) : 0;
			echo '<div class="notice notice-success inline"><p>' . esc_html( sprintf( 'عملیات گروهی صف‌بندی شد: %d سفارش جدید، %d تکراری.', $queued, $duplicates ) ) . '</p></div>';
		} elseif ( 'invalid' === $status ) {
			echo '<div class="notice notice-error inline"><p>عملیات گروهی معتبر نبود؛ حداقل یک سفارش و یک عملیات انتخاب کنید.</p></div>';
		}
		$export_url = wp_nonce_url( add_query_arg( 'action', 'hm_mahex_export_shipments', admin_url( 'admin-post.php' ) ), 'hm_mahex_export_shipments' );
		?>
		<section class="hmx-panel"><div class="hmx-panel-head"><div><h2>سفارش‌ها و مرسوله‌ها</h2><p>ثبت، به‌روزرسانی، لغو و صدور مجدد گروهی با صف داخلی انجام می‌شود و همه وضعیت‌ها داخل ووکامرس نگهداری می‌شوند.</p></div><a class="button" href="<?php echo esc_url( $export_url ); ?>">خروجی CSV صد سفارش اخیر</a></div>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="hm_mahex_bulk_shipments"><input type="hidden" name="request_id" value="<?php echo esc_attr( wp_generate_uuid4() ); ?>"><?php wp_nonce_field( \HoseinMomeni\MahexWoo\Bulk\BulkActionController::NONCE_ACTION, '_hm_mahex_nonce' ); ?>
		<table class="widefat striped hmx-table"><thead><tr><th><input type="checkbox" aria-label="انتخاب همه" onclick="document.querySelectorAll('.hmx-order-check').forEach(function(el){el.checked=this.checked}.bind(this))"></th><th>سفارش</th><th>مشتری</th><th>مقصد</th><th>تاریخ</th><th>مبلغ</th><th>وضعیت مرسوله</th><th>عملیات</th></tr></thead><tbody>
		<?php $store = new \HoseinMomeni\MahexWoo\Shipments\OrderShipmentStore(); foreach ( $orders as $order ) : $shipment = $store->get( $order ); ?>
		<tr><td><input class="hmx-order-check" type="checkbox" name="order_ids[]" value="<?php echo esc_attr( (string) $order->get_id() ); ?>"></td><td>#<?php echo esc_html( (string) $order->get_id() ); ?></td><td><?php echo esc_html( $order->get_formatted_billing_full_name() ?: '—' ); ?></td><td><?php echo esc_html( $order->get_shipping_city() ?: $order->get_billing_city() ?: '—' ); ?></td><td><?php echo esc_html( wc_format_datetime( $order->get_date_created() ) ); ?></td><td><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></td><td><?php if ( $shipment ) : ?><span class="hmx-state hmx-state--ok"><?php echo esc_html( $shipment->status ); ?></span><?php else : ?><span class="hmx-state hmx-state--warn">ثبت نشده</span><?php endif; ?></td><td><a class="button button-small" href="<?php echo esc_url( $order->get_edit_order_url() ); ?>">مشاهده</a></td></tr>
		<?php endforeach; ?>
		</tbody></table><p style="display:flex;gap:8px;flex-wrap:wrap"><button class="button button-primary" name="operation" value="create">ساخت مرسوله برای انتخاب‌شده‌ها</button><button class="button" name="operation" value="refresh">به‌روزرسانی وضعیت انتخاب‌شده‌ها</button><button class="button" name="operation" value="reissue" onclick="return confirm('برای سفارش‌های انتخاب‌شده مرسوله مجدد صادر شود؟')">صدور مجدد انتخاب‌شده‌ها</button><button class="button" name="operation" value="cancel" onclick="return confirm('مرسوله‌های انتخاب‌شده لغو شوند؟')">لغو انتخاب‌شده‌ها</button></p></form></section>
		<?php
	}

	public static function export_shipments(): void {
		if ( ! current_user_can( \HoseinMomeni\MahexWoo\Enterprise\Roles::REPORT ) && ! current_user_can( 'manage_woocommerce' ) ) { wp_die( esc_html__( 'دسترسی غیرمجاز است.', 'mahex-shipping-for-woocommerce' ), '', array( 'response' => 403 ) ); }
		check_admin_referer( 'hm_mahex_export_shipments' );
		$orders = wc_get_orders( array( 'limit' => 100, 'orderby' => 'date', 'order' => 'DESC' ) );
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="mahex-shipments-' . gmdate( 'Y-m-d-His' ) . '.csv"' );
		$out = fopen( 'php://output', 'wb' );
		if ( false === $out ) exit;
		fwrite( $out, "\xEF\xBB\xBF" );
		fputcsv( $out, array( 'order_id', 'date', 'recipient', 'province', 'city', 'order_total', 'currency', 'shipping_total', 'shipment_id', 'waybill', 'tracking', 'shipment_status' ) );
		$store = new \HoseinMomeni\MahexWoo\Shipments\OrderShipmentStore();
		foreach ( $orders as $order ) {
			$shipment = $store->get( $order );
			$row = array( $order->get_id(), $order->get_date_created() ? $order->get_date_created()->date( DATE_ATOM ) : '', $order->get_formatted_shipping_full_name() ?: $order->get_formatted_billing_full_name(), $order->get_shipping_state() ?: $order->get_billing_state(), $order->get_shipping_city() ?: $order->get_billing_city(), $order->get_total(), $order->get_currency(), $order->get_shipping_total(), $shipment?->shipment_id ?? '', $shipment?->waybill_number ?? '', $shipment?->tracking_code ?? '', $shipment?->status ?? '' );
			fputcsv( $out, array_map( array( self::class, 'csv_cell' ), $row ) );
		}
		fclose( $out );
		exit;
	}

	private static function csv_cell( mixed $value ): string {
		$value = (string) $value;
		return preg_match( '/^[=+\-@]/', $value ) ? "'" . $value : $value;
	}

	private static function waybill(): void {
		$orders = wc_get_orders( array( 'limit' => 1, 'orderby' => 'date', 'order' => 'DESC' ) );
		$order  = $orders[0] ?? null;
		if ( ! $order ) {
			echo '<section class="hmx-panel"><h2>پیش‌نمایش بارنامه</h2><div class="hmx-empty">برای ساخت بارنامه مستقل، ابتدا یک سفارش ایجاد کنید.</div></section>';
			return;
		}
		$url = wp_nonce_url( add_query_arg( array( 'action' => 'hm_mahex_print_waybill', 'order_id' => $order->get_id() ), admin_url( 'admin-post.php' ) ), 'hm_mahex_print_waybill:' . $order->get_id() );
		?><section class="hmx-panel"><div class="hmx-panel-head"><div><h2>بارنامه سفارش #<?php echo esc_html( (string) $order->get_id() ); ?></h2><p>بارکد ۱۰ رقمی، QR واقعی و تگ گیرنده از اطلاعات همین سفارش ساخته می‌شود.</p></div><a class="button button-primary" target="_blank" rel="noopener" href="<?php echo esc_url( $url ); ?>">بازکردن پیش‌نمایش و چاپ</a></div><iframe title="پیش‌نمایش بارنامه" src="<?php echo esc_url( $url ); ?>" style="width:100%;min-height:760px;border:1px solid #d0d5dd;border-radius:12px;background:#fff"></iframe></section><?php
	}

	private static function order_count(): int {
		$result = wc_get_orders( array(
			'limit'    => 1,
			'page'     => 1,
			'paginate' => true,
			'return'   => 'ids',
		) );

		if ( is_object( $result ) && isset( $result->total ) ) {
			return max( 0, (int) $result->total );
		}

		return is_array( $result ) ? count( $result ) : 0;
	}

	private static function reports(): void {
		$orders = wc_get_orders( array( 'limit' => 100, 'orderby' => 'date', 'order' => 'DESC' ) );
		$store = new \HoseinMomeni\MahexWoo\Shipments\OrderShipmentStore();
		$shipments = 0; $delivered = 0; $shipping_sum = 0.0; $cities = array();
		foreach ( $orders as $order ) {
			$shipping_sum += (float) $order->get_shipping_total();
			$city = trim( (string) ( $order->get_shipping_city() ?: $order->get_billing_city() ) ); if ( '' !== $city ) $cities[ $city ] = true;
			$shipment = $store->get( $order ); if ( $shipment ) { ++$shipments; if ( 'delivered' === $shipment->status ) ++$delivered; }
		}
		$metrics = array(
			array( 'محصول منتشرشده', wp_count_posts( 'product' )->publish ?? 0, 'dashicons-products' ),
			array( 'مرسوله ثبت‌شده در ۱۰۰ سفارش اخیر', $shipments, 'dashicons-location-alt' ),
			array( 'شهر مقصد در ۱۰۰ سفارش اخیر', count( $cities ), 'dashicons-location' ),
			array( 'تحویل‌شده', $delivered, 'dashicons-yes-alt' ),
		);
		?><div class="hmx-metrics"><?php foreach ( $metrics as $metric ) : ?><section><span class="dashicons <?php echo esc_attr( $metric[2] ); ?>"></span><strong><?php echo esc_html( number_format_i18n( $metric[1] ) ); ?></strong><small><?php echo esc_html( $metric[0] ); ?></small></section><?php endforeach; ?></div><section class="hmx-panel"><h2>جمع هزینه ارسال در ۱۰۰ سفارش اخیر</h2><p style="font-size:24px;font-weight:800"><?php echo wp_kses_post( wc_price( $shipping_sum ) ); ?></p><p>این گزارش از داده واقعی ووکامرس ساخته می‌شود و اعداد نمونه/ساختگی ندارد.</p></section><?php
	}

	private static function health(): void {
		$settings = get_option( 'hm_mahex_settings', array() ); $settings = is_array( $settings ) ? $settings : array();
		$pricing = \HoseinMomeni\MahexWoo\Rates\PricingSettings::normalizeStored( get_option( \HoseinMomeni\MahexWoo\Rates\PricingSettings::OPTION_NAME, array() ) );
		$hpos = 'نامشخص';
		$order_util = '\\Automattic\\WooCommerce\\Utilities\\OrderUtil';
		if ( class_exists( $order_util ) && method_exists( $order_util, 'custom_orders_table_usage_is_enabled' ) ) $hpos = $order_util::custom_orders_table_usage_is_enabled() ? 'فعال' : 'غیرفعال';
		$checks = array(
			array( version_compare( PHP_VERSION, '8.1', '>=' ), 'PHP', PHP_VERSION ),
			array( defined( 'WC_VERSION' ), 'WooCommerce', defined( 'WC_VERSION' ) ? WC_VERSION : 'لود نشده' ),
			array( in_array( get_woocommerce_currency(), array( 'IRR', 'IRT' ), true ), 'واحد پول', get_woocommerce_currency() ),
			array( ! empty( $settings['sender_name'] ) && ! empty( $settings['sender_phone'] ) && ! empty( $settings['origin_address'] ), 'مشخصات فرستنده', ! empty( $settings['sender_name'] ) ? 'ثبت شده' : 'ناقص' ),
			array( (int) $pricing['manual_base_cost'] > 0 || (int) $pricing['fallback_cost'] > 0, 'نرخ جایگزین', (int) $pricing['manual_base_cost'] > 0 ? 'تنظیم شده' : 'نیاز به بررسی' ),
			array( ! ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ), 'WP-Cron', defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ? 'غیرفعال' : 'فعال' ),
			array( function_exists( 'as_enqueue_async_action' ), 'Action Scheduler', function_exists( 'as_enqueue_async_action' ) ? 'در دسترس' : 'Fallback به WP-Cron' ),
			array( true, 'HPOS', $hpos ),
			array( 0 === count( \HoseinMomeni\MahexWoo\Inventory\PackagingInventory::lowStock() ), 'موجودی بسته‌بندی', 0 === count( \HoseinMomeni\MahexWoo\Inventory\PackagingInventory::lowStock() ) ? 'مناسب' : count( \HoseinMomeni\MahexWoo\Inventory\PackagingInventory::lowStock() ) . ' مورد کم‌موجودی' ),
		);
		?><section class="hmx-panel"><h2>سلامت و سازگاری افزونه</h2><p>این صفحه مشکلات رایج قبل از اثرگذاری روی Checkout را نشان می‌دهد.</p><table class="widefat striped hmx-table"><thead><tr><th>بخش</th><th>وضعیت</th><th>جزئیات</th></tr></thead><tbody><?php foreach ( $checks as $check ) : ?><tr><td><?php echo esc_html( $check[1] ); ?></td><td><span class="hmx-state <?php echo $check[0] ? 'hmx-state--ok' : 'hmx-state--warn'; ?>"><?php echo $check[0] ? 'مناسب' : 'نیاز به بررسی'; ?></span></td><td><?php echo esc_html( (string) $check[2] ); ?></td></tr><?php endforeach; ?></tbody></table><p><strong>حالت نسخه 1:</strong> محاسبه کرایه، بارنامه، عملیات انبار و رهگیری به‌صورت محلی داخل ووکامرس اجرا می‌شوند.</p></section><?php
	}

}
