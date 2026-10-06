<?php

namespace HoseinMomeni\MahexWoo\Enterprise;

use HoseinMomeni\MahexWoo\Shipping\PricingSettings;

final class WarehouseRouter {
	public static function register(): void {
		add_filter( 'woocommerce_cart_shipping_packages', array( self::class, 'splitPackages' ), 30 );
	}

	public static function select( string $province, string $city ): ?array {
		$province = PricingSettings::province_name( $province ); $city = trim( $city ); $target = Geo::provincePoint( $province ); $candidates = array();
		foreach ( WarehouseRepository::all() as $row ) {
			if ( empty( $row['enabled'] ) || ! self::covers( $row, $province, $city ) ) continue;
			$score = 1000000.0;
			if ( trim( (string) ( $row['province'] ?? '' ) ) === $province ) $score = 0;
			if ( $target && (float)($row['lat']??0) && (float)($row['lng']??0) ) $score = Geo::distanceKm((float)$row['lat'],(float)$row['lng'],$target[0],$target[1]);
			$candidates[] = array( 'score'=>$score, 'row'=>$row );
		}
		usort( $candidates, static fn($a,$b)=>$a['score']<=>$b['score'] ); return $candidates[0]['row'] ?? null;
	}

	public static function splitPackages( array $packages ): array {
		if ( ! Config::bool( 'enabled', true ) || ! Config::bool( 'multi_warehouse', true ) ) return $packages;
		$out = array();
		foreach ( $packages as $package ) {
			if ( ! is_array( $package ) || ! is_array( $package['contents'] ?? null ) ) { $out[]=$package; continue; }
			$dest = is_array($package['destination']??null)?$package['destination']:array();
			$default = self::select((string)($dest['state']??''),(string)($dest['city']??'')); $groups=array();
			foreach ( $package['contents'] as $key=>$item ) {
				$product = is_array($item) ? ($item['data']??null) : null;
				$id = $product instanceof \WC_Product ? self::productWarehouse($product) : ''; if(''===$id) $id=(string)($default['id']??'default');
				$groups[$id][$key]=$item;
			}
			if ( count($groups)<=1 ) { $package['hm_mahex_warehouse_id']=(string)array_key_first($groups ?: array('default'=>array())); $out[]=$package; continue; }
			foreach($groups as $id=>$contents){$copy=$package;$copy['contents']=$contents;$copy['contents_cost']=array_sum(array_map(static fn($i)=>is_array($i)&&isset($i['line_total'])?(float)$i['line_total']:0.0,$contents));$copy['hm_mahex_warehouse_id']=$id;$out[]=$copy;}
		}
		return $out;
	}

	public static function resolveProductWarehouse( \WC_Product $product, string $province, string $city ): string {
		$id=self::productWarehouse($product);if(''===$id)return '';
		$row=WarehouseRepository::get($id);if(!$row||empty($row['enabled'])||!self::covers($row,PricingSettings::province_name($province),$city))return '';
		return $id;
	}

	public static function productWarehouse( \WC_Product $product ): string {
		$v=(string)$product->get_meta('_hm_mahex_warehouse_id',true); if(''===$v && $product->is_type('variation')){$p=wc_get_product($product->get_parent_id());if($p)$v=(string)$p->get_meta('_hm_mahex_warehouse_id',true);} return sanitize_key($v);
	}

	private static function covers(array $w,string $province,string $city): bool {
		$p=(array)($w['coverage_provinces']??array());$c=(array)($w['coverage_cities']??array()); if(!$p&&!$c)return true;
		$norm=static fn($v)=>trim(str_replace(array('ي','ك'),array('ی','ک'),(string)$v));
		if($p && !in_array($norm($province),array_map($norm,$p),true))return false; if($c && !in_array($norm($city),array_map($norm,$c),true))return false; return true;
	}
}
