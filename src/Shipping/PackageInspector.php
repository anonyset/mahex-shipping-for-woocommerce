<?php

namespace HoseinMomeni\MahexWoo\Shipping;

use HoseinMomeni\MahexWoo\Packaging\ConfigurableVolumetricWeightCalculator;
use HoseinMomeni\MahexWoo\Packaging\PackageBuilder;
use HoseinMomeni\MahexWoo\Products\ProductPackagingData;

final class PackageInspector {
	/**
	 * @return array<int, array{product: \WC_Product, quantity: int}>
	 */
	public function shippable_items( array $package ): array {
		$items    = array();
		$contents = $package['contents'] ?? array();
		if ( ! is_array( $contents ) ) {
			return $items;
		}

		foreach ( $contents as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$product  = $item['data'] ?? null;
			$quantity = isset( $item['quantity'] ) ? absint( $item['quantity'] ) : 0;
			if ( ! $product instanceof \WC_Product || 0 === $quantity ) {
				continue;
			}

			try {
				if ( $product->is_virtual() ) {
					continue;
				}
			} catch ( \Throwable $exception ) {
				continue;
			}

			$items[] = array( 'product' => $product, 'quantity' => $quantity );
		}

		return $items;
	}

	/**
	 * When enabled-only mode is selected, every physical item in the package must
	 * explicitly opt in to Mahex. Variations inherit the parent flag when empty.
	 */
	public function all_items_mahex_enabled( array $package ): bool {
		$items = $this->shippable_items( $package );
		if ( array() === $items ) {
			return false;
		}
		foreach ( $items as $item ) {
			$meta = $this->product_meta( $item['product'] );
			if ( 'yes' !== ( $meta['_hm_mahex_enabled'] ?? '' ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Build the real checkout package context used by pricing. Product/variation
	 * packaging profiles, custom dimensions, separate shipping and volumetric
	 * weight are all reflected here. Missing physical data uses configured safe
	 * fallbacks instead of silently becoming a zero-weight shipment.
	 *
	 * @return array{actual_weight_g:int,volumetric_weight_g:int,chargeable_weight_g:int,package_count:int,requires_packaging:bool,insurance_required:bool,fragile:bool,fallback_item_ids:list<string>}
	 */
	public function shipping_context( array $package ): array {
		$items = $this->shippable_items( $package );
		if ( array() === $items ) {
			return array(
				'actual_weight_g' => 0,
				'volumetric_weight_g' => 0,
				'chargeable_weight_g' => 0,
				'package_count' => 0,
				'requires_packaging' => false,
				'insurance_required' => false,
				'fragile' => false,
				'fallback_item_ids' => array(),
			);
		}

		$profiles = function_exists( 'get_option' ) ? get_option( 'hm_mahex_packaging_profiles', array() ) : array();
		$profiles = is_array( $profiles ) ? $profiles : array();
		$resolved = array();
		$requires_packaging = false;
		$insurance_required = false;
		$fragile = false;

		foreach ( $items as $item ) {
			$product = $item['product'];
			$meta    = $this->product_meta( $product );
			$requires_packaging = $requires_packaging || 'yes' === ( $meta['_hm_mahex_requires_packaging'] ?? 'no' );
			$insurance_required = $insurance_required || 'yes' === ( $meta['_hm_mahex_insurance_required'] ?? 'no' );
			$fragile             = $fragile || 'yes' === ( $meta['_hm_mahex_fragile'] ?? 'no' );

			$product_data = array(
				'id'        => (string) $product->get_id(),
				'quantity'  => $item['quantity'],
				'weight_g'  => $this->weight_grams( $product ),
				'length_mm' => $this->dimension_mm( $product->get_length() ),
				'width_mm'  => $this->dimension_mm( $product->get_width() ),
				'height_mm' => $this->dimension_mm( $product->get_height() ),
				'price'     => max( 0, (int) round( (float) $product->get_price() ) ),
			);
			$resolved[] = ProductPackagingData::resolve( $product_data, $meta, $profiles );
		}

		$builder = new PackageBuilder(
			new ConfigurableVolumetricWeightCalculator( PricingSettings::int_value( 'volumetric_divisor', 5000, 1000, 50000 ) ),
			PricingSettings::int_value( 'fallback_weight_g', 500, 1, 1000000 ),
			array(
				PricingSettings::int_value( 'fallback_length_mm', 100, 1, 10000 ),
				PricingSettings::int_value( 'fallback_width_mm', 100, 1, 10000 ),
				PricingSettings::int_value( 'fallback_height_mm', 100, 1, 10000 ),
			)
		);
		$plan = $builder->build( $resolved );
		$packages = $plan->packages();
		$fallbacks = array();
		foreach ( $packages as $built ) {
			$fallbacks = array_merge( $fallbacks, (array) ( $built['fallback_item_ids'] ?? array() ) );
		}

		return array(
			'actual_weight_g'      => (int) array_sum( array_column( $packages, 'actual_weight_g' ) ),
			'volumetric_weight_g'  => (int) array_sum( array_column( $packages, 'volumetric_weight_g' ) ),
			'chargeable_weight_g'  => $plan->totalChargeableWeightGrams(),
			'package_count'        => count( $packages ),
			'requires_packaging'   => $requires_packaging,
			'insurance_required'   => $insurance_required,
			'fragile'              => $fragile,
			'fallback_item_ids'    => array_values( array_unique( array_map( 'strval', $fallbacks ) ) ),
		);
	}

	/** @param array<int, array{product: \WC_Product, quantity: int}> $items */
	public function weight_in_kg( array $items ): float {
		$total = 0.0;
		foreach ( $items as $item ) {
			$weight = (float) $item['product']->get_weight();
			if ( ! is_finite( $weight ) || $weight < 0 ) {
				throw new \UnexpectedValueException( 'Invalid product weight.' );
			}
			$weight = (float) wc_get_weight( $weight, 'kg' );
			if ( ! is_finite( $weight ) || $weight < 0 ) {
				throw new \UnexpectedValueException( 'Product weight could not be converted.' );
			}
			$total += $weight * $item['quantity'];
		}
		return $total;
	}

	/** @return array<string,mixed> */
	private function product_meta( \WC_Product $product ): array {
		$keys = array(
			'_hm_mahex_enabled', '_hm_mahex_requires_packaging', '_hm_mahex_fragile', '_hm_mahex_insurance_required',
			'_hm_mahex_cod_allowed', '_hm_mahex_collect_allowed', '_hm_mahex_ship_separately', '_hm_mahex_packaging_mode',
			'_hm_mahex_profile_id', '_hm_mahex_value_mode', '_hm_mahex_contents_description', '_hm_mahex_length_mm',
			'_hm_mahex_width_mm', '_hm_mahex_height_mm', '_hm_mahex_custom_value',
		);
		$parent = $product->is_type( 'variation' ) ? wc_get_product( $product->get_parent_id() ) : false;
		$result = array();
		foreach ( $keys as $key ) {
			$value = $product->get_meta( $key, true );
			if ( '' === $value && $parent instanceof \WC_Product ) {
				$value = $parent->get_meta( $key, true );
			}
			$result[ $key ] = $value;
		}
		return $result;
	}

	private function weight_grams( \WC_Product $product ): int {
		$raw = (float) $product->get_weight();
		if ( ! is_finite( $raw ) || $raw <= 0 ) {
			return 0;
		}
		$grams = (float) wc_get_weight( $raw, 'g' );
		return is_finite( $grams ) && $grams > 0 ? (int) round( $grams ) : 0;
	}

	private function dimension_mm( $value ): int {
		$raw = (float) $value;
		if ( ! is_finite( $raw ) || $raw <= 0 ) {
			return 0;
		}
		$millimetres = (float) wc_get_dimension( $raw, 'mm' );
		return is_finite( $millimetres ) && $millimetres > 0 ? (int) round( $millimetres ) : 0;
	}
}
