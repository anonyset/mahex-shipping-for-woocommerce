<?php

namespace HoseinMomeni\MahexWoo\Rates\Admin;

use HoseinMomeni\MahexWoo\Domain\Money;
use HoseinMomeni\MahexWoo\Rates\PricingSettings;
use InvalidArgumentException;

/** Secure admin UI/handlers for destination-specific pricing rules. */
final class RateRulesAdmin {
	private const CAPABILITY = 'manage_woocommerce';
	private const PAGE_SLUG  = 'hm-mahex-rate-rules';

	public static function register(): void {
		add_action( 'admin_menu', array( self::class, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_assets' ) );
		add_action( 'admin_post_hm_mahex_save_rate_rule', array( self::class, 'save' ) );
		add_action( 'admin_post_hm_mahex_delete_rate_rule', array( self::class, 'delete' ) );
		add_action( 'admin_post_hm_mahex_simulate_rate', array( self::class, 'simulate' ) );
	}

	public static function add_menu(): void {
		add_submenu_page( 'hm-mahex', 'قوانین قیمت — ماهکس', 'قوانین قیمت', self::CAPABILITY, self::PAGE_SLUG, array( self::class, 'render' ) );
	}

	public static function enqueue_assets( string $hook ): void {
		if ( false === strpos( $hook, self::PAGE_SLUG ) ) {
			return;
		}
		wp_enqueue_style( 'hm-mahex-admin', plugins_url( 'assets/admin/dashboard.css', HM_MAHEX_FILE ), array(), HM_MAHEX_VERSION );
		wp_enqueue_style( 'hm-mahex-rules', plugins_url( 'assets/admin/rate-rules.css', HM_MAHEX_FILE ), array( 'hm-mahex-admin' ), HM_MAHEX_VERSION );
	}

	public static function render(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'دسترسی غیرمجاز است.', 'mahex-shipping-for-woocommerce' ), '', array( 'response' => 403 ) );
		}
		$manager    = self::manager();
		$rules      = $manager->all();
		$notice     = isset( $_GET['hm_notice'] ) ? sanitize_key( wp_unslash( $_GET['hm_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$simulation = get_transient( 'hm_mahex_rate_simulation_' . get_current_user_id() );
		$pricing    = PricingSettings::normalizeStored( get_option( PricingSettings::OPTION_NAME, array() ) );
		?>
		<div class="wrap hm-mahex hmx-rules" dir="rtl">
			<h1>قوانین پیشرفته قیمت ماهکس</h1>
			<p>قانون با اولویت کمتر زودتر بررسی می‌شود و اولین قانون منطبق روی استان/شهر اعمال خواهد شد. مبالغ قوانین در این نسخه به ریال هستند.</p>
			<?php self::notice( $notice ); ?>
			<div class="hmx-rules-layout">
				<section class="hmx-panel">
					<h2>قانون جدید</h2>
					<?php self::rule_form(); ?>
				</section>
				<section class="hmx-panel">
					<h2>شبیه‌ساز قیمت</h2>
					<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" class="hmx-rules-grid">
						<input type="hidden" name="action" value="hm_mahex_simulate_rate">
						<?php wp_nonce_field( 'hm_mahex_simulate_rate' ); ?>
						<?php self::input( 'simulation[base]', 'کرایه پایه (ریال)', (string) $pricing['manual_base_cost'], 'number' ); ?>
						<?php self::input( 'simulation[per_kg]', 'هر کیلو (ریال)', (string) $pricing['manual_cost_per_kg'], 'number' ); ?>
						<?php self::input( 'simulation[weight_grams]', 'وزن قابل پرداخت (گرم)', '2000', 'number' ); ?>
						<?php self::input( 'simulation[province]', 'استان', 'قم' ); ?>
						<?php self::input( 'simulation[city]', 'شهر', 'قم' ); ?>
						<input type="hidden" name="simulation[currency]" value="IRR">
						<div><?php submit_button( 'محاسبه آزمایشی', 'secondary', 'submit', false ); ?></div>
					</form>
					<?php if ( is_array( $simulation ) ) : ?>
						<div class="hmx-rule-result"><strong>نتیجه:</strong> <?php echo esc_html( number_format_i18n( (int) ( $simulation['total'] ?? 0 ) ) ); ?> ریال
							<span>· وزن صورتحساب: <?php echo esc_html( number_format_i18n( (int) ( $simulation['billable_kilograms'] ?? 0 ) ) ); ?> کیلو</span>
							<span>· قانون اعمال‌شده: <?php echo esc_html( (string) ( $simulation['applied_rule_id'] ?? 'بدون قانون' ) ); ?></span>
						</div>
					<?php endif; ?>
				</section>
			</div>

			<section class="hmx-panel hmx-rules-list">
				<div class="hmx-panel-head"><div><h2>قوانین ذخیره‌شده</h2><p><?php echo esc_html( number_format_i18n( count( $rules ) ) ); ?> قانون</p></div></div>
				<?php if ( array() === $rules ) : ?><div class="hmx-empty">هنوز قانونی ساخته نشده است.</div><?php endif; ?>
				<?php foreach ( $rules as $rule ) : ?>
					<article class="hmx-rule-card">
						<?php self::rule_form( $rule ); ?>
						<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" onsubmit="return window.confirm('این قانون حذف شود؟');">
							<input type="hidden" name="action" value="hm_mahex_delete_rate_rule">
							<input type="hidden" name="rule_id" value="<?php echo esc_attr( $rule->id ); ?>">
							<?php wp_nonce_field( 'hm_mahex_delete_rate_rule_' . $rule->id ); ?>
							<?php submit_button( 'حذف قانون', 'delete', 'submit', false ); ?>
						</form>
					</article>
				<?php endforeach; ?>
			</section>
		</div>
		<?php
	}

	public static function save(): void {
		self::guard( 'hm_mahex_save_rate_rule' );
		$raw = isset( $_POST['rule'] ) && is_array( $_POST['rule'] ) ? wp_unslash( $_POST['rule'] ) : array();
		$raw = self::sanitizeRuleInput( $raw );
		try {
			$manager = self::manager();
			$id      = (string) ( $raw['id'] ?? '' );
			if ( '' !== $id && null !== ( new WordPressOptionRateRuleRepository() )->find( $id ) ) {
				$manager->update( $id, $raw );
			} else {
				if ( '' === $id ) {
					$raw['id'] = 'rule-' . strtolower( wp_generate_password( 12, false, false ) );
				}
				$manager->create( $raw );
			}
			self::redirect( 'saved' );
		} catch ( InvalidArgumentException ) {
			self::redirect( 'invalid' );
		}
	}

	public static function delete(): void {
		$id = isset( $_POST['rule_id'] ) ? sanitize_key( wp_unslash( $_POST['rule_id'] ) ) : '';
		self::guard( 'hm_mahex_delete_rate_rule_' . $id );
		self::manager()->delete( $id );
		self::redirect( 'deleted' );
	}

	public static function simulate(): void {
		self::guard( 'hm_mahex_simulate_rate' );
		$input = isset( $_POST['simulation'] ) && is_array( $_POST['simulation'] ) ? wp_unslash( $_POST['simulation'] ) : array();
		$input = array(
			'base'         => isset( $input['base'] ) ? absint( $input['base'] ) : 0,
			'per_kg'       => isset( $input['per_kg'] ) ? absint( $input['per_kg'] ) : 0,
			'weight_grams' => isset( $input['weight_grams'] ) ? absint( $input['weight_grams'] ) : 0,
			'currency'     => isset( $input['currency'] ) ? strtoupper( sanitize_key( $input['currency'] ) ) : 'IRR',
			'province'     => isset( $input['province'] ) ? sanitize_text_field( $input['province'] ) : '',
			'city'         => isset( $input['city'] ) ? sanitize_text_field( $input['city'] ) : '',
		);
		try {
			$simulation = ( new RateDryRunSimulator( self::manager() ) )->simulate(
				new Money( $input['base'], $input['currency'] ),
				new Money( $input['per_kg'], $input['currency'] ),
				$input['weight_grams'],
				$input['province'],
				$input['city']
			);
			set_transient( 'hm_mahex_rate_simulation_' . get_current_user_id(), $simulation->toArray(), MINUTE_IN_SECONDS );
			self::redirect( 'simulated' );
		} catch ( InvalidArgumentException ) {
			self::redirect( 'invalid' );
		}
	}

	private static function manager(): RateRuleManager {
		return new RateRuleManager( new WordPressOptionRateRuleRepository() );
	}

	/** @param array<string,mixed> $raw @return array<string,mixed> */
	private static function sanitizeRuleInput( array $raw ): array {
		return array(
			'id'       => isset( $raw['id'] ) ? sanitize_key( $raw['id'] ) : '',
			'name'     => isset( $raw['name'] ) ? sanitize_text_field( $raw['name'] ) : '',
			'priority' => isset( $raw['priority'] ) ? absint( $raw['priority'] ) : 0,
			'mode'     => isset( $raw['mode'] ) ? sanitize_key( $raw['mode'] ) : '',
			'amount'   => isset( $raw['amount'] ) ? absint( $raw['amount'] ) : 0,
			'currency' => 'IRR',
			'province' => isset( $raw['province'] ) ? sanitize_text_field( $raw['province'] ) : '',
			'city'     => isset( $raw['city'] ) ? sanitize_text_field( $raw['city'] ) : '',
			'active'   => isset( $raw['active'] ) && '0' !== (string) $raw['active'],
		);
	}

	private static function rule_form( ?RateRuleRecord $rule = null ): void {
		$id       = $rule?->id ?? '';
		$name     = $rule?->name ?? '';
		$priority = $rule?->priority ?? 10;
		$mode     = $rule?->mode ?? 'surcharge';
		$amount   = $rule?->amount ?? 0;
		$province = $rule?->province ?? '';
		$city     = $rule?->city ?? '';
		$active   = null === $rule ? true : $rule->active;
		?>
		<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" class="hmx-rules-grid">
			<input type="hidden" name="action" value="hm_mahex_save_rate_rule">
			<input type="hidden" name="rule[id]" value="<?php echo esc_attr( $id ); ?>">
			<?php wp_nonce_field( 'hm_mahex_save_rate_rule' ); ?>
			<?php self::input( 'rule[name]', 'نام قانون', $name ?: 'نرخ ویژه مقصد' ); ?>
			<?php self::input( 'rule[priority]', 'اولویت', (string) $priority, 'number' ); ?>
			<label><span>نوع قانون</span><select name="rule[mode]"><option value="surcharge" <?php selected( $mode, 'surcharge' ); ?>>افزایش روی نرخ</option><option value="fixed" <?php selected( $mode, 'fixed' ); ?>>نرخ نهایی ثابت</option></select></label>
			<?php self::input( 'rule[amount]', 'مبلغ (ریال)', (string) $amount, 'number' ); ?>
			<?php self::input( 'rule[province]', 'استان (خالی = همه)', $province ); ?>
			<?php self::input( 'rule[city]', 'شهر (خالی = همه شهرهای استان)', $city ); ?>
			<label class="hmx-rule-check"><input type="checkbox" name="rule[active]" value="1" <?php checked( $active ); ?>><span>فعال</span></label>
			<div><?php submit_button( null === $rule ? 'افزودن قانون' : 'ذخیره تغییرات', null === $rule ? 'primary' : 'secondary', 'submit', false ); ?></div>
		</form>
		<?php
	}

	private static function input( string $name, string $label, string $value, string $type = 'text' ): void {
		$attrs = 'number' === $type ? ' min="0" step="1" inputmode="numeric"' : ' maxlength="100"';
		echo '<label><span>' . esc_html( $label ) . '</span><input type="' . esc_attr( $type ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"' . $attrs . '></label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	private static function guard( string $action ): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'دسترسی غیرمجاز است.', 'mahex-shipping-for-woocommerce' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( $action );
	}

	private static function redirect( string $notice ): void {
		wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE_SLUG, 'hm_notice' => sanitize_key( $notice ) ), admin_url( 'admin.php' ) ) );
		exit;
	}

	private static function notice( string $notice ): void {
		$messages = array(
			'saved' => array( 'success', 'قانون قیمت ذخیره شد و در محاسبه Checkout قابل استفاده است.' ),
			'deleted' => array( 'success', 'قانون قیمت حذف شد.' ),
			'simulated' => array( 'info', 'شبیه‌سازی انجام شد.' ),
			'invalid' => array( 'error', 'اطلاعات قانون یا شبیه‌سازی معتبر نیست.' ),
		);
		if ( isset( $messages[ $notice ] ) ) {
			printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $messages[ $notice ][0] ), esc_html( $messages[ $notice ][1] ) );
		}
	}
}
