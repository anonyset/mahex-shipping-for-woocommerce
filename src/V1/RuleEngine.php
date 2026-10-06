<?php

namespace HoseinMomeni\MahexWoo\V1;

final class RuleEngine {
	public static function rules(): array {
		$rules = get_option( Config::RULES, array() );
		$rules = is_array( $rules ) ? array_values( array_filter( $rules, 'is_array' ) ) : array();
		usort( $rules, static fn( array $a, array $b ): int => (int) ( $a['priority'] ?? 100 ) <=> (int) ( $b['priority'] ?? 100 ) );
		return $rules;
	}

	public static function sanitizeRules( mixed $rows ): array {
		$rows = is_array( $rows ) ? $rows : array();
		$out = array();
		foreach ( array_slice( $rows, 0, 200 ) as $row ) {
			if ( ! is_array( $row ) ) continue;
			$name = substr( sanitize_text_field( (string) ( $row['name'] ?? '' ) ), 0, 100 );
			if ( '' === $name ) continue;
			$mode = sanitize_key( (string) ( $row['mode'] ?? 'surcharge' ) );
			if ( ! in_array( $mode, array( 'fixed','surcharge','percent','discount','free' ), true ) ) $mode = 'surcharge';
			$logic = strtoupper( sanitize_key( (string) ( $row['logic'] ?? 'and' ) ) );
			if ( ! in_array( $logic, array( 'AND','OR' ), true ) ) $logic = 'AND';
			$out[] = array(
				'id' => sanitize_key( (string) ( $row['id'] ?? '' ) ) ?: 'v1-' . strtolower( wp_generate_password( 10, false, false ) ),
				'name' => $name, 'active' => ! empty( $row['active'] ), 'priority' => max( 0, min( 100000, absint( $row['priority'] ?? 100 ) ) ),
				'logic' => $logic, 'stop' => ! empty( $row['stop'] ), 'mode' => $mode,
				'amount' => is_numeric( $row['amount'] ?? null ) ? max( 0.0, min( 1000000000000.0, (float) $row['amount'] ) ) : 0.0,
				'province' => substr( sanitize_text_field( (string) ( $row['province'] ?? '' ) ), 0, 100 ),
				'city' => substr( sanitize_text_field( (string) ( $row['city'] ?? '' ) ), 0, 100 ),
				'min_weight_g' => max( 0, absint( $row['min_weight_g'] ?? 0 ) ), 'max_weight_g' => max( 0, absint( $row['max_weight_g'] ?? 0 ) ),
				'min_qty' => max( 0, absint( $row['min_qty'] ?? 0 ) ), 'max_qty' => max( 0, absint( $row['max_qty'] ?? 0 ) ),
				'min_cart_irr' => max( 0, (int) ( $row['min_cart_irr'] ?? 0 ) ), 'max_cart_irr' => max( 0, (int) ( $row['max_cart_irr'] ?? 0 ) ),
				'categories' => self::csv( (string) ( $row['categories'] ?? '' ) ),
				'shipping_classes' => self::csv( (string) ( $row['shipping_classes'] ?? '' ) ),
				'brands' => self::csv( (string) ( $row['brands'] ?? '' ) ),
			);
		}
		return $out;
	}

	/** @return array{freight:float,applied:list<string>,breakdown:list<string>} */
	public static function apply( float $freight, array $package, string $province, string $city, int $weightG ): array {
		if ( ! Config::bool( 'feature_pricing', true ) ) return array( 'freight' => max( 0, $freight ), 'applied' => array(), 'breakdown' => array() );
		$ctx = self::context( $package, $province, $city, $weightG );
		$applied = array(); $breakdown = array();
		foreach ( self::rules() as $rule ) {
			if ( empty( $rule['active'] ) || ! self::matches( $rule, $ctx ) ) continue;
			$before = $freight; $amount = (float) ( $rule['amount'] ?? 0 );
			switch ( (string) ( $rule['mode'] ?? 'surcharge' ) ) {
				case 'fixed': $freight = $amount; break;
				case 'percent': $freight += $freight * $amount / 100; break;
				case 'discount': $freight -= $freight * min( 100, $amount ) / 100; break;
				case 'free': $freight = 0; break;
				default: $freight += $amount; break;
			}
			$freight = max( 0.0, $freight );
			$applied[] = (string) $rule['id'];
			$breakdown[] = 'v1-rule:' . (string) $rule['name'] . ':' . (int) round( $before ) . '→' . (int) round( $freight );
			if ( ! empty( $rule['stop'] ) ) break;
		}
		return array( 'freight' => round( $freight ), 'applied' => $applied, 'breakdown' => $breakdown );
	}

