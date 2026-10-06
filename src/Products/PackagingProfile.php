<?php

namespace HoseinMomeni\MahexWoo\Products;

use InvalidArgumentException;

/**
 * Normalises the local packaging data consumed by PackageBuilder.
 *
 * This value object deliberately has no WordPress dependency, so the same
 * validation is used by the admin screen and unit tests.
 */
final class PackagingProfile {
	private const MAX_DIMENSION_MM = 10000;
	private const MAX_WEIGHT_G     = 1000000;

	/** @return array{id:string,name:string,type:string,length_mm:int,width_mm:int,height_mm:int,empty_weight_g:int,max_weight_g:int,max_items:int,stock:int,min_stock:int,unit_cost_irr:int,price_history:array} */
	public static function fromInput( array $input ): array {
		$name = trim( strip_tags( (string) ( $input['name'] ?? '' ) ) );
		if ( '' === $name ) {
			throw new InvalidArgumentException( 'Profile name is required.' );
		}

		$id = preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) ( $input['id'] ?? '' ) ) );
		if ( '' === $id ) {
			$id = 'profile-' . substr( hash( 'sha256', $name . '|' . microtime( true ) ), 0, 12 );
		}

		$type = strtolower( trim( (string) ( $input['type'] ?? 'custom' ) ) );
		if ( ! in_array( $type, array( 'envelope', 'carton', 'box', 'custom' ), true ) ) $type = 'custom';

		return array(
			'id'             => substr( $id, 0, 64 ),
			'name'           => substr( $name, 0, 100 ),
			'type'           => $type,
			'length_mm'      => self::positiveInteger( $input['length_mm'] ?? 0, 'length_mm', self::MAX_DIMENSION_MM ),
			'width_mm'       => self::positiveInteger( $input['width_mm'] ?? 0, 'width_mm', self::MAX_DIMENSION_MM ),
			'height_mm'      => self::positiveInteger( $input['height_mm'] ?? 0, 'height_mm', self::MAX_DIMENSION_MM ),
			'empty_weight_g' => self::nonNegativeInteger( $input['empty_weight_g'] ?? 0, 'empty_weight_g', self::MAX_WEIGHT_G ),
			'max_weight_g'   => self::positiveInteger( $input['max_weight_g'] ?? 0, 'max_weight_g', self::MAX_WEIGHT_G ),
			'max_items'      => self::nonNegativeInteger( $input['max_items'] ?? 0, 'max_items', 10000 ),
			'stock'          => self::nonNegativeInteger( $input['stock'] ?? 0, 'stock', 1000000 ),
			'min_stock'      => self::nonNegativeInteger( $input['min_stock'] ?? 0, 'min_stock', 1000000 ),
			'unit_cost_irr'  => self::nonNegativeInteger( $input['unit_cost_irr'] ?? 0, 'unit_cost_irr', PHP_INT_MAX ),
			'price_history'   => is_array( $input['price_history'] ?? null ) ? $input['price_history'] : array(),
		);
	}

	private static function positiveInteger( mixed $value, string $field, int $maximum ): int {
		$number = self::integer( $value, $field );
		if ( $number <= 0 || $number > $maximum ) {
			throw new InvalidArgumentException( $field . ' must be between 1 and ' . $maximum . '.' );
		}
		return $number;
	}

	private static function nonNegativeInteger( mixed $value, string $field, int $maximum ): int {
		$number = self::integer( $value, $field );
		if ( $number < 0 || $number > $maximum ) {
			throw new InvalidArgumentException( $field . ' must be between 0 and ' . $maximum . '.' );
		}
		return $number;
	}

	private static function integer( mixed $value, string $field ): int {
		if ( ! is_scalar( $value ) || ! preg_match( '/^\d+$/', trim( (string) $value ) ) ) {
			throw new InvalidArgumentException( $field . ' must be an integer.' );
		}
		return (int) $value;
	}
}
