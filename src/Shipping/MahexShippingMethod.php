<?php

namespace HoseinMomeni\MahexWoo\Shipping;

use HoseinMomeni\MahexWoo\Rates\Admin\RateRuleManager;
use HoseinMomeni\MahexWoo\Rates\Admin\WordPressOptionRateRuleRepository;
use HoseinMomeni\MahexWoo\Rates\RateRule;
use HoseinMomeni\MahexWoo\Pro\FeatureSettings;
use HoseinMomeni\MahexWoo\Enterprise\Config as EnterpriseConfig;
use HoseinMomeni\MahexWoo\Enterprise\ServiceSelector;
use HoseinMomeni\MahexWoo\Enterprise\DeliveryEstimator;
use HoseinMomeni\MahexWoo\Enterprise\WarehouseRouter;
use HoseinMomeni\MahexWoo\V1\PackingEngine;
use HoseinMomeni\MahexWoo\V1\RuleEngine as V1RuleEngine;
use HoseinMomeni\MahexWoo\V1\MarginEngine;
use HoseinMomeni\MahexWoo\V1\Profiler;
use HoseinMomeni\MahexWoo\V2\RateCache;

defined( 'ABSPATH' ) || exit;

final class MahexShippingMethod extends \WC_Shipping_Method {
	private PackageInspector $package_inspector;

	public function __construct( int $instance_id = 0 ) {
		$this->id                 = 'hm_mahex';
		$this->instance_id        = absint( $instance_id );
		$this->method_title       = __( 'ارسال ماهکس', 'mahex-shipping-for-woocommerce' );
		$this->method_description = __( 'محاسبه نرخ ماهکس با وزن واقعی/حجمی، نرخ استانی و قوانین قیمت فروشگاه.', 'mahex-shipping-for-woocommerce' );
		$this->supports           = array( 'shipping-zones', 'instance-settings', 'instance-settings-modal' );
		$this->package_inspector  = new PackageInspector();
		$this->init();
	}

