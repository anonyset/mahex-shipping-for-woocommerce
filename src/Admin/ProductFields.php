<?php

namespace HoseinMomeni\MahexWoo\Admin;

defined( 'ABSPATH' ) || exit;

final class ProductFields {
	private const NONCE_ACTION = 'hm_mahex_save_product_shipping';
	private const NONCE_NAME   = 'hm_mahex_product_nonce';

	private const CHECKBOXES = array(
		'_hm_mahex_enabled'            => 'فعال‌سازی ارسال ماهکس برای این محصول',
		'_hm_mahex_requires_packaging' => 'نیازمند بسته‌بندی',
		'_hm_mahex_fragile'            => 'کالای شکستنی/حساس',
		'_hm_mahex_insurance_required' => 'بیمه الزامی',
		'_hm_mahex_cod_allowed'        => 'پرداخت در محل مجاز',
		'_hm_mahex_collect_allowed'    => 'پس‌کرایه مجاز',
		'_hm_mahex_ship_separately'    => 'ارسال جداگانه',
		'_hm_mahex_liquid'             => 'محصول مایع / نیازمند جداسازی',
	);

	private const TEXT_FIELDS = array(
		'_hm_mahex_contents_description' => 'text',
		'_hm_mahex_length_mm'            => 'positive_int',
		'_hm_mahex_width_mm'             => 'positive_int',
		'_hm_mahex_height_mm'            => 'positive_int',
		'_hm_mahex_custom_value'         => 'money',
		'_hm_mahex_lead_time_days'       => 'positive_int',
		'_hm_mahex_no_mix_group'        => 'text',
		'_hm_mahex_pick_zone'           => 'text',
	);

	private const SELECT_FIELDS = array(
		'_hm_mahex_packaging_mode' => array( 'product', 'profile', 'custom' ),
		'_hm_mahex_profile_id'     => array(),
		'_hm_mahex_warehouse_id'   => array(),
		'_hm_mahex_value_mode'     => array( 'product_price', 'custom' ),
	);

