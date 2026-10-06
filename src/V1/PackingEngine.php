<?php

namespace HoseinMomeni\MahexWoo\V1;

use HoseinMomeni\MahexWoo\Admin\PackagingProfiles;
use HoseinMomeni\MahexWoo\Packaging\ConfigurableVolumetricWeightCalculator;
use HoseinMomeni\MahexWoo\Shipping\PricingSettings;

final class PackingEngine {
	/** @return array{packages:list<array<string,mixed>>,package_count:int,actual_weight_g:int,volumetric_weight_g:int,chargeable_weight_g:int,cost_irr:int,usage:array<string,int>,warnings:list<string>,truncated:bool} */
	public static function plan( array $package ): array {
		if ( ! Config::bool( 'feature_packing', true ) ) return self::emptyPlan();
		$profiles = PackagingProfiles::all();
		$units = self::units( $package );
		if ( ! $units['items'] ) return self::emptyPlan();
		$groups = array();
		foreach ( $units['items'] as $unit ) $groups[ $unit['group'] ][] = $unit;
		$boxes = array(); $usage = array(); $warnings = array();
		foreach ( $groups as $groupUnits ) {
			usort( $groupUnits, static fn( array $a, array $b ): int => $b['volume'] <=> $a['volume'] ?: $b['weight_g'] <=> $a['weight_g'] );
			$groupBoxes = array();
			foreach ( $groupUnits as $unit ) {
				$placed = false;
				foreach ( $groupBoxes as $idx => $box ) {
					if ( self::canFit( $box, $unit ) ) {
						$groupBoxes[ $idx ] = self::place( $box, $unit ); $placed = true; break;
					}
				}
				if ( ! $placed ) {
					$new = self::newBox( $unit, $profiles, $usage );
					if ( 'custom' === $new['profile_id'] ) $warnings[] = 'برای یکی از اقلام بسته استاندارد مناسب/دارای موجودی پیدا نشد و بسته سفارشی محاسبه شد.';
					$groupBoxes[] = self::place( $new, $unit );
					if ( 'custom' !== $new['profile_id'] ) $usage[ $new['profile_id'] ] = 1 + (int) ( $usage[ $new['profile_id'] ] ?? 0 );
				}
			}
			$boxes = array_merge( $boxes, $groupBoxes );
		}
		$calc = new ConfigurableVolumetricWeightCalculator( PricingSettings::int_value( 'volumetric_divisor', 5000, 1000, 50000 ) );
		$actual = 0; $vol = 0; $cost = 0;
		foreach ( $boxes as &$box ) {
			$weight = (int) $box['content_weight_g'] + (int) $box['empty_weight_g'];
			$d = (array) $box['dimensions_mm'];
			$v = $calc->calculateGrams( max( 1, (int) $d[0] ), max( 1, (int) $d[1] ), max( 1, (int) $d[2] ) );
			$box['actual_weight_g'] = $weight; $box['volumetric_weight_g'] = $v; $box['chargeable_weight_g'] = max( $weight, $v );
			$actual += $weight; $vol += $v; $cost += (int) $box['unit_cost_irr'];
		}
		unset( $box );
		return array(
			'packages' => array_values( $boxes ), 'package_count' => count( $boxes ), 'actual_weight_g' => $actual,
			'volumetric_weight_g' => $vol, 'chargeable_weight_g' => (int) array_sum( array_column( $boxes, 'chargeable_weight_g' ) ),
			'cost_irr' => $cost, 'usage' => $usage, 'warnings' => array_values( array_unique( $warnings ) ), 'truncated' => $units['truncated'],
		);
	}

	private static function emptyPlan(): array { return array( 'packages'=>array(),'package_count'=>0,'actual_weight_g'=>0,'volumetric_weight_g'=>0,'chargeable_weight_g'=>0,'cost_irr'=>0,'usage'=>array(),'warnings'=>array(),'truncated'=>false ); }