	private function init(): void {
		$this->init_form_fields();
		$this->init_settings();
		$this->title   = str_replace( array( ' (آزمایشی)', ' آزمایشی' ), '', (string) $this->get_option( 'title', __( 'ارسال با ماهکس', 'mahex-shipping-for-woocommerce' ) ) );
		$this->enabled = (string) $this->get_option( 'enabled', 'yes' );
		add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	public function init_form_fields(): void {
		$this->instance_form_fields = array(
			'enabled' => array(
				'title' => __( 'فعال', 'mahex-shipping-for-woocommerce' ),
				'type' => 'checkbox',
				'default' => 'yes',
			),
			'title' => array(
				'title' => __( 'عنوان در تسویه‌حساب', 'mahex-shipping-for-woocommerce' ),
				'type' => 'text',
				'default' => __( 'ارسال با ماهکس', 'mahex-shipping-for-woocommerce' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'base_cost' => array(
				'title' => __( 'هزینه پایه جایگزین (واحد پول فروشگاه)', 'mahex-shipping-for-woocommerce' ),
				'type' => 'price',
				'default' => (string) PricingSettings::to_store_currency( 850000 ),
				'description' => __( 'فقط وقتی تنظیمات سراسری قیمت مقدار نداده باشند استفاده می‌شود.', 'mahex-shipping-for-woocommerce' ),
			),
			'cost_per_kg' => array(
				'title' => __( 'هزینه هر کیلوگرم جایگزین', 'mahex-shipping-for-woocommerce' ),
				'type' => 'price',
				'default' => (string) PricingSettings::to_store_currency( 75000 ),
			),
			'fallback_cost' => array(
				'title' => __( 'هزینه امن هنگام خطای محاسبه', 'mahex-shipping-for-woocommerce' ),
				'type' => 'price',
				'default' => (string) PricingSettings::to_store_currency( 925000 ),
				'description' => __( 'اگر داده محصول خراب باشد Checkout با این نرخ ادامه پیدا می‌کند.', 'mahex-shipping-for-woocommerce' ),
			),
		);
	}

	public function calculate_shipping( $package = array() ): void {
		if ( 'yes' !== $this->enabled || ! is_array( $package ) ) return;
		$items = $this->package_inspector->shippable_items( $package );
		if ( array() === $items ) return;
		if ( 'enabled_only' === PricingSettings::value( 'product_availability', 'all_products' ) && ! $this->package_inspector->all_items_mahex_enabled( $package ) ) return;

		Profiler::start( 'checkout_rate' );

		$base_fallback = PricingSettings::from_store_currency( max( 0.0, (float) wc_format_decimal( $this->get_option( 'base_cost', (string) PricingSettings::to_store_currency( 850000 ) ) ) ) );
		$kg_fallback   = PricingSettings::from_store_currency( max( 0.0, (float) wc_format_decimal( $this->get_option( 'cost_per_kg', (string) PricingSettings::to_store_currency( 75000 ) ) ) ) );
		$base_cost     = PricingSettings::money( 'manual_base_cost', $base_fallback );
		$cost_per_kg   = PricingSettings::money( 'manual_cost_per_kg', $kg_fallback );
		$cacheKey = RateCache::key( $package, $this->instance_id, array( 'title'=>$this->title, 'base'=>$base_cost, 'kg'=>$cost_per_kg, 'version'=>HM_MAHEX_VERSION ) );
		$cached = RateCache::get( $cacheKey );
		if ( is_array( $cached ) && ! empty( $cached['rates'] ) && is_array( $cached['rates'] ) ) {
			foreach ( $cached['rates'] as $row ) {
				if ( ! is_array( $row ) ) continue;
				$this->add_mahex_rate( (array) ( $row['parts'] ?? array() ), (string) ( $row['source'] ?? 'local' ), (string) ( $row['label'] ?? $this->title ), $package, (string) ( $row['suffix'] ?? '' ), (array) ( $row['context'] ?? array() ), isset( $row['applied_rule'] ) ? (string) $row['applied_rule'] : null, ! empty( $row['free'] ) );
			}
			Profiler::stop( 'checkout_rate', array( 'result'=>'cache_hit', 'rates'=>count( $cached['rates'] ) ) );
			return;
		}

		try {
			$context = $this->package_inspector->shipping_context( $package );
			$packing = PackingEngine::plan( $package );
			if ( (int) ( $packing['package_count'] ?? 0 ) > 0 ) {
				$context['actual_weight_g'] = (int) $packing['actual_weight_g'];
				$context['volumetric_weight_g'] = (int) $packing['volumetric_weight_g'];
				$context['chargeable_weight_g'] = (int) $packing['chargeable_weight_g'];
				$context['package_count'] = (int) $packing['package_count'];
				$context['v1_packing_cost_irr'] = (int) $packing['cost_irr'];
				$context['v1_packing_usage'] = (array) $packing['usage'];
				$context['v1_packing_warnings'] = (array) $packing['warnings'];
			}
			$billable_g = AdvancedRateEngine::billableWeightGrams( (int) $context['chargeable_weight_g'] );
			$weight = max( 0.001, $billable_g / 1000 );
			$state = isset( $package['destination']['state'] ) ? (string) $package['destination']['state'] : '';
			$province = PricingSettings::province_name( $state );
			$city = isset( $package['destination']['city'] ) ? trim( sanitize_text_field( (string) $package['destination']['city'] ) ) : '';
			$postcode = isset( $package['destination']['postcode'] ) ? preg_replace( '/\D/', '', (string) $package['destination']['postcode'] ) : '';

			if ( EnterpriseConfig::bool( 'enabled', true ) && ! ServiceSelector::availableDestination( $province, $city, $billable_g ) ) {
				if ( EnterpriseConfig::bool( 'fallback_enabled', true ) ) {
					$cost = EnterpriseConfig::int( 'fallback_cost_irr', 950000, 0, PHP_INT_MAX );
					$this->add_rate( array( 'id'=>$this->get_rate_id().':enterprise-fallback', 'label'=>(string)EnterpriseConfig::get('fallback_label','ارسال جایگزین فروشگاه'), 'cost'=>PricingSettings::to_store_currency($cost), 'package'=>$package, 'meta_data'=>array('provider'=>'LocalFallback','hm_mahex_source'=>'fallback','hm_mahex_freight'=>PricingSettings::to_store_currency($cost),'hm_mahex_packaging'=>0,'hm_mahex_insurance'=>0,'hm_mahex_unavailable'=>'yes') ) );
				}
				Profiler::stop( 'checkout_rate', array( 'result'=>'destination_unavailable' ) );
				return;
			}

			$eta = DeliveryEstimator::estimate( $package );
			$context['eta'] = $eta;
			$context['warehouse_id'] = (string) ( $package['hm_mahex_warehouse_id'] ?? $eta['warehouse_id'] ?? '' );
			$freight = $base_cost + ( ceil( max( 1.0, $weight ) ) * $cost_per_kg );
			$freight = PricingSettings::province_rate( $state, $freight );
			$applied_rule = null;
			[ $freight, $applied_rule ] = $this->apply_rate_rules( $freight, $province, $city );
			$advanced = AdvancedRateEngine::adjust( $freight, $package, $province, $city, (string) $postcode );
			$freight = (float) $advanced['freight'];
			$v1Rules = V1RuleEngine::apply( $freight, $package, $province, $city, $billable_g );
			$freight = (float) $v1Rules['freight'];
			$margin = MarginEngine::apply( $freight );
			$freight = (float) $margin['freight'];

			$context['billable_weight_g'] = $billable_g;
			$context['shipping_zone_class'] = AdvancedRateEngine::zone( $province, $city, (string) $postcode );
			$context['advanced_rate_breakdown'] = array_merge( (array) $advanced['breakdown'], (array) $v1Rules['breakdown'], (array) $margin['breakdown'] );
			$context['v1_rule_ids'] = (array) $v1Rules['applied'];
			$context['v1_subsidy_irr'] = (float) $margin['subsidy'];

			$packagingAmount = $this->component_amount( 'packaging', PricingSettings::money( 'manual_packaging_cost' ), $context );
			if ( ! empty( $context['requires_packaging'] ) && (int) ( $context['v1_packing_cost_irr'] ?? 0 ) > 0 ) $packagingAmount = (float) $context['v1_packing_cost_irr'];
			$parts = array(
				'freight' => $freight,
				'packaging' => $packagingAmount,
				'insurance' => $this->component_amount( 'insurance', PricingSettings::money( 'manual_insurance_cost' ), $context ),
			);
			$free = $this->qualifies_for_free_shipping( $package );
			if ( $free ) $parts['freight'] = 0.0;
			$ruleIds = array_values( array_filter( array_merge( array( $applied_rule ), (array) $v1Rules['applied'] ) ) );
			$ruleMeta = $ruleIds ? implode( ',', array_map( 'strval', $ruleIds ) ) : null;

			if ( EnterpriseConfig::bool( 'service_choices', false ) ) {
				$services = ServiceSelector::localServices( $billable_g );
				if ( $services ) {
					$cacheRows = array();
					foreach ( $services as $service ) {
						$sp = $parts; if ( ! $free ) $sp['freight'] = max( 0.0, (float) $sp['freight'] + (float) $service['surcharge_irr'] );
						$sc = $context; $sc['eta'] = DeliveryEstimator::estimate( $package, (int) $service['sla_days'] ); $sc['service_id']=$service['id']; $sc['service_name']=$service['label'];
						$label = $this->title . ' — ' . $service['label']; $suffix = ':service-' . $service['id'];
						$cacheRows[] = array( 'parts'=>$sp, 'source'=>'local', 'label'=>$label, 'suffix'=>$suffix, 'context'=>$sc, 'applied_rule'=>$ruleMeta, 'free'=>$free );
						$this->add_mahex_rate( $sp, 'local', $label, $package, $suffix, $sc, $ruleMeta, $free );
					}
					RateCache::set( $cacheKey, array( 'rates'=>$cacheRows, 'at'=>time() ) );
					Profiler::stop( 'checkout_rate', array( 'result'=>'service_choices', 'services'=>count($services) ) );
					return;
				}
			}
			RateCache::set( $cacheKey, array( 'rates'=>array( array( 'parts'=>$parts, 'source'=>'local', 'label'=>$this->title, 'suffix'=>'', 'context'=>$context, 'applied_rule'=>$ruleMeta, 'free'=>$free ) ), 'at'=>time() ) );
			$this->add_mahex_rate( $parts, 'local', $this->title, $package, '', $context, $ruleMeta, $free );
			Profiler::stop( 'checkout_rate', array( 'result'=>'local', 'packages'=>(int)($context['package_count']??1) ) );
		} catch ( \Throwable $exception ) {
			$cost = PricingSettings::money( 'fallback_cost', PricingSettings::from_store_currency( max( 0.0, (float) wc_format_decimal( $this->get_option( 'fallback_cost', (string) PricingSettings::to_store_currency( 925000 ) ) ) ) ) );
			$this->add_rate( array(
				'id' => $this->get_rate_id(), 'label' => $this->title, 'cost' => PricingSettings::to_store_currency( $cost ), 'package' => $package,
				'meta_data' => array( 'provider'=>'LocalSafeFallback','hm_mahex_source'=>'fallback','hm_mahex_freight'=>PricingSettings::to_store_currency($cost),'hm_mahex_packaging'=>0,'hm_mahex_insurance'=>0 ),
			) );
			$this->log_calculation_error( $exception );
			Profiler::stop( 'checkout_rate', array( 'result'=>'fallback', 'error'=>get_class($exception) ) );
		}
	}

	/** @param array<string,mixed> $parts @param array<string,mixed> $package @param array<string,mixed> $context */
	private function add_mahex_rate( array $parts, string $source, string $label, array $package, string $suffix, array $context, ?string $applied_rule, bool $free ): void {
		$freight   = $this->non_negative_number( $parts['freight'] ?? 0 );
		$packaging = $this->non_negative_number( $parts['packaging'] ?? 0 );
		$insurance = $this->non_negative_number( $parts['insurance'] ?? 0 );
		$freight_store   = PricingSettings::to_store_currency( $freight );
		$packaging_store = PricingSettings::to_store_currency( $packaging );
		$insurance_store = PricingSettings::to_store_currency( $insurance );
		$this->maybe_log_rate( $source, $freight, $packaging, $insurance, $context, $package );
		$this->add_rate( array(
			'id'      => $this->get_rate_id() . $suffix,
			'label'   => $label,
			'cost'    => $freight_store + $packaging_store + $insurance_store,
			'package' => $package,
			'meta_data' => array(
				'provider' => 'official' === $source ? 'MahexProvider' : 'StoreConfiguredRate',
				'hm_mahex_source' => $source,
				'hm_mahex_freight' => $freight_store,
				'hm_mahex_packaging' => $packaging_store,
				'hm_mahex_insurance' => $insurance_store,
				'hm_mahex_actual_weight_kg' => round( (int) ( $context['actual_weight_g'] ?? 0 ) / 1000, 3 ),
				'hm_mahex_volumetric_weight_kg' => round( (int) ( $context['volumetric_weight_g'] ?? 0 ) / 1000, 3 ),
				'hm_mahex_chargeable_weight_kg' => round( (int) ( $context['chargeable_weight_g'] ?? 0 ) / 1000, 3 ),
				'hm_mahex_billable_weight_kg' => round( (int) ( $context['billable_weight_g'] ?? $context['chargeable_weight_g'] ?? 0 ) / 1000, 3 ),
				'hm_mahex_zone_class' => sanitize_key( (string) ( $context['shipping_zone_class'] ?? '' ) ),
				'hm_mahex_rate_breakdown' => wp_json_encode( (array) ( $context['advanced_rate_breakdown'] ?? array() ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
				'hm_mahex_package_count' => (int) ( $context['package_count'] ?? 1 ),
				'hm_mahex_applied_rule' => $applied_rule ?? '',
				'hm_mahex_free_shipping' => $free ? 'yes' : 'no',
				'hm_mahex_eta' => sanitize_text_field( (string) ( $context['eta']['label'] ?? '' ) ),
				'hm_mahex_eta_date' => sanitize_text_field( (string) ( $context['eta']['date'] ?? '' ) ),
				'hm_mahex_warehouse_id' => sanitize_key( (string) ( $context['warehouse_id'] ?? '' ) ),
				'hm_mahex_service_id' => sanitize_key( (string) ( $context['service_id'] ?? '' ) ),
				'hm_mahex_service_name' => sanitize_text_field( (string) ( $context['service_name'] ?? '' ) ),
				'hm_mahex_subsidy' => PricingSettings::to_store_currency( (float) ( $context['v1_subsidy_irr'] ?? 0 ) ),
				'hm_mahex_packing_usage' => wp_json_encode( (array) ( $context['v1_packing_usage'] ?? array() ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
				'hm_mahex_packing_warnings' => wp_json_encode( (array) ( $context['v1_packing_warnings'] ?? array() ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
			),
		) );
	}

	/** @param array<string,mixed> $context */
	private function component_amount( string $type, float $amount, array $context ): float {
		$application = (string) PricingSettings::value( $type . '_application', 'always' );
		if ( 'never' === $application ) {
			return 0.0;
		}
		if ( 'required_products' === $application ) {
			$required_key = 'packaging' === $type ? 'requires_packaging' : 'insurance_required';
			return ! empty( $context[ $required_key ] ) ? $amount : 0.0;
		}
		return $amount;
	}

	/** @param mixed $quote @param array<string,mixed> $context @return array{freight:float,packaging:float,insurance:float} */
	private function normalized_quote( $quote, array $context ): array {
		$quote = is_array( $quote ) ? $quote : array();
		return array(
			'freight' => $this->non_negative_number( $quote['freight'] ?? 0 ),
			'packaging' => $this->component_amount( 'packaging', $this->non_negative_number( $quote['packaging'] ?? 0 ), $context ),
			'insurance' => $this->component_amount( 'insurance', $this->non_negative_number( $quote['insurance'] ?? 0 ), $context ),
		);
	}

	private function valid_quote( $quote ): bool {
		return is_array( $quote ) && array_key_exists( 'freight', $quote ) && is_numeric( $quote['freight'] ) && is_finite( (float) $quote['freight'] ) && (float) $quote['freight'] >= 0;
	}

	private function non_negative_number( $value ): float {
		if ( ! is_numeric( $value ) ) {
			return 0.0;
		}
		$value = (float) $value;
		return is_finite( $value ) ? max( 0.0, $value ) : 0.0;
	}

	/** @return array{0:float,1:?string} */
	private function apply_rate_rules( float $freight, string $province, string $city ): array {
		try {
			$manager = new RateRuleManager( new WordPressOptionRateRuleRepository() );
			foreach ( $manager->active() as $record ) {
				if ( 'IRR' !== $record->currency ) {
					continue;
				}
				$rule = $record->toDomainRule();
				if ( ! $rule->matches( $province, $city ) ) {
					continue;
				}
				$freight = RateRule::FIXED === $rule->mode ? (float) $record->amount : $freight + $record->amount;
				return array( max( 0.0, $freight ), $record->id );
			}
		} catch ( \Throwable $error ) {
			$this->log_calculation_error( $error );
		}
		return array( max( 0.0, $freight ), null );
	}

	/** @param array<string,mixed> $package */
	private function qualifies_for_free_shipping( array $package ): bool {
		$threshold = PricingSettings::money( 'free_shipping_threshold', 0 );
		if ( $threshold <= 0 ) {
			return false;
		}
		$contents_cost = isset( $package['contents_cost'] ) && is_numeric( $package['contents_cost'] ) ? (float) $package['contents_cost'] : 0.0;
		return PricingSettings::from_store_currency( max( 0.0, $contents_cost ) ) >= $threshold;
	}

	/** @param array<string,mixed> $context @param array<string,mixed> $package */
	private function maybe_log_rate( string $source, float $freight, float $packaging, float $insurance, array $context, array $package ): void {
		if ( ! FeatureSettings::bool( 'debug_rate_log' ) || ! function_exists( 'wc_get_logger' ) ) return;
		$destination = is_array( $package['destination'] ?? null ) ? $package['destination'] : array();
		wc_get_logger()->debug(
			'Mahex rate calculation.',
			array(
				'source' => 'hm-mahex-rate',
				'rate_source' => $source,
				'freight_irr' => $freight,
				'packaging_irr' => $packaging,
				'insurance_irr' => $insurance,
				'zone' => $context['shipping_zone_class'] ?? '',
				'billable_g' => $context['billable_weight_g'] ?? 0,
				'breakdown' => $context['advanced_rate_breakdown'] ?? array(),
				'province' => PricingSettings::province_name( (string) ( $destination['state'] ?? '' ) ),
				'city' => sanitize_text_field( (string) ( $destination['city'] ?? '' ) ),
			)
		);
	}

	private function log_calculation_error( \Throwable $exception ): void {
		if ( ! function_exists( 'wc_get_logger' ) ) {
			return;
		}
		wc_get_logger()->error(
			'خطا در محاسبه نرخ ماهکس؛ نرخ امن یا تنظیمات پایه استفاده شد.',
			array( 'source' => 'hm-mahex-shipping', 'exception' => get_class( $exception ) )
		);
	}
}
