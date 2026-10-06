<?php

namespace HoseinMomeni\MahexWoo\Admin;

use HoseinMomeni\MahexWoo\Analytics\ShippingAnalytics;
use HoseinMomeni\MahexWoo\Documents\BulkLabelRenderer;
use HoseinMomeni\MahexWoo\Documents\OrderWaybillFactory;
use HoseinMomeni\MahexWoo\Inventory\PackagingInventory;
use HoseinMomeni\MahexWoo\Pro\FeatureSettings;
use HoseinMomeni\MahexWoo\Shipments\OrderShipmentStore;

defined( 'ABSPATH' ) || exit;

final class ProCenter {
	private const CAP = 'manage_woocommerce';

	public static function register(): void {
		add_action( 'admin_menu', array( self::class, 'menu' ), 90 );
		add_action( 'admin_post_hm_mahex_save_pro_settings', array( self::class, 'saveSettings' ) );
		add_action( 'admin_post_hm_mahex_save_inventory', array( self::class, 'saveInventory' ) );
		add_action( 'admin_post_hm_mahex_settings_export', array( self::class, 'exportSettings' ) );
		add_action( 'admin_post_hm_mahex_settings_import', array( self::class, 'importSettings' ) );
		add_action( 'admin_post_hm_mahex_settings_backup', array( self::class, 'backupSettings' ) );
		add_action( 'admin_post_hm_mahex_settings_restore', array( self::class, 'restoreSettings' ) );
		add_action( 'admin_post_hm_mahex_bulk_print_labels', array( self::class, 'bulkPrint' ) );
		add_action( 'admin_post_hm_mahex_save_actual_cost', array( self::class, 'saveActualCost' ) );
		add_action( 'admin_post_hm_mahex_export_pro_report', array( self::class, 'exportReport' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'assets' ) );
	}

	public static function menu(): void {
		add_submenu_page( 'hm-mahex', 'مرکز حرفه‌ای ماهکس', 'مرکز حرفه‌ای', self::CAP, 'hm-mahex-pro-center', array( self::class, 'center' ) );
		add_submenu_page( 'hm-mahex', 'قوانین پیشرفته ارسال', 'قوانین پیشرفته', self::CAP, 'hm-mahex-pro-rates', array( self::class, 'advanced' ) );
		add_submenu_page( 'hm-mahex', 'موجودی بسته‌بندی', 'موجودی بسته‌بندی', self::CAP, 'hm-mahex-inventory', array( self::class, 'inventory' ) );
		add_submenu_page( 'hm-mahex', 'ابزارها و پشتیبان', 'ابزارها و پشتیبان', self::CAP, 'hm-mahex-tools', array( self::class, 'tools' ) );
	}

	public static function assets( string $hook ): void {
		if ( false === strpos( $hook, 'hm-mahex' ) ) return;
		wp_enqueue_style( 'hm-mahex-admin', plugins_url( 'assets/admin/dashboard.css', HM_MAHEX_FILE ), array(), HM_MAHEX_VERSION );
		wp_add_inline_style( 'hm-mahex-admin', '.hmx-pro-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin:16px 0}.hmx-pro-kpi{background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:16px}.hmx-pro-kpi b{display:block;font-size:24px;color:#082e63}.hmx-pro-chart{display:flex;align-items:end;gap:7px;height:190px;padding-top:20px}.hmx-pro-day{flex:1;min-width:18px;height:100%;display:flex;gap:2px;align-items:flex-end;position:relative}.hmx-pro-bar{flex:1;min-width:7px;background:#0b3a78;border-radius:5px 5px 0 0;position:relative}.hmx-pro-bar--pack{background:#ef233c}.hmx-pro-day>span{position:absolute;bottom:-22px;right:50%;transform:translateX(50%);font-size:9px;white-space:nowrap}.hmx-pro-two{display:grid;grid-template-columns:1fr 1fr;gap:16px}.hmx-pro-field{display:flex;flex-direction:column;gap:5px}.hmx-pro-field input,.hmx-pro-field select,.hmx-pro-field textarea{max-width:100%}.hmx-pro-section{background:#fff;border:1px solid #dbe3ec;border-radius:14px;padding:18px;margin:16px 0}.hmx-pro-section h2{margin-top:0}.hmx-code-help{direction:ltr;text-align:left;background:#f8fafc;padding:10px;border-radius:8px;font-family:monospace;white-space:pre-wrap}.hmx-pro-actions{display:flex;gap:8px;flex-wrap:wrap}.hmx-danger{border-color:#b42318!important;color:#b42318!important}@media(max-width:1000px){.hmx-pro-grid{grid-template-columns:1fr 1fr}.hmx-pro-two{grid-template-columns:1fr}}' );
	}

	private static function guard( string $nonce ): void {
		if ( ! current_user_can( self::CAP ) ) wp_die( esc_html__( 'دسترسی غیرمجاز است.', 'mahex-shipping-for-woocommerce' ), '', array( 'response' => 403 ) );
		check_admin_referer( $nonce );
	}