	private static function context( array $package, string $province, string $city, int $weightG ): array {
		$qty = 0; $categories = array(); $classes = array(); $brands = array();
		foreach ( (array) ( $package['contents'] ?? array() ) as $line ) {
			if ( ! is_array( $line ) || ! ( $line['data'] ?? null ) instanceof \WC_Product ) continue;
			$product = $line['data']; $qty += max( 0, (int) ( $line['quantity'] ?? 0 ) );
			$productId = $product->get_id();
			foreach ( wp_get_post_terms( $productId, 'product_cat', array( 'fields' => 'slugs' ) ) ?: array() as $slug ) $categories[] = sanitize_title( (string) $slug );
			$class = $product->get_shipping_class(); if ( '' !== $class ) $classes[] = sanitize_title( $class );
			foreach ( array( 'product_brand','pwb-brand','pa_brand' ) as $taxonomy ) {
				if ( ! taxonomy_exists( $taxonomy ) ) continue;
				foreach ( wp_get_post_terms( $productId, $taxonomy, array( 'fields' => 'slugs' ) ) ?: array() as $slug ) $brands[] = sanitize_title( (string) $slug );
			}
		}
		$cart = isset( $package['contents_cost'] ) && is_numeric( $package['contents_cost'] ) ? \HoseinMomeni\MahexWoo\Shipping\PricingSettings::from_store_currency( max( 0.0, (float) $package['contents_cost'] ) ) : 0.0;
		return array( 'province' => self::norm( $province ), 'city' => self::norm( $city ), 'weight_g' => $weightG, 'qty' => $qty, 'cart_irr' => $cart, 'categories' => array_unique( $categories ), 'shipping_classes' => array_unique( $classes ), 'brands' => array_unique( $brands ) );
	}

	private static function matches( array $rule, array $ctx ): bool {
		$checks = array();
		if ( '' !== trim( (string) ( $rule['province'] ?? '' ) ) ) $checks[] = self::norm( (string) $rule['province'] ) === $ctx['province'];
		if ( '' !== trim( (string) ( $rule['city'] ?? '' ) ) ) $checks[] = str_contains( $ctx['city'], self::norm( (string) $rule['city'] ) );
		foreach ( array( 'weight_g' => 'weight', 'qty' => 'qty', 'cart_irr' => 'cart' ) as $ctxKey => $prefix ) {
			$min = (float) ( $rule[ 'min_' . $prefix . ( 'weight' === $prefix ? '_g' : ( 'cart' === $prefix ? '_irr' : '' ) ) ] ?? 0 );
			$max = (float) ( $rule[ 'max_' . $prefix . ( 'weight' === $prefix ? '_g' : ( 'cart' === $prefix ? '_irr' : '' ) ) ] ?? 0 );
			if ( $min > 0 ) $checks[] = (float) $ctx[ $ctxKey ] >= $min;
			if ( $max > 0 ) $checks[] = (float) $ctx[ $ctxKey ] <= $max;
		}
		foreach ( array( 'categories','shipping_classes','brands' ) as $key ) {
			$need = (array) ( $rule[ $key ] ?? array() );
			if ( $need ) $checks[] = (bool) array_intersect( $need, (array) $ctx[ $key ] );
		}
		if ( ! $checks ) return true;
		return 'OR' === strtoupper( (string) ( $rule['logic'] ?? 'AND' ) ) ? in_array( true, $checks, true ) : ! in_array( false, $checks, true );
	}

	private static function csv( string $text ): array {
		$parts = preg_split( '/[,،\n\r]+/u', $text ) ?: array();
		return array_values( array_unique( array_filter( array_map( static fn( $v ) => sanitize_title( trim( (string) $v ) ), $parts ) ) ) );
	}
	private static function norm( string $text ): string { return trim( preg_replace( '/\s+/u', ' ', str_replace( array( 'ي','ك','ۀ','ة' ), array( 'ی','ک','ه','ه' ), $text ) ) ?: $text ); }
}
