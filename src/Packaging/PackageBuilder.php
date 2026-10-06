<?php

namespace HoseinMomeni\MahexWoo\Packaging;

use InvalidArgumentException;

final class PackageBuilder {
	public function __construct(
		private readonly VolumetricWeightCalculatorInterface $volumetricCalculator,
		private readonly int $fallbackWeightGrams,
		private readonly array $fallbackDimensionsMm
	) {
		if ( $fallbackWeightGrams <= 0 || count( $fallbackDimensionsMm ) !== 3 || min( $fallbackDimensionsMm ) <= 0 ) {
			throw new InvalidArgumentException( 'Fallback weight and dimensions must be positive.' );
		}
	}

	/**
	 * @param list<array{id:string,quantity:int,weight_g?:int,length_mm?:int,width_mm?:int,height_mm?:int,ship_separately?:bool}> $items
	 */
	public function build( array $items ): PackagePlan {
		$combined = array();
		$separate = array();
		foreach ( $items as $item ) {
			$this->validateItem( $item );
			for ( $index = 0; $index < $item['quantity']; ++$index ) {
				if ( ! empty( $item['ship_separately'] ) ) {
					$separate[] = $this->makePackage( array( $item ) );
				} else {
					$combined[] = $item;
				}
			}
		}
		$packages = $combined ? array( $this->makePackage( $combined ) ) : array();
		return new PackagePlan( array_merge( $packages, $separate ) );
	}

	private function validateItem( array $item ): void {
		if ( empty( $item['id'] ) || ! isset( $item['quantity'] ) || ! is_numeric( $item['quantity'] ) || (int) $item['quantity'] <= 0 ) {
			throw new InvalidArgumentException( 'Each item needs an id and positive quantity.' );
		}
		if ( (int) $item['quantity'] > 1000 ) {
			throw new InvalidArgumentException( 'A single line item cannot exceed 1000 units for package calculation.' );
		}
	}

	/** @param list<array<string, mixed>> $units */
	private function makePackage( array $units ): array {
		$weight = 0;
		$length = 0;
		$width  = 0;
		$height = 0;
		$fallbacks = array();
		foreach ( $units as $unit ) {
			$usedFallback = ! isset( $unit['weight_g'], $unit['length_mm'], $unit['width_mm'], $unit['height_mm'] )
				|| (int) ( $unit['weight_g'] ?? 0 ) <= 0
				|| (int) ( $unit['length_mm'] ?? 0 ) <= 0
				|| (int) ( $unit['width_mm'] ?? 0 ) <= 0
				|| (int) ( $unit['height_mm'] ?? 0 ) <= 0;
			$itemWeight = isset( $unit['weight_g'] ) && $unit['weight_g'] > 0 ? (int) $unit['weight_g'] : $this->fallbackWeightGrams;
			$dimensions = array(
				isset( $unit['length_mm'] ) && $unit['length_mm'] > 0 ? (int) $unit['length_mm'] : (int) $this->fallbackDimensionsMm[0],
				isset( $unit['width_mm'] ) && $unit['width_mm'] > 0 ? (int) $unit['width_mm'] : (int) $this->fallbackDimensionsMm[1],
				isset( $unit['height_mm'] ) && $unit['height_mm'] > 0 ? (int) $unit['height_mm'] : (int) $this->fallbackDimensionsMm[2],
			);
			$weight += $itemWeight;
			$length = max( $length, $dimensions[0] );
			$width  = max( $width, $dimensions[1] );
			$height += $dimensions[2];
			if ( $usedFallback ) {
				$fallbacks[] = (string) $unit['id'];
			}
		}
		$volumetric = $this->volumetricCalculator->calculateGrams( $length, $width, $height );
		return array(
			'item_ids'            => array_values( array_column( $units, 'id' ) ),
			'actual_weight_g'      => $weight,
			'volumetric_weight_g'  => $volumetric,
			'chargeable_weight_g'  => max( $weight, $volumetric ),
			'dimensions_mm'        => array( $length, $width, $height ),
			'fallback_item_ids'   => array_values( array_unique( $fallbacks ) ),
		);
	}
}