	public static function center(): void {
		self::cap();
		$after = isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : wp_date( 'Y-m-01' );
		$before = isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : wp_date( 'Y-m-d' );
		$data = ( new ShippingAnalytics() )->summarize( $after, $before, 1000 );
		$rows = self::filterRows( $data['rows'] );
		$delivered = (int) ( $data['statuses']['delivered'] ?? 0 );
		$active = (int) ( $data['shipments'] ?? 0 ) - $delivered - (int) ( $data['statuses']['canceled'] ?? 0 ) - (int) ( $data['statuses']['returned'] ?? 0 );
		?>
		<div class="wrap hm-mahex" dir="rtl"><h1>مرکز حرفه‌ای ماهکس</h1><p>داشبورد عملیاتی ارسال، سود، چاپ گروهی، جست‌وجو و گزارش مشتریان.</p>
		<form method="get" class="hmx-pro-section"><input type="hidden" name="page" value="hm-mahex-pro-center"><div class="hmx-form-grid"><label class="hmx-pro-field"><span>از تاریخ</span><input type="date" name="from" value="<?php echo esc_attr( $after ); ?>"></label><label class="hmx-pro-field"><span>تا تاریخ</span><input type="date" name="to" value="<?php echo esc_attr( $before ); ?>"></label><label class="hmx-pro-field"><span>جست‌وجو</span><input name="s" value="<?php echo esc_attr( isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '' ); ?>" placeholder="سفارش، مشتری، موبایل، رهگیری"></label><label class="hmx-pro-field"><span>استان</span><input name="province" value="<?php echo esc_attr(isset($_GET['province'])?sanitize_text_field(wp_unslash($_GET['province'])):''); ?>"></label><label class="hmx-pro-field"><span>شهر</span><input name="city" value="<?php echo esc_attr(isset($_GET['city'])?sanitize_text_field(wp_unslash($_GET['city'])):''); ?>"></label><label class="hmx-pro-field"><span>وضعیت مرسوله</span><select name="shipment_status"><option value="">همه</option><?php foreach ( array( 'created'=>'ثبت‌شده','picked_up'=>'جمع‌آوری','in_transit'=>'در مسیر','out_for_delivery'=>'درحال تحویل','delivered'=>'تحویل‌شده','returned'=>'برگشتی','failed'=>'ناموفق','canceled'=>'لغوشده','none'=>'بدون مرسوله' ) as $k=>$v ) : ?><option value="<?php echo esc_attr($k); ?>" <?php selected( isset($_GET['shipment_status']) ? sanitize_key(wp_unslash($_GET['shipment_status'])) : '', $k ); ?>><?php echo esc_html($v); ?></option><?php endforeach; ?></select></label></div><?php submit_button( 'اعمال فیلتر', 'secondary', '', false ); ?></form>
		<div class="hmx-pro-actions"><a class="button" href="<?php echo esc_url(wp_nonce_url(add_query_arg(array('action'=>'hm_mahex_export_pro_report','format'=>'csv','from'=>$after,'to'=>$before),admin_url('admin-post.php')),'hm_mahex_export_pro_report')); ?>">CSV مناسب Excel</a><a class="button" href="<?php echo esc_url(wp_nonce_url(add_query_arg(array('action'=>'hm_mahex_export_pro_report','format'=>'xls','from'=>$after,'to'=>$before),admin_url('admin-post.php')),'hm_mahex_export_pro_report')); ?>">Excel (.xls)</a></div><div class="hmx-pro-grid"><?php self::kpi( 'سفارش', $data['orders'] ); self::kpi( 'مرسوله', $data['shipments'] ); self::kpi( 'درحال ارسال', max(0,$active) ); self::kpi( 'تحویل‌شده', $delivered ); self::kpi( 'برگشتی', (int)($data['statuses']['returned']??0) ); self::kpi( 'لغوشده', (int)($data['statuses']['canceled']??0) ); self::kpiMoney( 'درآمد ارسال', $data['shipping_revenue'] ); self::kpiMoney( 'درآمد بسته‌بندی', $data['packaging_revenue'] ); self::kpiMoney( 'هزینه حمل', $data['carrier_cost'] ); self::kpiMoney( 'سود تخمینی', $data['profit'] ); ?></div>
		<div class="hmx-pro-two"><section class="hmx-pro-section"><h2>روند درآمد ارسال و بسته‌بندی</h2><p class="description">آبی: ارسال · قرمز: بسته‌بندی</p><?php self::chart( $data['daily'] ); ?></section><section class="hmx-pro-section"><h2>مشتریان پرتکرار</h2><table class="widefat striped"><thead><tr><th>مشتری</th><th>سفارش</th><th>هزینه ارسال</th></tr></thead><tbody><?php foreach ( array_slice( array_values( $data['customers'] ), 0, 10 ) as $c ) : ?><tr><td><?php echo esc_html($c['name']); ?></td><td><?php echo esc_html(number_format_i18n($c['orders'])); ?></td><td><?php echo wp_kses_post(wc_price($c['shipping'])); ?></td></tr><?php endforeach; ?></tbody></table></section></div>
		<section class="hmx-pro-section"><h2>سفارش‌ها و چاپ گروهی</h2><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" target="_blank"><input type="hidden" name="action" value="hm_mahex_bulk_print_labels"><?php wp_nonce_field('hm_mahex_bulk_print_labels'); ?><div class="hmx-pro-actions"><button class="button" name="layout" value="a4">چاپ لیبل A4</button><button class="button button-primary" name="layout" value="thermal">چاپ حرارتی</button></div><table class="widefat striped"><thead><tr><th><input type="checkbox" onclick="document.querySelectorAll('.hmx-print-check').forEach(e=>e.checked=this.checked)"></th><th>سفارش</th><th>مشتری</th><th>شهر</th><th>وضعیت</th><th>ارسال</th><th>هزینه واقعی</th><th>سود</th></tr></thead><tbody><?php foreach ( array_slice( $rows, 0, 100 ) as $row ) : ?><tr><td><input class="hmx-print-check" type="checkbox" name="order_ids[]" value="<?php echo esc_attr((string)$row['id']); ?>"></td><td><a href="<?php echo esc_url( (wc_get_order($row['id']))->get_edit_order_url() ); ?>">#<?php echo esc_html((string)$row['id']); ?></a></td><td><?php echo esc_html($row['name']); ?></td><td><?php echo esc_html($row['city'] ?: '—'); ?></td><td><?php echo esc_html($row['status']); ?></td><td><?php echo wp_kses_post(wc_price($row['shipping'])); ?></td><td><?php echo wp_kses_post(wc_price($row['carrier'])); ?></td><td><?php echo wp_kses_post(wc_price($row['profit'])); ?></td></tr><?php endforeach; ?></tbody></table></form></section>
		</div><?php
	}

