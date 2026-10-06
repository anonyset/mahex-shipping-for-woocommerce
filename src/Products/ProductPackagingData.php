<?php

namespace HoseinMomeni\MahexWoo\Products;

/** Resolves saved product choices into the item shape accepted by PackageBuilder. */
final class ProductPackagingData {
	/**
	 * @param array<string,mixed> $product Product dimensions/weight already converted to mm/g.
	 * @param array<string,mixed> $meta Saved Mahex product meta.
	 * @param array<string,array<string,mixed>> $profiles Local packaging profiles keyed by id.
	 * @return array<string,mixed>
	 */
	public static function resolve( array $product, array $meta, array $profiles ): array {
		$item = array(
			'id'              => (string) ( $product['id'] ?? '' ),
			'quantity'        => max( 1, (int) ( $product['quantity'] ?? 1 ) ),
			'weight_g'        => max( 0, (int) ( $product['weight_g'] ?? 0 ) ),
			'length_mm'       => max( 0, (int) ( $product['length_mm'] ?? 0 ) ),
			'width_mm'        => max( 0, (int) ( $product['width_mm'] ?? 0 ) ),
			'height_mm'       => max( 0, (int) ( $product['height_mm'] ?? 0 ) ),
			'ship_separately' => 'yes' === ( $meta['_hm_mahex_ship_separately'] ?? 'no' ),
		);

		$mode = (string) ( $meta['_hm_mahex_packaging_mode'] ?? 'product' );
		if ( 'profile' === $mode ) {
			$profile = $profiles[ (string) ( $meta['_hm_mahex_profile_id'] ?? '' ) ] ?? null;
			if ( is_array( $profile ) ) {
				$item['length_mm'] = max( 0, (int) ( $profile['length_mm'] ?? 0 ) );
				$item['width_mm']  = max( 0, (int) ( $profile['width_mm'] ?? 0 ) );
				$item['height_mm'] = max( 0, (int) ( $profile['height_mm'] ?? 0 ) );
				$item['weight_g'] += max( 0, (int) ( $profile['empty_weight_g'] ?? 0 ) );
			}
		} elseif ( 'custom' === $mode ) {
			$item['length_mm'] = max( 0, (int) ( $meta['_hm_mahex_length_mm'] ?? 0 ) );
			$item['width_mm']  = max( 0, (int) ( $meta['_hm_mahex_width_mm'] ?? 0 ) );
			$item['height_mm'] = max( 0, (int) ( $meta['_hm_mahex_height_mm'] ?? 0 ) );
		}

		$item['declared_value'] = 'custom' === ( $meta['_hm_mahex_value_mode'] ?? 'product_price' )
			? max( 0, (int) ( $meta['_hm_mahex_custom_value'] ?? 0 ) )
			: max( 0, (int) ( $product['price'] ?? 0 ) );

		return $item;
	}
}
