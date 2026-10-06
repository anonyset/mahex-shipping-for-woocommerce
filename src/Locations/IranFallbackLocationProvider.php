<?php

namespace HoseinMomeni\MahexWoo\Locations;

/**
 * Local address-entry fallback. This is not a Mahex serviceability list.
 * Province names prefer WooCommerce's maintained IR state list when available.
 */
final class IranFallbackLocationProvider implements LocationProvider {
	public const SOURCE = 'woocommerce-ir-states/local-province-fallback';

	/** @var list<string> */
	private const PROVINCES = array(
		'آذربایجان شرقی', 'آذربایجان غربی', 'اردبیل', 'اصفهان', 'البرز', 'ایلام', 'بوشهر', 'تهران',
		'چهارمحال و بختیاری', 'خراسان جنوبی', 'خراسان رضوی', 'خراسان شمالی', 'خوزستان', 'زنجان',
		'سمنان', 'سیستان و بلوچستان', 'فارس', 'قزوین', 'قم', 'کردستان', 'کرمان', 'کرمانشاه',
		'کهگیلویه و بویراحمد', 'گلستان', 'گیلان', 'لرستان', 'مازندران', 'مرکزی', 'هرمزگان', 'همدان', 'یزد',
	);

	/** @param array<string,string> $states */
	public function __construct( private readonly array $states = array() ) {}

	public function fetch(): LocationProviderResult {
		$states = $this->states;
		$source = self::SOURCE;
		if ( array() === $states && function_exists( 'WC' ) && WC() && isset( WC()->countries ) ) {
			$states = (array) WC()->countries->get_states( 'IR' );
			$source = 'woocommerce:WC_Countries::get_states(IR)';
		}
		$names = array_values( array_unique( array_filter( array_map( static fn ( mixed $name ): string => trim( (string) $name ), $states ) ) ) );
		// An incomplete WooCommerce state list is less useful than the bundled, audited 31-province fallback.
		if ( 31 !== count( $names ) ) {
			$names = self::PROVINCES;
			$source = self::SOURCE;
		}

		// Province rows intentionally use an empty city marker; no city coverage is claimed.
		$locations = array_map( static fn ( string $name ): Location => new Location( $name, '', null, $source ), $names );
		return new LocationProviderResult( LocationProviderResult::AVAILABLE, $locations, $source, 'این فهرست فقط برای ورود استانِ نشانی است و پوشش سرویس ماهکس محسوب نمی‌شود.' );
	}
}