	public static function advanced(): void {
		self::cap(); $s = FeatureSettings::all(); $sim = self::advancedSimulation();
		?><div class="wrap hm-mahex" dir="rtl"><h1>قوانین پیشرفته نرخ و اتوماسیون</h1><?php self::notice(); ?>
		<section class="hmx-pro-section"><h2>شبیه‌ساز پیشرفته Checkout</h2><form method="post"><div class="hmx-form-grid"><label class="hmx-pro-field"><span>استان</span><input name="sim_province" value="<?php echo esc_attr(isset($_POST['sim_province'])?sanitize_text_field(wp_unslash($_POST['sim_province'])):'قم'); ?>"></label><label class="hmx-pro-field"><span>شهر</span><input name="sim_city" value="<?php echo esc_attr(isset($_POST['sim_city'])?sanitize_text_field(wp_unslash($_POST['sim_city'])):'قم'); ?>"></label><label class="hmx-pro-field"><span>کدپستی</span><input name="sim_postcode" value="<?php echo esc_attr(isset($_POST['sim_postcode'])?sanitize_text_field(wp_unslash($_POST['sim_postcode'])):'3710000000'); ?>"></label><label class="hmx-pro-field"><span>وزن قابل پرداخت اولیه (گرم)</span><input type="number" min="1" name="sim_weight_g" value="<?php echo esc_attr(isset($_POST['sim_weight_g'])?absint($_POST['sim_weight_g']):1200); ?>"></label><label class="hmx-pro-field"><span>ارزش سبد (ریال)</span><input type="number" min="0" name="sim_cart_irr" value="<?php echo esc_attr(isset($_POST['sim_cart_irr'])?absint($_POST['sim_cart_irr']):5000000); ?>"></label></div><?php wp_nonce_field('hm_mahex_pro_sim','hm_mahex_pro_sim_nonce'); ?><button class="button">شبیه‌سازی</button></form><?php if(is_array($sim)): ?><p><strong>وزن صورتحساب:</strong> <?php echo esc_html(number_format_i18n($sim['billable_g'])); ?> گرم · <strong>منطقه:</strong> <?php echo esc_html($sim['zone']); ?> · <strong>کرایه:</strong> <?php echo wp_kses_post(wc_price(\HoseinMomeni\MahexWoo\Shipping\PricingSettings::to_store_currency($sim['freight']))); ?></p><code style="display:block;direction:ltr;text-align:left;white-space:pre-wrap"><?php echo esc_html(implode("\n",$sim['breakdown'])); ?></code><?php endif; ?></section>
		<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="hm_mahex_save_pro_settings"><?php wp_nonce_field('hm_mahex_save_pro_settings'); ?>
		<section class="hmx-pro-section"><h2>وزن و مناطق</h2><div class="hmx-form-grid"><?php self::num('min_billable_weight_g','حداقل وزن قابل محاسبه (گرم)',$s); self::num('weight_step_g','پله گردکردن وزن (گرم)',$s); self::select('zone_mode','نحوه نرخ منطقه',$s,array('surcharge'=>'افزایش روی نرخ','fixed'=>'نرخ ثابت')); self::num('tehran_adjustment','تهران (ریال)',$s); self::num('adjacent_adjustment','همجوار (ریال)',$s); self::num('non_adjacent_adjustment','غیرهمجوار (ریال)',$s); self::num('remote_adjustment','دورافتاده (ریال)',$s); ?></div><?php self::area('adjacent_provinces','استان‌های همجوار (با ویرگول)',$s); self::area('remote_cities','شهرهای دورافتاده',$s); self::area('remote_postcode_prefixes','پیش‌شماره کدپستی مناطق دورافتاده',$s); ?><p class="description">مبالغ این صفحه به ریال هستند و بعد از نرخ استان/شهر اعمال می‌شوند.</p></section>
		<section class="hmx-pro-section"><h2>قواعد کدپستی و سبد خرید</h2><?php self::area('postcode_rules','قواعد کدپستی',$s); ?><div class="hmx-code-help">قالب هر خط: 371|surcharge|150000\n141|fixed|900000</div><div class="hmx-form-grid"><?php self::num('low_cart_fee_threshold_irr','آستانه سبد کم‌مبلغ (ریال)',$s); self::num('low_cart_fee_irr','هزینه اضافه سبد کم‌مبلغ (ریال)',$s); self::num('high_cart_discount_threshold_irr','آستانه تخفیف سبد بزرگ (ریال)',$s); self::num('high_cart_discount_percent','درصد تخفیف ارسال سبد بزرگ',$s,'0.1'); ?></div></section>
		<section class="hmx-pro-section"><h2>VIP، نقش کاربری و مناسبت</h2><?php self::area('vip_roles','نقش‌های VIP',$s); self::area('role_adjustments','تغییر نرخ بر اساس نقش',$s); ?><div class="hmx-code-help">قالب هر خط نقش: wholesale_customer|-15\npartner|10</div><div class="hmx-form-grid"><?php self::num('vip_discount_percent','تخفیف VIP (%)',$s,'0.1'); self::num('seasonal_surcharge_percent','افزایش مناسبتی (%)',$s,'0.1'); self::date('seasonal_start','شروع',$s); self::date('seasonal_end','پایان',$s); ?></div></section>
		<section class="hmx-pro-section"><h2>اتوماسیون و سود</h2><div class="hmx-form-grid"><?php self::num('carrier_cost_percent','درصد تخمینی هزینه واقعی حمل از مبلغ دریافتی',$s,'0.1'); self::num('packaging_low_stock_threshold','هشدار موجودی بسته‌بندی',$s); self::select('sync_interval','تناوب همگام‌سازی',$s,array('hourly'=>'ساعتی','twicedaily'=>'روزی دوبار','daily'=>'روزانه')); self::select('label_template','قالب بارنامه',$s,array('classic'=>'کلاسیک','compact'=>'فشرده','minimal'=>'مینیمال')); self::select('thermal_size','ابعاد لیبل حرارتی',$s,array('80x100'=>'80×100','100x150'=>'100×150')); self::text('store_logo_url','نشانی لوگوی فروشگاه',$s); ?></div><?php self::check('auto_create_shipment','ساخت خودکار مرسوله با ورود سفارش به processing/completed',$s); self::check('auto_sync_status','همگام‌سازی خودکار وضعیت مرسوله‌ها',$s); self::check('sync_order_status','تغییر خودکار وضعیت سفارش ووکامرس بر اساس وضعیت مرسوله',$s); self::area('order_status_mappings','نگاشت وضعیت مرسوله به وضعیت سفارش (shipment|woocommerce)',$s); self::check('debug_rate_log','ثبت جزئیات محاسبه نرخ در WooCommerce Logs',$s); ?></section>

		<?php submit_button('ذخیره تنظیمات پیشرفته'); ?></form></div><?php
	}