	public static function register(): void {
		add_filter( 'woocommerce_product_data_tabs', array( self::class, 'add_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( self::class, 'render_panel' ) );
		add_action( 'woocommerce_process_product_meta', array( self::class, 'save_product' ) );
		add_action( 'woocommerce_variation_options', array( self::class, 'render_variation' ), 20, 3 );
		add_action( 'woocommerce_save_product_variation', array( self::class, 'save_variation' ), 20, 2 );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_assets' ) );
	}

	public static function enqueue_assets(): void {
		$screen = get_current_screen();
		if ( ! $screen || 'product' !== $screen->post_type ) {
			return;
		}
		wp_enqueue_style( 'hm-mahex-product-fields', plugins_url( 'assets/admin/product-fields.css', HM_MAHEX_FILE ), array(), HM_MAHEX_VERSION );
		wp_enqueue_script( 'hm-mahex-packaging', plugins_url( 'assets/admin/packaging.js', HM_MAHEX_FILE ), array(), HM_MAHEX_VERSION, true );
	}

	public static function add_tab( array $tabs ): array {
		$tabs['hm_mahex'] = array(
			'label'    => __( 'اطلاعات ارسال ماهکس', 'mahex-shipping-for-woocommerce' ),
			'target'   => 'hm_mahex_product_data',
			'class'    => array( 'show_if_simple', 'show_if_variable' ),
			'priority' => 75,
		);
		return $tabs;
	}

	public static function render_panel(): void {
		echo '<div id="hm_mahex_product_data" class="panel woocommerce_options_panel hidden hm-mahex-product-fields" dir="rtl">';
		echo '<header class="hm-mahex-product-fields__hero"><img src="' . esc_url( plugins_url( 'assets/brand/mahex-reference.png', HM_MAHEX_FILE ) ) . '" alt="Mahex"><div><span>' . esc_html__( 'تنظیمات مرسوله', 'mahex-shipping-for-woocommerce' ) . '</span><h2>' . esc_html__( 'اطلاعات ارسال ماهکس', 'mahex-shipping-for-woocommerce' ) . '</h2><p>' . esc_html__( 'ابعاد، ارزش و خدمات موردنیاز این محصول را مشخص کنید.', 'mahex-shipping-for-woocommerce' ) . '</p></div></header>';
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		self::render_fields();
		self::render_recommendation( get_the_ID() );
		echo '</div>';
	}

	public static function render_variation( int $loop, array $variation_data, \WP_Post $variation ): void {
		echo '<div class="hm-mahex-variation-fields" dir="rtl"><div class="form-row form-row-full"><strong>' . esc_html__( 'اطلاعات ارسال ماهکس', 'mahex-shipping-for-woocommerce' ) . '</strong></div>';
		if ( 0 === $loop ) {
			wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		}
		self::render_fields( $variation->ID, '[' . $loop . ']' );
		echo '</div>';
	}

	private static function render_fields( int $product_id = 0, string $suffix = '' ): void {
		$product_id = $product_id ?: get_the_ID();
		$wrapper    = $suffix ? 'form-row form-row-full' : '';
		foreach ( self::CHECKBOXES as $key => $label ) {
			woocommerce_wp_checkbox(
				array(
					'id'            => $key . $suffix,
					'name'          => $key . $suffix,
					'label'         => __( $label, 'mahex-shipping-for-woocommerce' ), // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
					'value'         => get_post_meta( $product_id, $key, true ),
					'wrapper_class' => $suffix ? 'form-row form-row-first' : '',
				)
			);
		}
		woocommerce_wp_select(
			array(
				'id'            => '_hm_mahex_packaging_mode' . $suffix,
				'name'          => '_hm_mahex_packaging_mode' . $suffix,
				'label'         => __( 'منبع ابعاد بسته', 'mahex-shipping-for-woocommerce' ),
				'value'         => get_post_meta( $product_id, '_hm_mahex_packaging_mode', true ) ?: 'product',
				'options'       => array(
					'product' => __( 'ابعاد خود محصول', 'mahex-shipping-for-woocommerce' ),
					'profile' => __( 'پروفایل بسته‌بندی', 'mahex-shipping-for-woocommerce' ),
					'custom'  => __( 'ابعاد سفارشی', 'mahex-shipping-for-woocommerce' ),
				),
				'wrapper_class' => $wrapper,
			)
		);
		woocommerce_wp_select(
			array(
				'id'            => '_hm_mahex_profile_id' . $suffix,
				'name'          => '_hm_mahex_profile_id' . $suffix,
				'label'         => __( 'پروفایل بسته‌بندی', 'mahex-shipping-for-woocommerce' ),
				'value'         => get_post_meta( $product_id, '_hm_mahex_profile_id', true ),
				'options'       => array( '' => __( 'انتخاب کنید', 'mahex-shipping-for-woocommerce' ) ) + self::profile_options(),
				'wrapper_class' => $wrapper,
			)
		);
		foreach ( array( 'length' => 'طول', 'width' => 'عرض', 'height' => 'ارتفاع' ) as $dimension => $label ) {
			$key = '_hm_mahex_' . $dimension . '_mm';
			woocommerce_wp_text_input(
				array(
					'id'                => $key . $suffix,
					'name'              => $key . $suffix,
					'label'             => sprintf( __( '%s سفارشی (میلی‌متر)', 'mahex-shipping-for-woocommerce' ), $label ), // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
					'value'             => get_post_meta( $product_id, $key, true ),
					'type'              => 'number',
					'custom_attributes' => array( 'min' => '1', 'max' => '10000', 'step' => '1' ),
					'wrapper_class'     => $suffix ? 'form-row form-row-first' : '',
				)
			);
		}
		woocommerce_wp_select(
			array(
				'id'            => '_hm_mahex_warehouse_id' . $suffix,
				'name'          => '_hm_mahex_warehouse_id' . $suffix,
				'label'         => __( 'انبار مبدا', 'mahex-shipping-for-woocommerce' ),
				'value'         => get_post_meta( $product_id, '_hm_mahex_warehouse_id', true ),
				'options'       => array( '' => __( 'انتخاب خودکار نزدیک‌ترین انبار', 'mahex-shipping-for-woocommerce' ) ) + self::warehouse_options(),
				'wrapper_class' => $wrapper,
			)
		);
		woocommerce_wp_text_input(
			array(
				'id' => '_hm_mahex_lead_time_days' . $suffix, 'name' => '_hm_mahex_lead_time_days' . $suffix,
				'label' => __( 'زمان آماده‌سازی محصول (روز کاری)', 'mahex-shipping-for-woocommerce' ),
				'value' => get_post_meta( $product_id, '_hm_mahex_lead_time_days', true ), 'type' => 'number',
				'custom_attributes' => array( 'min' => '0', 'max' => '30', 'step' => '1' ), 'wrapper_class' => $wrapper,
			)
		);
		woocommerce_wp_text_input(
			array( 'id'=>'_hm_mahex_no_mix_group'.$suffix, 'name'=>'_hm_mahex_no_mix_group'.$suffix, 'label'=>__( 'گروه عدم اختلاط', 'mahex-shipping-for-woocommerce' ), 'description'=>__( 'کالاهای دارای گروه متفاوت در یک بسته قرار نمی‌گیرند.', 'mahex-shipping-for-woocommerce' ), 'value'=>get_post_meta($product_id,'_hm_mahex_no_mix_group',true), 'wrapper_class'=>$wrapper, 'custom_attributes'=>array('maxlength'=>'50') )
		);
		woocommerce_wp_text_input(
			array( 'id'=>'_hm_mahex_pick_zone'.$suffix, 'name'=>'_hm_mahex_pick_zone'.$suffix, 'label'=>__( 'زون جمع‌آوری انبار', 'mahex-shipping-for-woocommerce' ), 'value'=>get_post_meta($product_id,'_hm_mahex_pick_zone',true), 'wrapper_class'=>$wrapper, 'custom_attributes'=>array('maxlength'=>'50') )
		);
		woocommerce_wp_select(
			array(
				'id'            => '_hm_mahex_value_mode' . $suffix,
				'name'          => '_hm_mahex_value_mode' . $suffix,
				'label'         => __( 'مبنای ارزش مرسوله', 'mahex-shipping-for-woocommerce' ),
				'value'         => get_post_meta( $product_id, '_hm_mahex_value_mode', true ) ?: 'product_price',
				'options'       => array(
					'product_price' => __( 'قیمت محصول', 'mahex-shipping-for-woocommerce' ),
					'custom'        => __( 'مبلغ سفارشی', 'mahex-shipping-for-woocommerce' ),
				),
				'wrapper_class' => $wrapper,
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'                => '_hm_mahex_custom_value' . $suffix,
				'name'              => '_hm_mahex_custom_value' . $suffix,
				'label'             => __( 'ارزش سفارشی مرسوله', 'mahex-shipping-for-woocommerce' ),
				'value'             => get_post_meta( $product_id, '_hm_mahex_custom_value', true ),
				'type'              => 'number',
				'custom_attributes' => array( 'min' => '0', 'step' => '1' ),
				'wrapper_class'     => $wrapper,
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'                => '_hm_mahex_contents_description' . $suffix,
				'name'              => '_hm_mahex_contents_description' . $suffix,
				'label'             => __( 'شرح محتویات بارنامه', 'mahex-shipping-for-woocommerce' ),
				'value'             => get_post_meta( $product_id, '_hm_mahex_contents_description', true ),
				'custom_attributes' => array( 'maxlength' => '200' ),
				'wrapper_class'     => $suffix ? 'form-row form-row-full' : '',
			)
		);
	}

	private static function render_recommendation( int $product_id ): void {
		$product = wc_get_product( $product_id );
		if ( ! $product ) return;
		$length = (int) round( (float) wc_get_dimension( (float) $product->get_length(), 'mm' ) );
		$width  = (int) round( (float) wc_get_dimension( (float) $product->get_width(), 'mm' ) );
		$height = (int) round( (float) wc_get_dimension( (float) $product->get_height(), 'mm' ) );
		$weight = (int) round( (float) wc_get_weight( (float) $product->get_weight(), 'g' ) );
		if ( min( $length, $width, $height, $weight ) <= 0 ) return;
		$profile = \HoseinMomeni\MahexWoo\Packaging\SmartPackaging::recommend( $length, $width, $height, $weight );
		if ( ! $profile ) {
			echo '<p class="form-field"><strong>پیشنهاد بسته‌بندی هوشمند:</strong> پروفایل دارای موجودی و ابعاد مناسب پیدا نشد.</p>';
			return;
		}
		echo '<p class="form-field"><strong>پیشنهاد بسته‌بندی هوشمند:</strong> ' . esc_html( (string) ( $profile['name'] ?? '' ) ) . ' · موجودی ' . esc_html( (string) ( $profile['stock'] ?? 0 ) ) . '</p>';
	}

	public static function save_product( int $product_id ): void {
		if ( ! self::can_save( $product_id ) ) {
			return;
		}
		self::save_fields( $product_id, $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	}

	public static function save_variation( int $variation_id, int $loop ): void {
		if ( ! self::can_save( $variation_id ) ) {
			return;
		}
		$data = array();
		foreach ( array_keys( self::CHECKBOXES ) as $key ) {
			if ( isset( $_POST[ $key ][ $loop ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				$data[ $key ] = wp_unslash( $_POST[ $key ][ $loop ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			}
		}
		if ( isset( $_POST['_hm_mahex_contents_description'][ $loop ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$data['_hm_mahex_contents_description'] = wp_unslash( $_POST['_hm_mahex_contents_description'][ $loop ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}
		foreach ( array_merge( array_keys( self::TEXT_FIELDS ), array_keys( self::SELECT_FIELDS ) ) as $key ) {
			if ( isset( $_POST[ $key ][ $loop ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				$data[ $key ] = wp_unslash( $_POST[ $key ][ $loop ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			}
		}
		self::save_fields( $variation_id, $data );
	}

	private static function can_save( int $product_id ): bool {
		$nonce = isset( $_POST[ self::NONCE_NAME ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		return current_user_can( 'edit_post', $product_id ) && wp_verify_nonce( $nonce, self::NONCE_ACTION );
	}

	private static function save_fields( int $product_id, array $data ): void {
		foreach ( array_keys( self::CHECKBOXES ) as $key ) {
			update_post_meta( $product_id, $key, isset( $data[ $key ] ) && 'yes' === sanitize_text_field( (string) $data[ $key ] ) ? 'yes' : 'no' );
		}
		$description = isset( $data['_hm_mahex_contents_description'] ) ? sanitize_text_field( (string) $data['_hm_mahex_contents_description'] ) : '';
		update_post_meta( $product_id, '_hm_mahex_contents_description', wp_html_excerpt( $description, 200, '' ) );

		$packaging_mode = sanitize_key( (string) ( $data['_hm_mahex_packaging_mode'] ?? 'product' ) );
		update_post_meta( $product_id, '_hm_mahex_packaging_mode', in_array( $packaging_mode, self::SELECT_FIELDS['_hm_mahex_packaging_mode'], true ) ? $packaging_mode : 'product' );

		$profiles   = self::profile_options();
		$profile_id = sanitize_key( (string) ( $data['_hm_mahex_profile_id'] ?? '' ) );
		update_post_meta( $product_id, '_hm_mahex_profile_id', isset( $profiles[ $profile_id ] ) ? $profile_id : '' );
		$warehouse_id = sanitize_key( (string) ( $data['_hm_mahex_warehouse_id'] ?? '' ) );
		$warehouses = \HoseinMomeni\MahexWoo\Enterprise\WarehouseRepository::all();
		update_post_meta( $product_id, '_hm_mahex_warehouse_id', isset( $warehouses[ $warehouse_id ] ) ? $warehouse_id : '' );
		update_post_meta( $product_id, '_hm_mahex_lead_time_days', self::bounded_integer( $data['_hm_mahex_lead_time_days'] ?? '', 0, 30 ) );
		update_post_meta( $product_id, '_hm_mahex_no_mix_group', sanitize_key( (string) ( $data['_hm_mahex_no_mix_group'] ?? '' ) ) );
		update_post_meta( $product_id, '_hm_mahex_pick_zone', substr( sanitize_text_field( (string) ( $data['_hm_mahex_pick_zone'] ?? '' ) ), 0, 50 ) );

		$value_mode = sanitize_key( (string) ( $data['_hm_mahex_value_mode'] ?? 'product_price' ) );
		update_post_meta( $product_id, '_hm_mahex_value_mode', in_array( $value_mode, self::SELECT_FIELDS['_hm_mahex_value_mode'], true ) ? $value_mode : 'product_price' );
		update_post_meta( $product_id, '_hm_mahex_custom_value', self::bounded_integer( $data['_hm_mahex_custom_value'] ?? '', 0, PHP_INT_MAX ) );
		foreach ( array( '_hm_mahex_length_mm', '_hm_mahex_width_mm', '_hm_mahex_height_mm' ) as $key ) {
			update_post_meta( $product_id, $key, self::bounded_integer( $data[ $key ] ?? '', 1, 10000 ) );
		}
	}

	/** @return array<string,string> */
	private static function warehouse_options(): array {
		$options = array();
		foreach ( \HoseinMomeni\MahexWoo\Enterprise\WarehouseRepository::all() as $id => $warehouse ) {
			if ( is_array( $warehouse ) && ! empty( $warehouse['enabled'] ) ) $options[ sanitize_key( (string) $id ) ] = sanitize_text_field( (string) ( $warehouse['name'] ?? $id ) );
		}
		return $options;
	}

	/** @return array<string,string> */
	private static function profile_options(): array {
		$profiles = get_option( 'hm_mahex_packaging_profiles', array() );
		$options  = array();
		if ( is_array( $profiles ) ) {
			foreach ( $profiles as $id => $profile ) {
				if ( is_array( $profile ) && isset( $profile['name'] ) ) {
					$options[ sanitize_key( (string) $id ) ] = sanitize_text_field( (string) $profile['name'] );
				}
			}
		}
		return $options;
	}

	private static function bounded_integer( mixed $value, int $minimum, int $maximum ): string {
		$value = is_scalar( $value ) ? trim( (string) $value ) : '';
		if ( '' === $value || ! ctype_digit( $value ) ) {
			return '';
		}
		$number = (int) $value;
		return $number >= $minimum && $number <= $maximum ? (string) $number : '';
	}
}
