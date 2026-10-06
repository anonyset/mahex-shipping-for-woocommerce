<?php

namespace HoseinMomeni\MahexWoo\V25;

final class Admin {
	private const PAGE = 'hm-mahex-v25';

	public static function register(): void {
		add_action( 'admin_menu', array( self::class, 'menu' ), 31 );
		$actions = array(
			'save_settings' => 'saveSettings',
			'scan_inbox' => 'scanInbox',
			'resolve_inbox' => 'resolveInbox',
			'apply_rule' => 'applyRule',
			'save_workflow' => 'saveWorkflow',
			'update_workflow' => 'updateWorkflow',
			'add_ledger' => 'addLedger',
			'close_month' => 'closeMonth',
			'save_report' => 'saveReport',
			'save_dashboard' => 'saveDashboard',
			'archive_now' => 'archiveNow',
			'backfill_now' => 'backfillNow',
		);
		foreach ( $actions as $action => $method ) add_action( 'admin_post_hm_mahex_v25_' . $action, array( self::class, $method ) );
	}

	public static function menu(): void {
		add_submenu_page( 'hm-mahex', 'Mahex Intelligence 2.5', 'Intelligence 2.5', 'manage_woocommerce', self::PAGE, array( self::class, 'render' ) );
	}

	public static function render(): void {
		self::cap();
		$tab = sanitize_key( (string) ( $_GET['tab'] ?? 'overview' ) );
		$tabs = array( 'overview'=>'نمای کلی','intelligence'=>'هوشمندی','operations'=>'عملیات','finance'=>'مالی','reports'=>'گزارش‌ساز','system'=>'سیستم' );
		if ( ! isset( $tabs[ $tab ] ) ) $tab = 'overview';
		echo '<div class="wrap" dir="rtl"><h1>Mahex Shipping OS <small style="font-size:14px">2.5.0 Intelligence & Automation</small></h1><p>تحلیل، اتوماسیون و کنترل مالی کاملاً محلی؛ بدون API خارجی.</p><nav class="nav-tab-wrapper">';
		foreach ( $tabs as $key => $label ) echo '<a class="nav-tab ' . ( $tab === $key ? 'nav-tab-active' : '' ) . '" href="' . esc_url( admin_url( 'admin.php?page=' . self::PAGE . '&tab=' . $key ) ) . '">' . esc_html( $label ) . '</a>';
		echo '</nav><div style="margin-top:16px">';
		match ( $tab ) {
			'intelligence' => self::intelligence(),
			'operations' => self::operations(),
			'finance' => self::finance(),
			'reports' => self::reports(),
			'system' => self::system(),
			default => self::overview(),
		};
		echo '</div></div>';
	}