	public static function inventory(): void {
		self::cap(); $profiles = PackagingProfiles::all(); $low = PackagingInventory::lowStock();
		?><div class="wrap hm-mahex" dir="rtl"><h1>موجودی بسته‌بندی</h1><?php self::notice(); ?><div class="hmx-pro-grid"><?php self::kpi('پروفایل بسته',count($profiles)); self::kpi('کم‌موجودی',count($low)); self::kpi('کل موجودی',array_sum(array_map(static fn($p)=>(int)($p['stock']??0),$profiles))); self::kpiMoney('ارزش موجودی',array_sum(array_map(static fn($p)=>(float)($p['stock']??0)*(float)($p['unit_cost_irr']??0),$profiles))/ ( 'IRT'===strtoupper(get_woocommerce_currency()) ? 10 : 1 )); ?></div><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="hm_mahex_save_inventory"><?php wp_nonce_field('hm_mahex_save_inventory'); ?><table class="widefat striped"><thead><tr><th>پروفایل</th><th>نوع</th><th>ابعاد</th><th>حداکثر وزن</th><th>موجودی</th><th>بهای هر بسته (ریال)</th><th>وضعیت</th></tr></thead><tbody><?php foreach($profiles as $id=>$p): $stock=(int)($p['stock']??0); ?><tr><td><?php echo esc_html((string)($p['name']??$id)); ?></td><td><?php echo esc_html(array('envelope'=>'پاکت','carton'=>'کارتن','box'=>'جعبه','custom'=>'سفارشی')[(string)($p['type']??'custom')]??'سفارشی'); ?></td><td><?php echo esc_html(($p['length_mm']??0).'×'.($p['width_mm']??0).'×'.($p['height_mm']??0)); ?> mm</td><td><?php echo esc_html((string)($p['max_weight_g']??0)); ?> g</td><td><input type="number" min="0" name="inventory[<?php echo esc_attr($id); ?>][stock]" value="<?php echo esc_attr((string)$stock); ?>"></td><td><input type="number" min="0" name="inventory[<?php echo esc_attr($id); ?>][unit_cost_irr]" value="<?php echo esc_attr((string)($p['unit_cost_irr']??0)); ?>"></td><td><?php echo $stock<=FeatureSettings::int('packaging_low_stock_threshold',10) ? '<span class="hmx-state hmx-state--warn">کم</span>' : '<span class="hmx-state hmx-state--ok">مناسب</span>'; ?></td></tr><?php endforeach; ?></tbody></table><?php submit_button('ذخیره موجودی'); ?></form></div><?php
	}