	/** @return array{items:list<array<string,mixed>>,truncated:bool} */
	private static function units( array $package ): array {
		$items = array(); $max = Config::int( 'packing_max_units', 500, 1, 5000 ); $count = 0; $truncated = false;
		$fallbackWeight = PricingSettings::int_value( 'fallback_weight_g', 500, 1, 1000000 );
		$fallbackDims = array( PricingSettings::int_value( 'fallback_length_mm', 100, 1, 10000 ), PricingSettings::int_value( 'fallback_width_mm', 100, 1, 10000 ), PricingSettings::int_value( 'fallback_height_mm', 100, 1, 10000 ) );
		foreach ( (array) ( $package['contents'] ?? array() ) as $lineKey => $line ) {
			if ( ! is_array( $line ) || ! ( $line['data'] ?? null ) instanceof \WC_Product ) continue;
			$product = $line['data']; if ( $product->is_virtual() ) continue;
			$qty = max( 0, (int) ( $line['quantity'] ?? 0 ) ); if ( 0 === $qty ) continue;
			$parent = $product->is_type( 'variation' ) ? wc_get_product( $product->get_parent_id() ) : null;
			$meta = static function( string $key ) use ( $product, $parent ): string { $v = (string) $product->get_meta( $key, true ); if ( '' === $v && $parent instanceof \WC_Product ) $v = (string) $parent->get_meta( $key, true ); return $v; };
			$weight = (int) round( (float) wc_get_weight( max( 0.0, (float) $product->get_weight() ), 'g' ) ); if ( $weight <= 0 ) $weight = $fallbackWeight;
			$dims = array(
				(int) round( (float) wc_get_dimension( max( 0.0, (float) $product->get_length() ), 'mm' ) ),
				(int) round( (float) wc_get_dimension( max( 0.0, (float) $product->get_width() ), 'mm' ) ),
				(int) round( (float) wc_get_dimension( max( 0.0, (float) $product->get_height() ), 'mm' ) ),
			);
			if ( 'custom' === $meta( '_hm_mahex_packaging_mode' ) ) $dims = array( (int) $meta('_hm_mahex_length_mm'), (int) $meta('_hm_mahex_width_mm'), (int) $meta('_hm_mahex_height_mm') );
			foreach ( $dims as $i => $d ) if ( $d <= 0 ) $dims[ $i ] = $fallbackDims[ $i ];
			$fragile = 'yes' === $meta( '_hm_mahex_fragile' ); $liquid = 'yes' === $meta( '_hm_mahex_liquid' ); $separate = 'yes' === $meta( '_hm_mahex_ship_separately' ); $noMix = sanitize_key( $meta( '_hm_mahex_no_mix_group' ) );
			for ( $i = 0; $i < $qty; ++$i ) {
				if ( $count >= $max ) { $truncated = true; break 2; }
				$group = $separate ? 'single:' . $lineKey . ':' . $i : ( '' !== $noMix ? 'nomix:' . $noMix : ( $fragile ? 'fragile' : ( $liquid ? 'liquid' : 'normal' ) ) );
				$items[] = array( 'id'=>(string)$product->get_id().':'.$i,'product_id'=>$product->get_id(),'name'=>$product->get_name(),'weight_g'=>$weight,'dimensions_mm'=>$dims,'volume'=>max(1,$dims[0]*$dims[1]*$dims[2]),'group'=>$group,'fragile'=>$fragile,'liquid'=>$liquid,'preferred_profile'=>sanitize_key($meta('_hm_mahex_profile_id')) ); ++$count;
			}
		}
		return array( 'items' => $items, 'truncated' => $truncated );
	}

	private static function newBox( array $unit, array $profiles, array $usage ): array {
		$candidates = array();
		foreach ( $profiles as $id => $p ) {
			if ( ! is_array( $p ) ) continue;
			$stock = max( 0, (int) ( $p['stock'] ?? 0 ) ); if ( $stock <= (int) ( $usage[ $id ] ?? 0 ) ) continue;
			$d = array( max(1,(int)($p['length_mm']??0)), max(1,(int)($p['width_mm']??0)), max(1,(int)($p['height_mm']??0)) );
			$maxWeight = max( 1, (int) ( $p['max_weight_g'] ?? 0 ) ); $maxItems = max( 0, (int) ( $p['max_items'] ?? 0 ) );
			$box = array( 'profile_id'=>(string)$id,'profile_name'=>(string)($p['name']??$id),'type'=>(string)($p['type']??'custom'),'dimensions_mm'=>$d,'empty_weight_g'=>max(0,(int)($p['empty_weight_g']??0)),'max_weight_g'=>$maxWeight,'max_items'=>$maxItems,'unit_cost_irr'=>max(0,(int)($p['unit_cost_irr']??0)),'content_weight_g'=>0,'content_volume'=>0,'items'=>array() );
			if ( ! self::canFit( $box, $unit ) ) continue;
			$volume = $d[0]*$d[1]*$d[2]; $preferred = (string)($unit['preferred_profile']??'') === (string)$id ? 0 : 1;
			$candidates[] = array( 'box'=>$box,'preferred'=>$preferred,'volume'=>$volume );
		}
		usort( $candidates, static fn($a,$b)=>$a['preferred']<=>$b['preferred'] ?: $a['volume']<=>$b['volume'] );
		if ( $candidates ) return $candidates[0]['box'];
		$d = (array) $unit['dimensions_mm'];
		return array( 'profile_id'=>'custom','profile_name'=>'بسته سفارشی','type'=>'custom','dimensions_mm'=>$d,'empty_weight_g'=>0,'max_weight_g'=>PHP_INT_MAX,'max_items'=>1,'unit_cost_irr'=>0,'content_weight_g'=>0,'content_volume'=>0,'items'=>array() );
	}

	private static function canFit( array $box, array $unit ): bool {
		$maxItems = (int) ( $box['max_items'] ?? 0 ); if ( $maxItems > 0 && count( (array) ( $box['items'] ?? array() ) ) >= $maxItems ) return false;
		if ( (int) ( $box['content_weight_g'] ?? 0 ) + (int) $unit['weight_g'] > (int) ( $box['max_weight_g'] ?? PHP_INT_MAX ) ) return false;
		$bd = array_map( 'intval', (array) $box['dimensions_mm'] ); $ud = array_map( 'intval', (array) $unit['dimensions_mm'] ); sort($bd); sort($ud); if ( $ud[0]>$bd[0] || $ud[1]>$bd[1] || $ud[2]>$bd[2] ) return false;
		$waste = Config::float( 'packing_waste_percent', 10, 0, 80 ); $capacity = (int) floor( max(1,$bd[0]*$bd[1]*$bd[2]) * (1-$waste/100) );
		return (int) ( $box['content_volume'] ?? 0 ) + (int) $unit['volume'] <= max( 1, $capacity );
	}

	private static function place( array $box, array $unit ): array {
		$box['content_weight_g'] = (int) ( $box['content_weight_g'] ?? 0 ) + (int) $unit['weight_g'];
		$box['content_volume'] = (int) ( $box['content_volume'] ?? 0 ) + (int) $unit['volume'];
		$box['items'][] = array( 'id'=>$unit['id'],'product_id'=>$unit['product_id'],'name'=>$unit['name'],'weight_g'=>$unit['weight_g'],'dimensions_mm'=>$unit['dimensions_mm'] );
		return $box;
	}
}