	private static function overview(): void {
		$brief = Operations::morningBrief();
		$forecast = Intelligence::profitForecast();
		$loss = Intelligence::losses();
		$widgets = ReportCenter::dashboardWidgets();
		echo '<div style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px">';
		if ( in_array( 'inbox', $widgets, true ) ) echo '<div class="card"><h3>Inbox باز</h3><strong style="font-size:26px">' . number_format_i18n( $brief['open_inbox'] ) . '</strong></div>';
		if ( in_array( 'brief', $widgets, true ) ) echo '<div class="card"><h3>آماده ارسال</h3><strong style="font-size:26px">' . number_format_i18n( $brief['ready_to_ship'] ) . '</strong></div>';
		if ( in_array( 'profit', $widgets, true ) ) echo '<div class="card"><h3>پیش‌بینی سود</h3><strong>' . wp_kses_post( wc_price( (float) $forecast['forecast_profit'] ) ) . '</strong></div>';
		if ( in_array( 'profit', $widgets, true ) ) echo '<div class="card"><h3>سفارش زیان‌ده</h3><strong style="font-size:26px">' . number_format_i18n( $loss['count'] ) . '</strong></div>';
		echo '</div>';

		if ( in_array( 'forecast', $widgets, true ) ) {
			$advisor = Intelligence::shippingAdvisor();
			echo '<div class="card"><h2>Smart Shipping Advisor</h2>';
			if ( ! $advisor ) echo '<p>مورد مهمی برای اقدام فوری پیدا نشد.</p>';
			else { echo '<ul>'; foreach ( array_slice( $advisor, 0, 8 ) as $row ) echo '<li><strong>' . esc_html( $row['title'] ) . '</strong> — ' . esc_html( $row['message'] ) . ' <em>' . esc_html( $row['action'] ) . '</em></li>'; echo '</ul>'; }
			echo '</div>';
		}
		if ( in_array( 'packaging', $widgets, true ) ) {
			echo '<div class="card"><h2>پیش‌بینی بسته‌بندی</h2><ul>';
			foreach ( array_slice( Intelligence::packagingForecast(), 0, 8 ) as $row ) echo '<li>' . esc_html( $row['name'] ) . ': موجودی ' . (int) $row['stock'] . ' / پیش‌بینی ' . (int) $row['forecast_qty'] . ' / خرید پیشنهادی ' . (int) $row['reorder_qty'] . '</li>';
			echo '</ul></div>';
		}
		if ( in_array( 'bottlenecks', $widgets, true ) ) {
			echo '<div class="card"><h2>گلوگاه‌ها</h2><ul>';
			foreach ( Operations::bottlenecks() as $row ) echo '<li><strong>' . esc_html( $row['stage'] ) . '</strong> — ' . esc_html( $row['message'] ) . '</li>';
			echo '</ul></div>';
		}
		if ( in_array( 'operators', $widgets, true ) ) {
			echo '<div class="card"><h2>بار اپراتورها</h2><ul>';
			foreach ( array_slice( Operations::operatorWorkload(), 0, 8 ) as $row ) echo '<li>' . esc_html( $row['name'] ) . ' — ' . esc_html( (string) $row['orders_per_hour'] ) . ' سفارش/ساعت — سهم پیشنهادی ' . (int) $row['suggested_share'] . '</li>';
			echo '</ul></div>';
		}
		if ( in_array( 'returns', $widgets, true ) ) echo '<div class="card"><h2>Workflowهای برگشتی</h2><p>' . count( WorkflowManager::recent( 'return', 200 ) ) . ' پرونده اخیر</p></div>';
		if ( in_array( 'budget', $widgets, true ) ) { $m = Finance::monthSummary(); echo '<div class="card"><h2>Budget vs Actual</h2><p>بودجه ' . number_format_i18n( (int) $m['budget_irr'] ) . ' ریال — هزینه ' . number_format_i18n( (int) $m['total_cost'] ) . ' — اختلاف ' . number_format_i18n( (int) $m['budget_variance'] ) . '</p></div>'; }
		if ( in_array( 'anomalies', $widgets, true ) ) echo '<div class="card"><h2>ناهنجاری‌ها</h2><p>' . count( Intelligence::anomalies() ) . ' مورد در بازه تحلیل</p></div>';

		$plan = Operations::dailyPlan();
		echo '<div class="card"><h2>برنامه عملیات امروز</h2><table class="widefat striped"><thead><tr><th>اولویت</th><th>نوع</th><th>سفارش</th><th>کار</th></tr></thead><tbody>';
		foreach ( array_slice( $plan, 0, 15 ) as $row ) echo '<tr><td>' . (int) $row['priority'] . '</td><td>' . esc_html( $row['type'] ) . '</td><td>' . ( $row['order_id'] ? '#' . (int) $row['order_id'] : '—' ) . '</td><td>' . esc_html( $row['title'] ) . '</td></tr>';
		echo '</tbody></table></div>';
	}