	public static function tools(): void {
		self::cap(); $backups = get_option('hm_mahex_settings_backups',array()); $backups=is_array($backups)?$backups:array();
		?><div class="wrap hm-mahex" dir="rtl"><h1>ابزارها، خروجی و پشتیبان</h1><?php self::notice(); ?><section class="hmx-pro-section"><h2>خروجی / ورود تنظیمات</h2><div class="hmx-pro-actions"><a class="button button-primary" href="<?php echo esc_url(wp_nonce_url(add_query_arg('action','hm_mahex_settings_export',admin_url('admin-post.php')),'hm_mahex_settings_export')); ?>">دانلود JSON تنظیمات</a><form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="hm_mahex_settings_import"><?php wp_nonce_field('hm_mahex_settings_import'); ?><input type="file" name="settings_file" accept="application/json,.json" required><button class="button">ورود JSON</button></form></div></section><section class="hmx-pro-section"><h2>Backup / Restore داخلی</h2><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="hm_mahex_settings_backup"><?php wp_nonce_field('hm_mahex_settings_backup'); ?><button class="button button-primary">ساخت نسخه پشتیبان</button></form><table class="widefat striped"><thead><tr><th>زمان</th><th>نسخه</th><th>عملیات</th></tr></thead><tbody><?php foreach(array_reverse($backups,true) as $id=>$b): ?><tr><td><?php echo esc_html((string)($b['created_at']??'')); ?></td><td><?php echo esc_html((string)($b['version']??'')); ?></td><td><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('تنظیمات این نسخه بازیابی شود؟')"><input type="hidden" name="action" value="hm_mahex_settings_restore"><input type="hidden" name="backup_id" value="<?php echo esc_attr((string)$id); ?>"><?php wp_nonce_field('hm_mahex_settings_restore_'.$id); ?><button class="button">بازیابی</button></form></td></tr><?php endforeach; ?></tbody></table></section><section class="hmx-pro-section"><h2>قابلیت‌های نسخه</h2><p>این نسخه شامل داشبورد KPI، سود، گزارش مشتری، فیلتر سفارش، چاپ A4/حرارتی، موجودی بسته، هشدار کمبود، وزن حجمی، حداقل/گردکردن وزن، منطقه‌بندی، کدپستی، VIP/نقش، مناسبت، Backup/Restore و ابزارهای کامل محلی نسخه 1 است.</p></section></div><?php
	}

	public static function saveSettings(): void { self::guard('hm_mahex_save_pro_settings'); $input=isset($_POST['hm_mahex_pro_settings'])&&is_array($_POST['hm_mahex_pro_settings'])?wp_unslash($_POST['hm_mahex_pro_settings']):array(); update_option(FeatureSettings::OPTION,FeatureSettings::sanitize($input),false); self::redirect('hm-mahex-pro-rates','saved'); }
	public static function saveInventory(): void { self::guard('hm_mahex_save_inventory'); $rows=isset($_POST['inventory'])&&is_array($_POST['inventory'])?wp_unslash($_POST['inventory']):array(); $profiles=PackagingProfiles::all(); foreach($rows as $id=>$row){$id=sanitize_key($id); if(!isset($profiles[$id])||!is_array($row))continue; $profiles[$id]['stock']=max(0,absint($row['stock']??0)); $profiles[$id]['unit_cost_irr']=max(0,(int)($row['unit_cost_irr']??0));} update_option(PackagingProfiles::OPTION_NAME,$profiles,false); self::redirect('hm-mahex-inventory','saved'); }

	public static function exportSettings(): void { self::guard('hm_mahex_settings_export'); $data=self::snapshot(false); nocache_headers(); header('Content-Type: application/json; charset=utf-8'); header('Content-Disposition: attachment; filename="mahex-settings-'.gmdate('Y-m-d-His').'.json"'); echo wp_json_encode(array('schema'=>1,'plugin_version'=>HM_MAHEX_VERSION,'exported_at'=>gmdate(DATE_ATOM),'data'=>$data),JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT); exit; }
	public static function importSettings(): void { self::guard('hm_mahex_settings_import'); if(empty($_FILES['settings_file']['tmp_name'])||!is_uploaded_file($_FILES['settings_file']['tmp_name'])||(!empty($_FILES['settings_file']['size'])&&(int)$_FILES['settings_file']['size']>1048576)) self::redirect('hm-mahex-tools','invalid'); $raw=file_get_contents($_FILES['settings_file']['tmp_name']); $decoded=json_decode((string)$raw,true); if(!is_array($decoded)||!isset($decoded['data'])||!is_array($decoded['data'])) self::redirect('hm-mahex-tools','invalid'); self::applySnapshot($decoded['data']); self::redirect('hm-mahex-tools','imported'); }
	public static function backupSettings(): void { self::guard('hm_mahex_settings_backup'); $backups=get_option('hm_mahex_settings_backups',array()); $backups=is_array($backups)?$backups:array(); $id=gmdate('YmdHis').'-'.wp_generate_password(4,false,false); $backups[$id]=array('created_at'=>wp_date('Y/m/d H:i:s'),'version'=>HM_MAHEX_VERSION,'data'=>self::snapshot(true)); if(count($backups)>10)$backups=array_slice($backups,-10,null,true); update_option('hm_mahex_settings_backups',$backups,false); self::redirect('hm-mahex-tools','backedup'); }
	public static function restoreSettings(): void { $id=isset($_POST['backup_id'])?sanitize_key(wp_unslash($_POST['backup_id'])):''; self::guard('hm_mahex_settings_restore_'.$id); $backups=get_option('hm_mahex_settings_backups',array()); if(!is_array($backups)||!isset($backups[$id]['data'])||!is_array($backups[$id]['data'])) self::redirect('hm-mahex-tools','invalid'); self::applySnapshot($backups[$id]['data']); self::redirect('hm-mahex-tools','restored'); }

