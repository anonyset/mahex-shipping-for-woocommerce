<?php

namespace HoseinMomeni\MahexWoo\Packaging;

use HoseinMomeni\MahexWoo\Admin\PackagingProfiles;

final class SmartPackaging {
	/** @return array<string,mixed>|null */
	public static function recommend( int $lengthMm, int $widthMm, int $heightMm, int $weightG ): ?array {
		$profiles = PackagingProfiles::all();
		$candidates = array();
		foreach ( $profiles as $profile ) {
			if ( ! is_array( $profile ) ) continue;
			$dims = array_map( 'intval', array( $profile['length_mm'] ?? 0, $profile['width_mm'] ?? 0, $profile['height_mm'] ?? 0 ) );
			sort( $dims );
			$item = array( max( 1, $lengthMm ), max( 1, $widthMm ), max( 1, $heightMm ) );
			sort( $item );
			$fits = $item[0] <= $dims[0] && $item[1] <= $dims[1] && $item[2] <= $dims[2] && $weightG <= (int) ( $profile['max_weight_g'] ?? 0 );
			$stock = isset( $profile['stock'] ) ? (int) $profile['stock'] : PHP_INT_MAX;
			if ( $fits && $stock > 0 ) {
				$volume = max( 1, $dims[0] * $dims[1] * $dims[2] );
				$candidates[] = array( 'profile' => $profile, 'volume' => $volume );
			}
		}
		usort( $candidates, static fn( $a, $b ) => $a['volume'] <=> $b['volume'] );
		return $candidates[0]['profile'] ?? null;
	}
}