	private static function intelligence(): void {
		$what = (float) ( $_GET['what_if'] ?? 10 );
		$wi = Intelligence::whatIf( $what );
		echo '<div class="card"><h2>What-if Analysis</h2><form method="get"><input type="hidden" name="page" value="' . self::PAGE . '"><input type="hidden" name="tab" value="intelligence"><label>تغییر کرایه (%) <input type="number" step="1" name="what_if" value="' . esc_attr( $what ) . '" style="width:90px"></label> <button class="button">محاسبه</button></form><p>سود قبل: ' . wp_kses_post( wc_price( (float) $wi['profit_before'] ) ) . ' — بعد: ' . wp_kses_post( wc_price( (float) $wi['profit_after'] ) ) . ' — تغییر سفارش‌های زیان‌ده: ' . (int) $wi['loss_orders_before'] . ' ← ' . (int) $wi['loss_orders_after'] . '</p></div>';

		$suggestions = Intelligence::ruleSuggestions();
		echo '<div class="card"><h2>Auto Rule Suggestions / Rule Simulator</h2><table class="widefat striped"><thead><tr><th>پیشنهاد</th><th>مقصد</th><th>افزایش</th><th>اثر شبیه‌سازی</th><th></th></tr></thead><tbody>';
		foreach ( $suggestions as $s ) { $sim = Intelligence::simulateSuggestion( $s ); echo '<tr><td>' . esc_html( $s['name'] ) . '<br><small>' . esc_html( $s['reason'] ) . '</small></td><td>' . esc_html( $s['province'] . ' / ' . $s['city'] ) . '</td><td>' . number_format_i18n( (int) $s['amount'] ) . ' ریال</td><td>' . number_format_i18n( (int) $sim['matched'] ) . ' سفارش / +' . wp_kses_post( wc_price( (float) $sim['delta'] ) ) . '</td><td><a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=hm_mahex_v25_apply_rule&id=' . rawurlencode( $s['id'] ) ), 'hm_mahex_v25_apply_rule' ) ) . '">اعمال Rule</a></td></tr>'; }
		echo '</tbody></table></div>';

		$conflicts = Intelligence::ruleConflicts();
		echo '<div class="card"><h2>Rule Conflict Detector</h2>';
		if ( ! $conflicts ) echo '<p>تداخل مهمی پیدا نشد.</p>';
		else { echo '<ul>'; foreach ( array_slice( $conflicts, 0, 30 ) as $c ) echo '<li><strong>' . esc_html( $c['a'] ) . '</strong> ↔ <strong>' . esc_html( $c['b'] ) . '</strong> — ' . esc_html( $c['reason'] ) . '</li>'; echo '</ul>'; }
		echo '</div>';

		echo '<div class="card"><h2>Packaging Forecast & Reorder</h2><table class="widefat striped"><thead><tr><th>بسته</th><th>موجودی</th><th>مصرف ۳۰ روز</th><th>پیش‌بینی</th><th>پیشنهاد خرید</th><th>پوشش روز</th></tr></thead><tbody>';
		foreach ( Intelligence::packagingForecast() as $p ) echo '<tr><td>' . esc_html( $p['name'] ) . '</td><td>' . (int) $p['stock'] . '</td><td>' . (int) $p['used30'] . '</td><td>' . (int) $p['forecast_qty'] . '</td><td>' . (int) $p['reorder_qty'] . '</td><td>' . esc_html( (string) $p['days_cover'] ) . '</td></tr>';
		echo '</tbody></table></div>';

		$audits = Intelligence::recentPackingAudits( 15 );
		echo '<div class="card"><h2>Packaging Efficiency / Oversized / Cost Optimizer</h2><table class="widefat striped"><thead><tr><th>سفارش</th><th>Score</th><th>بسته</th><th>Oversized</th><th>هزینه</th></tr></thead><tbody>';
		foreach ( $audits as $row ) echo '<tr><td>#' . esc_html( (string) $row['order_number'] ) . '</td><td>' . esc_html( (string) $row['score'] ) . '%</td><td>' . (int) $row['package_count'] . '</td><td>' . (int) $row['oversized_count'] . '</td><td>' . number_format_i18n( (int) $row['cost_irr'] ) . ' ریال</td></tr>';
		echo '</tbody></table></div>';

		$anomalies = Intelligence::anomalies(); $duplicates = Intelligence::duplicateShipments();
		echo '<div class="card"><h2>Anomaly / Duplicate / Pattern Alerts</h2><p>ناهنجاری: ' . count( $anomalies ) . ' — Tracking تکراری: ' . count( $duplicates ) . ' — الگوهای نیازمند بررسی: ' . count( Intelligence::suspiciousPatterns() ) . '</p><ul>';
		foreach ( array_slice( $anomalies, 0, 10 ) as $a ) echo '<li>#' . (int) $a['order_id'] . ' — ' . esc_html( $a['type'] ) . ' — ' . esc_html( $a['message'] ) . '</li>';
		foreach ( array_slice( $duplicates, 0, 10 ) as $d ) echo '<li>Tracking ' . esc_html( $d['tracking'] ) . ' در سفارش‌های ' . esc_html( $d['order_ids'] ) . '</li>';
		echo '</ul></div>';

		foreach ( array( 'Customer Shipping Score'=>Intelligence::customerScores(10),'City Performance Score'=>Intelligence::cityScores(10),'Product Shipping Score'=>Intelligence::productScores(10) ) as $title => $scores ) {
			echo '<div class="card"><h2>' . esc_html( $title ) . '</h2><pre style="direction:ltr;text-align:left;max-height:240px;overflow:auto">' . esc_html( wp_json_encode( $scores, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ) . '</pre></div>';
		}
	}

	private static function operations(): void {
		$rows = Operations::inbox( 200 );
		echo '<p><a class="button button-primary" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=hm_mahex_v25_scan_inbox' ), 'hm_mahex_v25_scan_inbox' ) ) . '">اسکن Exception Center</a></p>';
		echo '<div class="card"><h2>Operations Inbox / Smart Priority Queue</h2><table class="widefat striped"><thead><tr><th>Priority</th><th>نوع</th><th>سفارش</th><th>موضوع</th><th></th></tr></thead><tbody>';
		foreach ( $rows as $row ) echo '<tr><td>' . (int) $row['priority'] . '</td><td>' . esc_html( $row['item_type'] ) . '</td><td>' . ( $row['order_id'] ? '#' . (int) $row['order_id'] : '—' ) . '</td><td><strong>' . esc_html( $row['title'] ) . '</strong><br><small>' . esc_html( $row['details'] ) . '</small></td><td><a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=hm_mahex_v25_resolve_inbox&id=' . (int) $row['id'] ), 'hm_mahex_v25_resolve_inbox' ) ) . '">حل شد</a></td></tr>';
		echo '</tbody></table></div>';

		$shift = Operations::shiftPlan();
		echo '<div class="card"><h2>Shift Planner / Workload Balance / Productivity</h2><p>اپراتور پیشنهادی: <strong>' . (int) $shift['recommended_operators'] . '</strong> برای بار کاری ' . (int) $shift['workload'] . '</p><table class="widefat striped"><thead><tr><th>اپراتور</th><th>سفارش/ساعت</th><th>سهم پیشنهادی</th><th>خطا</th></tr></thead><tbody>';
		foreach ( $shift['operators'] as $row ) echo '<tr><td>' . esc_html( $row['name'] ) . '</td><td>' . esc_html( (string) $row['orders_per_hour'] ) . '</td><td>' . (int) $row['suggested_share'] . '</td><td>' . (int) $row['errors'] . '</td></tr>';
		echo '</tbody></table><h3>Packing Time Analytics</h3><pre style="direction:ltr;text-align:left">' . esc_html( wp_json_encode( Operations::packingTimeAnalytics(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ) . '</pre><h3>Bottleneck Detector</h3><ul>';
		foreach ( Operations::bottlenecks() as $b ) echo '<li><strong>' . esc_html( $b['stage'] ) . '</strong> — ' . esc_html( $b['message'] ) . '</li>';
		echo '</ul></div>';

		$eod = Operations::endOfDay();
		echo '<div class="card"><h2>Morning Brief / End-of-Day Report</h2><p>Morning: ' . esc_html( wp_json_encode( Operations::morningBrief(), JSON_UNESCAPED_UNICODE ) ) . '</p><p>End of day: ' . esc_html( wp_json_encode( $eod['metrics'], JSON_UNESCAPED_UNICODE ) ) . '</p></div>';

		self::workflowForm();
		$workflows = WorkflowManager::recent( '', 100 );
		echo '<div class="card"><h2>Return / Exchange / Reship / Damage / Evidence</h2><table class="widefat striped"><thead><tr><th>ID</th><th>نوع</th><th>سفارش</th><th>وضعیت</th><th>علت</th><th>هزینه</th><th>مدرک</th></tr></thead><tbody>';
		foreach ( $workflows as $w ) { $link = (int) $w['attachment_id'] ? wp_get_attachment_url( (int) $w['attachment_id'] ) : ''; echo '<tr><td>' . (int) $w['id'] . '</td><td>' . esc_html( $w['workflow_type'] ) . '</td><td>#' . (int) $w['order_id'] . '</td><td>' . esc_html( $w['status'] ) . '</td><td>' . esc_html( $w['reason'] ) . '</td><td>' . number_format_i18n( (int) $w['cost_irr'] ) . '</td><td>' . ( $link ? '<a href="' . esc_url( $link ) . '" target="_blank" rel="noopener">مشاهده</a>' : '—' ) . '</td></tr>'; }
		echo '</tbody></table></div>';
	}

	private static function workflowForm(): void {
		echo '<div class="card"><h2>پرونده عملیاتی جدید</h2><form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="hm_mahex_v25_save_workflow">';
		wp_nonce_field( 'hm_mahex_v25_save_workflow' );
		echo '<input name="order_id" type="number" min="1" required placeholder="شماره سفارش"> <select name="workflow_type"><option value="return">برگشت</option><option value="exchange">تعویض</option><option value="reship">ارسال مجدد</option><option value="damage">آسیب</option><option value="evidence">مدرک بسته‌بندی</option></select> <input name="reason" placeholder="علت"> <input name="cost" type="number" min="0" placeholder="هزینه ریال"> <input name="note" placeholder="یادداشت"> <input type="file" name="evidence_file" accept="image/*"> ';
		submit_button( 'ثبت پرونده', 'primary', '', false ); echo '</form></div>';
	}

	private static function finance(): void {
		$daily = Finance::dailySettlement();
		$month = Finance::monthSummary();
		echo '<div class="card"><h2>Daily Settlement</h2><p>امروز: درآمد ' . number_format_i18n( (int) $daily['revenue_irr'] ) . ' — هزینه ' . number_format_i18n( (int) $daily['cost_irr'] ) . ' — خالص ' . number_format_i18n( (int) $daily['net_irr'] ) . ' ریال</p></div>';
		echo '<div style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px"><div class="card"><h3>درآمد ارسال</h3><strong>' . number_format_i18n( (int) $month['shipping_revenue'] ) . ' ریال</strong></div><div class="card"><h3>درآمد بسته‌بندی</h3><strong>' . number_format_i18n( (int) $month['packaging_revenue'] ) . ' ریال</strong></div><div class="card"><h3>هزینه</h3><strong>' . number_format_i18n( (int) $month['total_cost'] ) . ' ریال</strong></div><div class="card"><h3>سود</h3><strong>' . number_format_i18n( (int) $month['profit'] ) . ' ریال</strong></div></div>';
		echo '<div class="card"><h2>Budget vs Actual / Monthly Closing</h2><p>بودجه: ' . number_format_i18n( (int) $month['budget_irr'] ) . ' — اختلاف بودجه با هزینه: ' . number_format_i18n( (int) $month['budget_variance'] ) . ' ریال</p><a class="button button-primary" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=hm_mahex_v25_close_month&period=' . rawurlencode( $month['period'] ) ), 'hm_mahex_v25_close_month' ) ) . '">بستن ماه ' . esc_html( $month['period'] ) . '</a></div>';
		echo '<div class="card"><h2>ثبت هزینه/درآمد دستی</h2><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="hm_mahex_v25_add_ledger">'; wp_nonce_field( 'hm_mahex_v25_add_ledger' ); echo '<input name="order_id" type="number" min="0" placeholder="سفارش (اختیاری)"> <input name="entry_type" value="operational_adjustment" placeholder="نوع"> <input name="amount" type="number" step="1" required placeholder="مبلغ ریال (+/-)"> <input name="cost_center" placeholder="Cost center"> <input name="note" placeholder="یادداشت"> '; submit_button( 'ثبت', 'secondary', '', false ); echo '</form></div>';
		echo '<div class="card"><h2>Cost Center</h2><table class="widefat striped"><thead><tr><th>مرکز</th><th>سفارش</th><th>درآمد</th><th>هزینه</th><th>خالص</th></tr></thead><tbody>';
		foreach ( Finance::costCenters() as $row ) echo '<tr><td>' . esc_html( $row['cost_center'] ) . '</td><td>' . (int) $row['orders'] . '</td><td>' . number_format_i18n( (int) $row['revenue'] ) . '</td><td>' . number_format_i18n( (int) $row['cost'] ) . '</td><td>' . number_format_i18n( (int) $row['net'] ) . '</td></tr>';
		echo '</tbody></table></div>';
	}

	private static function reports(): void {
		$defs = ReportCenter::definitions();
		echo '<div class="card"><h2>Advanced Report Builder</h2><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="hm_mahex_v25_save_report">'; wp_nonce_field( 'hm_mahex_v25_save_report' ); echo '<input name="name" required placeholder="نام گزارش"> <select name="dimension"><option value="city">شهر</option><option value="customer">مشتری</option><option value="status">وضعیت</option><option value="cost_center">Cost Center</option><option value="day">روز</option></select> <select name="metric"><option value="profit">سود</option><option value="orders">تعداد</option><option value="revenue">درآمد</option><option value="cost">هزینه</option><option value="returns">برگشتی</option></select> <input name="days" type="number" value="30" min="1" max="3650"> <label><input type="checkbox" name="scheduled" value="1"> Snapshot روزانه</label> '; submit_button( 'ذخیره گزارش', 'primary', '', false ); echo '</form></div>';
		$snapshots = ReportCenter::snapshots( 10 );
		echo '<div class="card"><h2>Scheduled Local Reports</h2><p>' . count( $snapshots ) . ' Snapshot اخیر موجود است.</p><ul>'; foreach ( $snapshots as $x ) echo '<li>' . esc_html( $x['title'] ) . ' — ' . esc_html( $x['created_at'] ) . '</li>'; echo '</ul></div>';
		foreach ( $defs as $def ) { $rows = ReportCenter::run( $def ); echo '<div class="card"><h3>' . esc_html( $def['name'] ) . '</h3><pre style="direction:ltr;text-align:left;max-height:300px;overflow:auto">' . esc_html( wp_json_encode( array_slice( $rows, 0, 30 ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ) . '</pre></div>'; }
		echo '<div class="card"><h2>Dashboard Builder</h2><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="hm_mahex_v25_save_dashboard">'; wp_nonce_field( 'hm_mahex_v25_save_dashboard' ); $current = ReportCenter::dashboardWidgets(); foreach ( array( 'brief'=>'خلاصه روز','profit'=>'سود','inbox'=>'Inbox','forecast'=>'پیش‌بینی','packaging'=>'بسته‌بندی','operators'=>'اپراتورها','returns'=>'برگشتی','bottlenecks'=>'گلوگاه','budget'=>'بودجه','anomalies'=>'ناهنجاری' ) as $key => $label ) echo '<label style="display:inline-block;margin:5px 10px"><input type="checkbox" name="widgets[]" value="' . esc_attr( $key ) . '" ' . checked( in_array( $key, $current, true ), true, false ) . '> ' . esc_html( $label ) . '</label>'; submit_button( 'ذخیره داشبورد' ); echo '</form></div>';
	}

	private static function system(): void {
		$s = Config::all(); $migration = MigrationManager::status(); $archive = ArchiveManager::stats(); $guard = ReleaseGuard::checks();
		echo '<div class="card"><h2>تنظیمات 2.5</h2><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="hm_mahex_v25_save_settings">'; wp_nonce_field( 'hm_mahex_v25_save_settings' );
		foreach ( array( 'intelligence_enabled'=>'Intelligence','automation_enabled'=>'Automation','finance_enabled'=>'Finance','archive_enabled'=>'Archive','scheduled_reports_enabled'=>'Scheduled reports','release_guard_enabled'=>'Release Guard','packing_evidence_enabled'=>'Packing Evidence','operator_balance_enabled'=>'Operator balance' ) as $key => $label ) echo '<label style="display:block;margin:6px"><input type="checkbox" name="settings[' . esc_attr( $key ) . ']" value="1" ' . checked( ! empty( $s[ $key ] ), true, false ) . '> ' . esc_html( $label ) . '</label>';
		foreach ( array( 'forecast_days'=>'روز پیش‌بینی','history_days'=>'روز تاریخچه','return_risk_threshold'=>'آستانه ریسک برگشت','anomaly_multiplier'=>'ضریب Anomaly','sla_hours'=>'SLA ساعت','daily_plan_limit'=>'حد برنامه روز','monthly_budget_irr'=>'بودجه ماهانه ریال','archive_after_days'=>'آرشیو بعد از روز','performance_budget_ms'=>'بودجه Performance ms' ) as $key => $label ) echo '<label style="display:inline-block;margin:6px">' . esc_html( $label ) . ' <input type="number" name="settings[' . esc_attr( $key ) . ']" value="' . (int) $s[ $key ] . '"></label>';
		submit_button( 'ذخیره' ); echo '</form></div>';
		echo '<div class="card"><h2>Zero-downtime Migration / Archive / Partition Strategy</h2><p>Backfill cursor: ' . (int) $migration['cursor'] . ' — ' . ( $migration['done'] ? 'تمام شده' : 'در حال انجام' ) . ' — آرشیو: ' . (int) $archive['count'] . ' ردیف</p><a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=hm_mahex_v25_backfill_now' ), 'hm_mahex_v25_backfill_now' ) ) . '">اجرای Batch مهاجرت</a> <a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=hm_mahex_v25_archive_now' ), 'hm_mahex_v25_archive_now' ) ) . '">Archive Batch</a><pre style="direction:ltr;text-align:left">' . esc_html( wp_json_encode( ArchiveManager::partitionStrategy(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ) . '</pre></div>';
		echo '<div class="card"><h2>Performance Budget</h2><table class="widefat striped"><thead><tr><th>گروه</th><th>P95</th><th>Budget</th><th>نتیجه</th></tr></thead><tbody>'; foreach ( PerformanceBudget::evaluate() as $row ) echo '<tr><td>' . esc_html( $row['key'] ) . '</td><td>' . esc_html( (string) $row['p95'] ) . 'ms</td><td>' . (int) $row['budget_ms'] . 'ms</td><td>' . ( $row['pass'] ? 'PASS' : 'OVER' ) . '</td></tr>'; echo '</tbody></table></div>';
		echo '<div class="card"><h2>Release Guard</h2><ul>'; foreach ( $guard as $row ) echo '<li>' . ( $row['ok'] ? '✅' : '❌' ) . ' <strong>' . esc_html( $row['name'] ) . '</strong> ' . esc_html( $row['details'] ) . '</li>'; echo '</ul></div>';
	}

	public static function saveSettings(): void { self::guard( 'hm_mahex_v25_save_settings' ); update_option( Config::OPTION, Config::sanitize( $_POST['settings'] ?? array() ), false ); self::back( 'system' ); }
	public static function scanInbox(): void { self::guard( 'hm_mahex_v25_scan_inbox' ); Operations::scanInbox(); self::back( 'operations' ); }
	public static function resolveInbox(): void { self::guard( 'hm_mahex_v25_resolve_inbox' ); Operations::resolve( absint( $_GET['id'] ?? 0 ) ); self::back( 'operations' ); }
	public static function applyRule(): void { self::guard( 'hm_mahex_v25_apply_rule' ); Intelligence::applySuggestedRule( sanitize_key( (string) ( $_GET['id'] ?? '' ) ) ); self::back( 'intelligence' ); }
	public static function saveWorkflow(): void { self::guard( 'hm_mahex_v25_save_workflow' ); $id = absint( $_POST['order_id'] ?? 0 ); $type = sanitize_key( (string) ( $_POST['workflow_type'] ?? 'return' ) ); $attachment = Config::bool( 'packing_evidence_enabled', true ) ? WorkflowManager::handleEvidenceUpload() : 0; WorkflowManager::ensure( $id, $type, sanitize_text_field( (string) ( $_POST['reason'] ?? '' ) ), sanitize_textarea_field( (string) ( $_POST['note'] ?? '' ) ), max( 0, (float) ( $_POST['cost'] ?? 0 ) ), $attachment ); self::back( 'operations' ); }
	public static function updateWorkflow(): void { self::guard( 'hm_mahex_v25_update_workflow' ); WorkflowManager::update( absint( $_POST['id'] ?? 0 ), sanitize_key( (string) ( $_POST['status'] ?? 'open' ) ), sanitize_textarea_field( (string) ( $_POST['note'] ?? '' ) ), max( 0, (float) ( $_POST['cost'] ?? 0 ) ) ); self::back( 'operations' ); }
	public static function addLedger(): void { self::guard( 'hm_mahex_v25_add_ledger' ); Finance::add( absint( $_POST['order_id'] ?? 0 ), sanitize_key( (string) ( $_POST['entry_type'] ?? 'adjustment' ) ), (float) ( $_POST['amount'] ?? 0 ), sanitize_text_field( (string) ( $_POST['cost_center'] ?? '' ) ), sanitize_textarea_field( (string) ( $_POST['note'] ?? '' ) ) ); self::back( 'finance' ); }
	public static function closeMonth(): void { self::guard( 'hm_mahex_v25_close_month' ); Finance::closeMonth( sanitize_text_field( (string) ( $_GET['period'] ?? '' ) ) ); self::back( 'finance' ); }
	public static function saveReport(): void { self::guard( 'hm_mahex_v25_save_report' ); ReportCenter::saveDefinition( wp_unslash( $_POST ) ); self::back( 'reports' ); }
	public static function saveDashboard(): void { self::guard( 'hm_mahex_v25_save_dashboard' ); ReportCenter::saveDashboard( $_POST['widgets'] ?? array() ); self::back( 'reports' ); }
	public static function archiveNow(): void { self::guard( 'hm_mahex_v25_archive_now' ); ArchiveManager::runBatch( 500 ); self::back( 'system' ); }
	public static function backfillNow(): void { self::guard( 'hm_mahex_v25_backfill_now' ); MigrationManager::backfill( 250 ); self::back( 'system' ); }
	private static function cap(): void { if ( ! current_user_can( 'manage_woocommerce' ) ) wp_die( 'Forbidden', '', array( 'response' => 403 ) ); }
	private static function guard( string $nonce ): void { self::cap(); check_admin_referer( $nonce ); }
	private static function back( string $tab ): void { wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE . '&tab=' . $tab ) ); exit; }
}