	public static function exportReport(): void {
		self::guard( 'hm_mahex_export_pro_report' );
		$from = isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : '';
		$to = isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : '';
		$format = isset( $_GET['format'] ) && 'xls' === sanitize_key( wp_unslash( $_GET['format'] ) ) ? 'xls' : 'csv';
		$rows = ( new ShippingAnalytics() )->summarize( $from, $to, 2000 )['rows'];
		nocache_headers();
		if ( 'xls' === $format ) {
			header( 'Content-Type: application/vnd.ms-excel; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename="mahex-report-' . gmdate( 'Y-m-d-His' ) . '.xls"' );
			echo "\xEF\xBB\xBF";
			echo '<html><head><meta charset="utf-8"></head><body><table border="1"><tr><th>سفارش</th><th>تاریخ</th><th>مشتری</th><th>شهر</th><th>وضعیت</th><th>رهگیری</th><th>درآمد ارسال</th><th>هزینه حمل</th><th>سود</th></tr>';
			foreach ( $rows as $r ) echo '<tr><td>' . esc_html( (string) $r['id'] ) . '</td><td>' . esc_html( $r['date'] ) . '</td><td>' . esc_html( $r['name'] ) . '</td><td>' . esc_html( $r['city'] ) . '</td><td>' . esc_html( $r['status'] ) . '</td><td>' . esc_html( $r['tracking'] ) . '</td><td>' . esc_html( (string) $r['shipping'] ) . '</td><td>' . esc_html( (string) $r['carrier'] ) . '</td><td>' . esc_html( (string) $r['profit'] ) . '</td></tr>';
			echo '</table></body></html>'; exit;
		}
		header( 'Content-Type: text/csv; charset=utf-8' ); header( 'Content-Disposition: attachment; filename="mahex-report-' . gmdate( 'Y-m-d-His' ) . '.csv"' );
		$out = fopen( 'php://output', 'wb' ); if ( false === $out ) exit; fwrite( $out, "\xEF\xBB\xBF" );
		fputcsv( $out, array( 'order_id','date','customer','city','shipment_status','tracking','shipping_revenue','carrier_cost','profit' ) );
		foreach ( $rows as $r ) fputcsv( $out, array_map( array( self::class, 'csvSafe' ), array( $r['id'],$r['date'],$r['name'],$r['city'],$r['status'],$r['tracking'],$r['shipping'],$r['carrier'],$r['profit'] ) ) );
		fclose( $out ); exit;
	}

	private static function csvSafe( mixed $v ): string { $v=(string)$v; return preg_match('/^[=+\-@]/',$v) ? "'".$v : $v; }

	public static function bulkPrint(): void { self::guard('hm_mahex_bulk_print_labels'); $ids=isset($_POST['order_ids'])&&is_array($_POST['order_ids'])?array_values(array_unique(array_filter(array_map('absint',wp_unslash($_POST['order_ids']))))):array(); if(!$ids) wp_die('هیچ سفارشی انتخاب نشده است.'); $layout=isset($_POST['layout'])&&'thermal'===sanitize_key(wp_unslash($_POST['layout']))?'thermal':'a4'; $factory=new OrderWaybillFactory(); $docs=array(); foreach(array_slice($ids,0,100) as $id){$order=wc_get_order($id); if($order&&current_user_can('edit_shop_order',$id))$docs[]=$factory->from_order($order);} nocache_headers(); header('Content-Type: text/html; charset=utf-8'); echo (new BulkLabelRenderer())->render($docs,$layout); exit; }
	public static function saveActualCost(): void { self::guard('hm_mahex_save_actual_cost'); $id=isset($_POST['order_id'])?absint($_POST['order_id']):0; $order=wc_get_order($id); if(!$order||!current_user_can('edit_shop_order',$id)) wp_die('دسترسی غیرمجاز'); $cost=isset($_POST['actual_cost'])&&is_numeric($_POST['actual_cost'])?max(0,(float)$_POST['actual_cost']):0; $order->update_meta_data('_hm_mahex_actual_carrier_cost',$cost); $order->save(); wp_safe_redirect($order->get_edit_order_url()); exit; }

