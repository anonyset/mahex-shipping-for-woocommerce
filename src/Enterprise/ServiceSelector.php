<?php

namespace HoseinMomeni\MahexWoo\Enterprise;

final class ServiceSelector {
	public static function availableDestination( string $province, string $city, int $weightG ): bool {
		$n=static fn($v)=>trim(str_replace(array('ي','ك'),array('ی','ک'),(string)$v)); $province=$n($province);$city=$n($city);
		if($weightG>Config::int('max_service_weight_g',100000,1,5000000))return false;
		foreach(Config::csv('excluded_provinces') as $v)if($n($v)===$province)return false;
		foreach(Config::csv('excluded_cities') as $v)if($n($v)===$city)return false;
		return null!==WarehouseRouter::select($province,$city);
	}

	/** @return list<array{id:string,label:string,sla_days:int,max_weight_g:int,surcharge_irr:float}> */
	public static function localServices( int $weightG ): array {
		$out=array();$lines=preg_split('/\r\n|\r|\n/',(string)Config::get('local_services',''))?:array();
		foreach($lines as $line){$p=array_map('trim',explode('|',$line));if(count($p)<5)continue;$id=sanitize_key($p[0]);$label=sanitize_text_field($p[1]);$sla=max(0,min(30,(int)$p[2]));$max=max(0,(int)$p[3]);$sur=max(0,(float)$p[4]);if(''!==$id&&''!==$label&&($max<=0||$weightG<=$max))$out[]=array('id'=>$id,'label'=>$label,'sla_days'=>$sla,'max_weight_g'=>$max,'surcharge_irr'=>$sur);}
		return $out;
	}

	public static function chooseApiService( array $services ): ?array {
		$rows=array_values(array_filter($services,'is_array')); if(!$rows)return null;$policy=(string)Config::get('service_policy','balanced');
		$price=static fn($r)=>(float)($r['freight']??$r['price']??$r['amount']??PHP_INT_MAX);$days=static fn($r)=>(int)($r['sla_days']??$r['delivery_days']??99);
		usort($rows,static function($a,$b)use($policy,$price,$days){if('cheapest'===$policy)return $price($a)<=>$price($b);if('fastest'===$policy)return ($days($a)<=>$days($b))?:($price($a)<=>$price($b));return (($days($a)*1000000+$price($a))<=>($days($b)*1000000+$price($b)));});
		return $rows[0]??null;
	}
}
