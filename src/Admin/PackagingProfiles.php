<?php

namespace HoseinMomeni\MahexWoo\Admin;

use HoseinMomeni\MahexWoo\Products\PackagingProfile;
use InvalidArgumentException;

defined( 'ABSPATH' ) || exit;

final class PackagingProfiles {
	public const OPTION_NAME = 'hm_mahex_packaging_profiles';
	private const CAPABILITY = 'read';
	private const PAGE_SLUG  = 'hm-mahex-packaging-profiles';

	public static function register(): void {
		add_action( 'admin_menu', array( self::class, 'add_menu' ) );
		add_action( 'admin_post_hm_mahex_save_packaging_profile', array( self::class, 'save' ) );
		add_action( 'admin_post_hm_mahex_delete_packaging_profile', array( self::class, 'delete' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_assets' ) );
	}

	public static function add_menu(): void {
		add_submenu_page(
			'woocommerce',
			esc_html__( 'پروفایل‌های بسته‌بندی ماهکس', 'mahex-shipping-for-woocommerce' ),
			esc_html__( 'بسته‌بندی ماهکس', 'mahex-shipping-for-woocommerce' ),
			self::CAPABILITY,
			self::PAGE_SLUG,
			array( self::class, 'render' )
		);
	}

	public static function enqueue_assets( string $hook ): void {
		if ( 'woocommerce_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}
		wp_enqueue_style( 'hm-mahex-admin', plugins_url( 'assets/admin/dashboard.css', HM_MAHEX_FILE ), array(), HM_MAHEX_VERSION );
		wp_enqueue_style( 'hm-mahex-packaging', plugins_url( 'assets/admin/packaging.css', HM_MAHEX_FILE ), array( 'hm-mahex-admin' ), HM_MAHEX_VERSION );
		wp_enqueue_script( 'hm-mahex-packaging', plugins_url( 'assets/admin/packaging.js', HM_MAHEX_FILE ), array(), HM_MAHEX_VERSION, true );
	}

	/** @return array<string,array<string,mixed>> */
	public static function all(): array {
		$profiles = get_option( self::OPTION_NAME, array() );
		return is_array( $profiles ) ? $profiles : array();
	}

	public static function render(): void {
		if ( ! self::can_manage_profiles() ) {
			wp_die( esc_html__( 'شما اجازه دسترسی به این صفحه را ندارید.', 'mahex-shipping-for-woocommerce' ) );
		}
		$profiles = self::all();
		?>
		<div class="wrap hm-mahex hm-mahex-packaging" dir="rtl">
			<section class="hmx-reference-hero hm-mahex-packaging__hero">
				<div class="hmx-reference-brand"><img src="<?php echo esc_url( plugins_url( 'assets/brand/mahex-reference.png', HM_MAHEX_FILE ) ); ?>" alt="Mahex"><div><span><?php esc_html_e( 'مدیریت بسته‌بندی فروشگاه', 'mahex-shipping-for-woocommerce' ); ?></span><h2><?php esc_html_e( 'پروفایل‌های بسته‌بندی ماهکس', 'mahex-shipping-for-woocommerce' ); ?></h2><p><?php esc_html_e( 'ابعاد و وزن استاندارد مرسوله‌ها را یک‌بار تعریف کنید.', 'mahex-shipping-for-woocommerce' ); ?></p></div></div>
				<div class="hmx-feature-badge"><strong><?php echo esc_html( number_format_i18n( count( $profiles ) ) ); ?></strong><span><?php esc_html_e( 'پروفایل ذخیره‌شده', 'mahex-shipping-for-woocommerce' ); ?></span><small><?php esc_html_e( 'آماده استفاده برای محصول', 'mahex-shipping-for-woocommerce' ); ?></small></div>
				<div class="hmx-reference-kpis"><span><b>mm</b> <?php esc_html_e( 'ابعاد به میلی‌متر', 'mahex-shipping-for-woocommerce' ); ?></span><span><b>g</b> <?php esc_html_e( 'وزن به گرم', 'mahex-shipping-for-woocommerce' ); ?></span><span><b>RTL</b> <?php esc_html_e( 'رابط فارسی', 'mahex-shipping-for-woocommerce' ); ?></span></div>
			</section>
			<div class="hm-mahex-packaging__intro"><h1><?php esc_html_e( 'مدیریت بسته‌ها', 'mahex-shipping-for-woocommerce' ); ?></h1><p><?php esc_html_e( 'ابعاد را به میلی‌متر و وزن را به گرم وارد کنید. این اطلاعات مستقیماً برای ساخت بسته محلی استفاده می‌شوند.', 'mahex-shipping-for-woocommerce' ); ?></p></div>
			<?php self::render_notice(); ?>
			<div class="hm-mahex-packaging__layout">
				<section class="hm-mahex-card">
					<h2><?php esc_html_e( 'پروفایل جدید', 'mahex-shipping-for-woocommerce' ); ?></h2>
					<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
						<input type="hidden" name="action" value="hm_mahex_save_packaging_profile">
						<?php wp_nonce_field( 'hm_mahex_save_packaging_profile' ); ?>
						<?php self::profile_fields(); ?>
						<?php submit_button( __( 'ذخیره پروفایل', 'mahex-shipping-for-woocommerce' ) ); ?>
					</form>
				</section>
				<section class="hm-mahex-card hm-mahex-card--list">
					<h2><?php esc_html_e( 'پروفایل‌های ذخیره‌شده', 'mahex-shipping-for-woocommerce' ); ?></h2>
					<?php if ( empty( $profiles ) ) : ?>
						<p><?php esc_html_e( 'هنوز پروفایلی ساخته نشده است.', 'mahex-shipping-for-woocommerce' ); ?></p>
					<?php endif; ?>
					<?php foreach ( $profiles as $profile ) : self::profile_card( $profile ); endforeach; ?>
				</section>
			</div>
		</div>
		<?php
	}

	private static function profile_fields( array $profile = array() ): void {
		$fields = array(
			'name'           => __( 'نام پروفایل', 'mahex-shipping-for-woocommerce' ),
			'length_mm'      => __( 'طول (میلی‌متر)', 'mahex-shipping-for-woocommerce' ),
			'width_mm'       => __( 'عرض (میلی‌متر)', 'mahex-shipping-for-woocommerce' ),
			'height_mm'      => __( 'ارتفاع (میلی‌متر)', 'mahex-shipping-for-woocommerce' ),
			'empty_weight_g' => __( 'وزن خالی بسته (گرم)', 'mahex-shipping-for-woocommerce' ),
			'max_weight_g'   => __( 'حداکثر وزن بسته (گرم)', 'mahex-shipping-for-woocommerce' ),
			'max_items'      => __( 'حداکثر تعداد کالا در بسته (۰ = نامحدود)', 'mahex-shipping-for-woocommerce' ),
			'stock'          => __( 'موجودی بسته', 'mahex-shipping-for-woocommerce' ),
			'min_stock'      => __( 'حداقل موجودی هشدار', 'mahex-shipping-for-woocommerce' ),
			'unit_cost_irr'  => __( 'بهای تمام‌شده هر بسته (ریال)', 'mahex-shipping-for-woocommerce' ),
		);
		if ( isset( $profile['id'] ) ) {
			echo '<input type="hidden" name="profile[id]" value="' . esc_attr( $profile['id'] ) . '">';
		}
		$type = (string) ( $profile['type'] ?? 'custom' );
		echo '<p><label><span>' . esc_html__( 'نوع بسته', 'mahex-shipping-for-woocommerce' ) . '</span><select name="profile[type]">';
		foreach ( array( 'envelope' => 'پاکت', 'carton' => 'کارتن', 'box' => 'جعبه', 'custom' => 'سفارشی' ) as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '" ' . selected( $type, $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></label></p>';
		foreach ( $fields as $key => $label ) {
			$type = 'name' === $key ? 'text' : 'number';
			$min  = in_array( $key, array( 'empty_weight_g', 'max_items', 'stock', 'min_stock', 'unit_cost_irr' ), true ) ? '0' : '1';
			echo '<p><label><span>' . esc_html( $label ) . '</span><input required type="' . esc_attr( $type ) . '" name="profile[' . esc_attr( $key ) . ']" value="' . esc_attr( $profile[ $key ] ?? '' ) . '"' . ( 'number' === $type ? ' min="' . esc_attr( $min ) . '" step="1"' : ' maxlength="100"' ) . '></label></p>';
		}
	}

	private static function profile_card( array $profile ): void {
		?>
		<article class="hm-mahex-profile">
			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
				<input type="hidden" name="action" value="hm_mahex_save_packaging_profile">
				<?php wp_nonce_field( 'hm_mahex_save_packaging_profile' ); ?>
				<?php self::profile_fields( $profile ); ?>
				<?php submit_button( __( 'به‌روزرسانی', 'mahex-shipping-for-woocommerce' ), 'secondary', 'submit', false ); ?>
			</form>
			<form class="hm-mahex-delete" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" data-confirm="<?php esc_attr_e( 'این پروفایل حذف شود؟', 'mahex-shipping-for-woocommerce' ); ?>">
				<input type="hidden" name="action" value="hm_mahex_delete_packaging_profile">
				<input type="hidden" name="profile_id" value="<?php echo esc_attr( $profile['id'] ); ?>">
				<?php wp_nonce_field( 'hm_mahex_delete_packaging_profile_' . $profile['id'] ); ?>
				<?php submit_button( __( 'حذف', 'mahex-shipping-for-woocommerce' ), 'delete', 'submit', false ); ?>
			</form>
			<?php if ( ! empty( $profile['price_history'] ) && is_array( $profile['price_history'] ) ) : ?>
			<details class="hm-mahex-price-history"><summary><?php esc_html_e( 'تاریخچه بهای بسته', 'mahex-shipping-for-woocommerce' ); ?></summary><ul><?php foreach ( array_reverse( array_slice( $profile['price_history'], -10 ) ) as $row ) : ?><li><span dir="ltr"><?php echo esc_html( (string) ( $row['at'] ?? '' ) ); ?></span> — <?php echo esc_html( number_format_i18n( (int) ( $row['cost_irr'] ?? 0 ) ) ); ?> ریال</li><?php endforeach; ?></ul></details>
			<?php endif; ?>
		</article>
		<?php
	}

	public static function save(): void {
		self::guard( 'hm_mahex_save_packaging_profile' );
		$raw = isset( $_POST['profile'] ) && is_array( $_POST['profile'] ) ? wp_unslash( $_POST['profile'] ) : array();
		try {
			$profiles = self::all();
			$old = isset( $raw['id'], $profiles[ sanitize_key( (string) $raw['id'] ) ] ) && is_array( $profiles[ sanitize_key( (string) $raw['id'] ) ] ) ? $profiles[ sanitize_key( (string) $raw['id'] ) ] : array();
			$raw['price_history'] = is_array( $old['price_history'] ?? null ) ? $old['price_history'] : array();
			$profile = PackagingProfile::fromInput( $raw );
			$oldCost = isset( $old['unit_cost_irr'] ) ? (int) $old['unit_cost_irr'] : null;
			if ( null === $oldCost || $oldCost !== (int) $profile['unit_cost_irr'] ) {
				$profile['price_history'][] = array( 'at' => gmdate( DATE_ATOM ), 'cost_irr' => (int) $profile['unit_cost_irr'] );
				$profile['price_history'] = array_slice( $profile['price_history'], -50 );
			}
			$profiles[ $profile['id'] ] = $profile;
			update_option( self::OPTION_NAME, $profiles, false );
			self::redirect( 'saved' );
		} catch ( InvalidArgumentException $exception ) {
			self::redirect( 'invalid' );
		}
	}

	public static function delete(): void {
		$id = isset( $_POST['profile_id'] ) ? sanitize_key( wp_unslash( $_POST['profile_id'] ) ) : '';
		self::guard( 'hm_mahex_delete_packaging_profile_' . $id );
		$profiles = self::all();
		unset( $profiles[ $id ] );
		update_option( self::OPTION_NAME, $profiles, false );
		self::redirect( 'deleted' );
	}

	private static function can_manage_profiles(): bool {
		return current_user_can( 'manage_woocommerce' ) || current_user_can( 'hm_mahex_manage_inventory' ) || current_user_can( 'hm_mahex_pack_shipments' );
	}

	private static function guard( string $action ): void {
		if ( ! self::can_manage_profiles() ) {
			wp_die(
				esc_html__( 'دسترسی غیرمجاز است.', 'mahex-shipping-for-woocommerce' ),
				esc_html__( 'خطای دسترسی', 'mahex-shipping-for-woocommerce' ),
				array( 'response' => 403 )
			);
		}
		check_admin_referer( $action );
	}

	private static function redirect( string $notice ): void {
		wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE_SLUG, 'hm_notice' => $notice ), admin_url( 'admin.php' ) ) );
		exit;
	}

	private static function render_notice(): void {
		$notice = isset( $_GET['hm_notice'] ) ? sanitize_key( wp_unslash( $_GET['hm_notice'] ) ) : '';
		$messages = array(
			'saved'   => __( 'پروفایل بسته‌بندی ذخیره شد.', 'mahex-shipping-for-woocommerce' ),
			'deleted' => __( 'پروفایل بسته‌بندی حذف شد.', 'mahex-shipping-for-woocommerce' ),
			'invalid' => __( 'مقادیر پروفایل معتبر نیستند.', 'mahex-shipping-for-woocommerce' ),
		);
		if ( isset( $messages[ $notice ] ) ) {
			echo '<div class="notice ' . ( 'invalid' === $notice ? 'notice-error' : 'notice-success' ) . ' is-dismissible"><p>' . esc_html( $messages[ $notice ] ) . '</p></div>';
		}
	}
}