	private static function advancedSimulation(): ?array {
		if ( empty( $_POST['hm_mahex_pro_sim_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['hm_mahex_pro_sim_nonce'] ) ), 'hm_mahex_pro_sim' ) ) return null;
		$weight = max( 1, absint( $_POST['sim_weight_g'] ?? 1 ) );
		$billable = \HoseinMomeni\MahexWoo\Shipping\AdvancedRateEngine::billableWeightGrams( $weight );
		$province = sanitize_text_field( wp_unslash( $_POST['sim_province'] ?? '' ) );
		$city = sanitize_text_field( wp_unslash( $_POST['sim_city'] ?? '' ) );
		$postcode = preg_replace( '/\D/', '', (string) wp_unslash( $_POST['sim_postcode'] ?? '' ) ) ?: '';
		$cart = max( 0, (float) ( $_POST['sim_cart_irr'] ?? 0 ) );
		$base = \HoseinMomeni\MahexWoo\Shipping\PricingSettings::money( 'manual_base_cost', 850000 );
		$per = \HoseinMomeni\MahexWoo\Shipping\PricingSettings::money( 'manual_cost_per_kg', 75000 );
		$freight = $base + ceil( $billable / 1000 ) * $per;
		$freight = \HoseinMomeni\MahexWoo\Shipping\PricingSettings::province_rate( $province, $freight );
		$package = array( 'contents_cost' => \HoseinMomeni\MahexWoo\Shipping\PricingSettings::to_store_currency( $cart ) );
		$adjusted = \HoseinMomeni\MahexWoo\Shipping\AdvancedRateEngine::adjust( $freight, $package, \HoseinMomeni\MahexWoo\Shipping\PricingSettings::province_name( $province ), $city, $postcode );
		return array( 'billable_g' => $billable, 'zone' => \HoseinMomeni\MahexWoo\Shipping\AdvancedRateEngine::zone( \HoseinMomeni\MahexWoo\Shipping\PricingSettings::province_name( $province ), $city, $postcode ), 'freight' => $adjusted['freight'], 'breakdown' => $adjusted['breakdown'] );
	}

	private static function snapshot(bool $includeSecrets=true): array {
		$pro=FeatureSettings::all();
		$enterprise=\HoseinMomeni\MahexWoo\Enterprise\Config::all();
		return array(
			'hm_mahex_settings'=>get_option('hm_mahex_settings',array()),
			'hm_mahex_pricing_settings'=>get_option('hm_mahex_pricing_settings',array()),
			FeatureSettings::OPTION=>$pro,
			PackagingProfiles::OPTION_NAME=>PackagingProfiles::all(),
			'hm_mahex_rate_rules'=>get_option('hm_mahex_rate_rules',array()),
			'hm_mahex_waybill_sequence_start'=>get_option('hm_mahex_waybill_sequence_start',100400),
			\HoseinMomeni\MahexWoo\Enterprise\Config::OPTION=>$enterprise,
			\HoseinMomeni\MahexWoo\Enterprise\Config::WAREHOUSES=>(new \HoseinMomeni\MahexWoo\Enterprise\WarehouseRepository())->all(),
		);
	}
	private static function applySnapshot( array $d ): void {
		if ( isset( $d['hm_mahex_settings'] ) && is_array( $d['hm_mahex_settings'] ) ) {
			update_option( 'hm_mahex_settings', Dashboard::sanitize_settings( $d['hm_mahex_settings'] ), false );
		}
		if ( isset( $d['hm_mahex_pricing_settings'] ) && is_array( $d['hm_mahex_pricing_settings'] ) ) {
			update_option( 'hm_mahex_pricing_settings', \HoseinMomeni\MahexWoo\Rates\PricingSettings::normalizeStored( $d['hm_mahex_pricing_settings'] ), false );
		}
		if ( isset( $d[ FeatureSettings::OPTION ] ) && is_array( $d[ FeatureSettings::OPTION ] ) ) {
			$pro = $d[ FeatureSettings::OPTION ];
			update_option( FeatureSettings::OPTION, FeatureSettings::sanitize( $pro ), false );
		}
		if ( isset( $d[ PackagingProfiles::OPTION_NAME ] ) && is_array( $d[ PackagingProfiles::OPTION_NAME ] ) ) {
			$profiles = array();
			foreach ( $d[ PackagingProfiles::OPTION_NAME ] as $raw ) {
				if ( ! is_array( $raw ) ) continue;
				try {
					$profile = \HoseinMomeni\MahexWoo\Products\PackagingProfile::fromInput( $raw );
					$profiles[ $profile['id'] ] = $profile;
				} catch ( \InvalidArgumentException ) {}
			}
			update_option( PackagingProfiles::OPTION_NAME, $profiles, false );
		}
		if ( isset( $d['hm_mahex_rate_rules'] ) && is_array( $d['hm_mahex_rate_rules'] ) ) {
			$rules = array();
			foreach ( $d['hm_mahex_rate_rules'] as $raw ) {
				if ( ! is_array( $raw ) ) continue;
				$clean = array(
					'id' => sanitize_key( $raw['id'] ?? '' ),
					'name' => sanitize_text_field( $raw['name'] ?? '' ),
					'priority' => absint( $raw['priority'] ?? 0 ),
					'mode' => sanitize_key( $raw['mode'] ?? '' ),
					'amount' => absint( $raw['amount'] ?? 0 ),
					'currency' => 'IRR',
					'province' => sanitize_text_field( $raw['province'] ?? '' ),
					'city' => sanitize_text_field( $raw['city'] ?? '' ),
					'active' => ! empty( $raw['active'] ),
				);
				try {
					$rules[] = \HoseinMomeni\MahexWoo\Rates\Admin\RateRuleRecord::fromArray( $clean )->toArray();
				} catch ( \InvalidArgumentException ) {}
			}
			update_option( 'hm_mahex_rate_rules', $rules, false );
		}
		if ( isset( $d['hm_mahex_waybill_sequence_start'] ) ) {
			update_option( 'hm_mahex_waybill_sequence_start', max( 1, absint( $d['hm_mahex_waybill_sequence_start'] ) ), false );
		}
		if ( isset( $d[ \HoseinMomeni\MahexWoo\Enterprise\Config::OPTION ] ) && is_array( $d[ \HoseinMomeni\MahexWoo\Enterprise\Config::OPTION ] ) ) {
			$enterprise = $d[ \HoseinMomeni\MahexWoo\Enterprise\Config::OPTION ];
			update_option( \HoseinMomeni\MahexWoo\Enterprise\Config::OPTION, \HoseinMomeni\MahexWoo\Enterprise\Config::sanitize( $enterprise ), false );
		}
		if ( isset( $d[ \HoseinMomeni\MahexWoo\Enterprise\Config::WAREHOUSES ] ) && is_array( $d[ \HoseinMomeni\MahexWoo\Enterprise\Config::WAREHOUSES ] ) ) {
			(new \HoseinMomeni\MahexWoo\Enterprise\WarehouseRepository())->save( $d[ \HoseinMomeni\MahexWoo\Enterprise\Config::WAREHOUSES ] );
		}
	}
	private static function filterRows(array $rows): array { $q=isset($_GET['s'])?self::lower(trim(sanitize_text_field(wp_unslash($_GET['s'])))) : ''; $status=isset($_GET['shipment_status'])?sanitize_key(wp_unslash($_GET['shipment_status'])):''; $province=isset($_GET['province'])?self::lower(trim(sanitize_text_field(wp_unslash($_GET['province'])))):''; $city=isset($_GET['city'])?self::lower(trim(sanitize_text_field(wp_unslash($_GET['city'])))):''; if(''===$q&&''===$status&&''===$province&&''===$city)return $rows; $out=array(); foreach($rows as $r){if(''!==$status&&$r['status']!==$status)continue; if(''!==$province&&!str_contains(self::lower((string)($r['province']??'')),$province))continue; if(''!==$city&&!str_contains(self::lower((string)($r['city']??'')),$city))continue; if(''!==$q){$order=wc_get_order($r['id']); $hay=self::lower(implode(' ',array($r['id'],$r['name'],$r['city'],$r['tracking'],$order?$order->get_billing_phone():'',$order?(string)$order->get_meta('_hm_mahex_barcode_number',true):''))); $pos=function_exists('mb_strpos')?mb_strpos($hay,$q):strpos($hay,$q); if(false===$pos)continue;} $out[]=$r;} return $out; }
	private static function lower(string $v): string { return function_exists('mb_strtolower') ? mb_strtolower($v,'UTF-8') : strtolower($v); }
	private static function cap(): void { if(!current_user_can(self::CAP)) wp_die('دسترسی غیرمجاز', '', array('response'=>403)); }
	private static function redirect(string $page,string $notice): never { wp_safe_redirect(add_query_arg(array('page'=>$page,'hm_notice'=>$notice),admin_url('admin.php'))); exit; }
	private static function notice(): void { $n=isset($_GET['hm_notice'])?sanitize_key(wp_unslash($_GET['hm_notice'])):''; $m=array('saved'=>'ذخیره شد.','imported'=>'تنظیمات با موفقیت وارد شد.','backedup'=>'نسخه پشتیبان ساخته شد.','restored'=>'نسخه پشتیبان بازیابی شد.','invalid'=>'داده معتبر نیست.'); if(isset($m[$n])) echo '<div class="notice '.('invalid'===$n?'notice-error':'notice-success').' inline"><p>'.esc_html($m[$n]).'</p></div>'; }
	private static function kpi(string $label,int|float $value): void { echo '<div class="hmx-pro-kpi"><b>'.esc_html(number_format_i18n($value)).'</b><span>'.esc_html($label).'</span></div>'; }
	private static function kpiMoney(string $label,float $value): void { echo '<div class="hmx-pro-kpi"><b>'.wp_kses_post(wc_price($value)).'</b><span>'.esc_html($label).'</span></div>'; }
	private static function chart(array $daily): void { if(!$daily){echo '<p>داده‌ای نیست.</p>';return;} $daily=array_slice($daily,-14,14,true); $values=array(); foreach($daily as $v){$values[]=(float)($v['shipping']??0);$values[]=(float)($v['packaging']??0);} $max=max(1.0,max($values)); echo '<div class="hmx-pro-chart">'; foreach($daily as $day=>$v){$shipping=(float)($v['shipping']??0);$packaging=(float)($v['packaging']??0);$hs=max(4,round(($shipping/$max)*160));$hp=$packaging>0?max(4,round(($packaging/$max)*160)):0; echo '<div class="hmx-pro-day"><div class="hmx-pro-bar" style="height:'.esc_attr((string)$hs).'px" title="ارسال: '.esc_attr(wp_strip_all_tags(wc_price($shipping))).'"></div><div class="hmx-pro-bar hmx-pro-bar--pack" style="height:'.esc_attr((string)$hp).'px" title="بسته‌بندی: '.esc_attr(wp_strip_all_tags(wc_price($packaging))).'"></div><span>'.esc_html(substr($day,5)).'</span></div>'; } echo '</div>'; }
	private static function num(string $key,string $label,array $s,string $step='1'): void { echo '<label class="hmx-pro-field"><span>'.esc_html($label).'</span><input type="number" min="0" step="'.esc_attr($step).'" name="hm_mahex_pro_settings['.esc_attr($key).']" value="'.esc_attr((string)($s[$key]??0)).'"></label>'; }
	private static function text(string $key,string $label,array $s,string $type='text',string $override=''): void { $value=''!==$override?$override:(string)($s[$key]??''); echo '<label class="hmx-pro-field"><span>'.esc_html($label).'</span><input type="'.esc_attr($type).'" name="hm_mahex_pro_settings['.esc_attr($key).']" value="'.esc_attr($value).'" autocomplete="off"></label>'; }
	private static function date(string $key,string $label,array $s): void { echo '<label class="hmx-pro-field"><span>'.esc_html($label).'</span><input type="date" name="hm_mahex_pro_settings['.esc_attr($key).']" value="'.esc_attr((string)($s[$key]??'')).'"></label>'; }
	private static function select(string $key,string $label,array $s,array $options): void { echo '<label class="hmx-pro-field"><span>'.esc_html($label).'</span><select name="hm_mahex_pro_settings['.esc_attr($key).']">'; foreach($options as $v=>$t) echo '<option value="'.esc_attr($v).'" '.selected((string)($s[$key]??''),$v,false).'>'.esc_html($t).'</option>'; echo '</select></label>'; }
	private static function area(string $key,string $label,array $s): void { echo '<label class="hmx-pro-field" style="margin:12px 0"><span>'.esc_html($label).'</span><textarea rows="4" name="hm_mahex_pro_settings['.esc_attr($key).']">'.esc_textarea((string)($s[$key]??'')).'</textarea></label>'; }
	private static function check(string $key,string $label,array $s): void { echo '<label style="display:block;margin:10px 0"><input type="checkbox" name="hm_mahex_pro_settings['.esc_attr($key).']" value="1" '.checked(!empty($s[$key]),true,false).'> '.esc_html($label).'</label>'; }
}
