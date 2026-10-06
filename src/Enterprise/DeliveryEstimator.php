<?php

namespace HoseinMomeni\MahexWoo\Enterprise;

use HoseinMomeni\MahexWoo\Shipping\PricingSettings;

final class DeliveryEstimator {
	/** @return array{days:int,date:string,label:string,warehouse_id:string} */
	public static function estimate( array $package, int $serviceDays = -1 ): array {
		$dest=is_array($package['destination']??null)?$package['destination']:array();$province=PricingSettings::province_name((string)($dest['state']??''));$city=sanitize_text_field((string)($dest['city']??''));
		$warehouseId=sanitize_key((string)($package['hm_mahex_warehouse_id']??''));$warehouse=$warehouseId?WarehouseRepository::get($warehouseId):WarehouseRouter::select($province,$city);if(!$warehouse)$warehouse=array('id'=>'','prep_days'=>0);
		$lead=self::maxProductLead($package);$prep=max(0,(int)($warehouse['prep_days']??0));$sla=$serviceDays>=0?$serviceDays:self::slaDays($province,$city);$days=max(0,$lead+$prep+$sla);
		$start=new \DateTimeImmutable('now',wp_timezone());$cut=(string)Config::get('cutoff_time','14:00');if($start->format('H:i')>$cut||!self::isBusinessDay($start))++$days;
		$date=self::addBusinessDays($start,$days);$label=0===$days?'تحویل امروز':'تحویل تقریبی تا '.$date->format('Y/m/d');
		return array('days'=>$days,'date'=>$date->format('Y-m-d'),'label'=>$label,'warehouse_id'=>(string)($warehouse['id']??''));
	}

	private static function slaDays(string $province,string $city): int {
		$default=Config::int('sla_default_days',3,0,30);$lines=preg_split('/\r\n|\r|\n/',(string)Config::get('sla_rules',''))?:array();$norm=static fn($v)=>trim(str_replace(array('ي','ك'),array('ی','ک'),(string)$v));
		foreach($lines as $line){$p=array_map('trim',explode('|',$line));if(count($p)<3)continue;if(''!==$p[0]&&$norm($p[0])!==$norm($province))continue;if(''!==$p[1]&&!str_contains($norm($city),$norm($p[1])))continue;return max(0,min(30,(int)$p[2]));}return $default;
	}

	private static function maxProductLead(array $package): int {
		$max=0;foreach((array)($package['contents']??array()) as $item){$p=is_array($item)?($item['data']??null):null;if(!$p instanceof \WC_Product)continue;$v=$p->get_meta('_hm_mahex_lead_time_days',true);if(''===$v&&$p->is_type('variation')){$parent=wc_get_product($p->get_parent_id());if($parent)$v=$parent->get_meta('_hm_mahex_lead_time_days',true);}$max=max($max,min(30,absint($v)));}return $max;
	}

	private static function isBusinessDay(\DateTimeImmutable $date): bool { $weekends=array_map('intval',preg_split('/[, ]+/',(string)Config::get('weekend_days','5'))?:array(5));$holidays=array_flip(Config::csv('holidays'));return !in_array((int)$date->format('N'),$weekends,true)&&!isset($holidays[$date->format('Y-m-d')]); }

	private static function addBusinessDays(\DateTimeImmutable $date,int $days): \DateTimeImmutable {
		$weekends=array_map('intval',preg_split('/[, ]+/',(string)Config::get('weekend_days','5'))?:array(5));$holidays=array_flip(Config::csv('holidays'));$added=0;$guard=0;
		while($added<$days&&$guard<120){$date=$date->modify('+1 day');++$guard;$n=(int)$date->format('N');$ymd=$date->format('Y-m-d');if(in_array($n,$weekends,true)||isset($holidays[$ymd]))continue;++$added;}return $date;
	}
}
