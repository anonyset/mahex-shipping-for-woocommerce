<?php

namespace HoseinMomeni\MahexWoo\V25;

final class Stats {
	public static function indexed(int $days=90): array { global $wpdb;$t=$wpdb->prefix.'hm_mahex_order_index';$days=max(1,min(3650,$days));return $wpdb->get_results($wpdb->prepare("SELECT * FROM $t WHERE created_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL %d DAY) ORDER BY created_at DESC",$days),ARRAY_A)?:array(); }
	public static function median(array $values): float {$values=array_values(array_filter(array_map('floatval',$values),static fn($v)=>is_finite($v)));if(!$values)return 0.0;sort($values,SORT_NUMERIC);$n=count($values);$m=intdiv($n,2);return $n%2?$values[$m]:($values[$m-1]+$values[$m])/2;}
	public static function productScores(int $days=90,int $limit=30): array {
		$orders=wc_get_orders(array('limit'=>500,'date_created'=>'>'.gmdate('Y-m-d',strtotime("-$days days")),'orderby'=>'date','order'=>'DESC','return'=>'objects'));$agg=array();
		foreach($orders as $o){if(!$o instanceof \WC_Order)continue;$allocation=\HoseinMomeni\MahexWoo\V1\Reports::allocation($o);$qty=max(1,array_sum(array_map(static fn($i)=>(int)$i->get_quantity(),$o->get_items())));$share=(float)$allocation['profit']/$qty;foreach($o->get_items() as $item){$pid=$item->get_product_id();if(!$pid)continue;$q=max(1,(int)$item->get_quantity());if(!isset($agg[$pid]))$agg[$pid]=array('product_id'=>$pid,'name'=>$item->get_name(),'qty'=>0,'orders'=>0,'profit'=>0.0);$agg[$pid]['qty']+=$q;$agg[$pid]['orders']++;$agg[$pid]['profit']+=$share*$q;}}
		usort($agg,static fn($a,$b)=>$b['profit']<=>$a['profit']);return array_slice($agg,0,max(1,min(200,$limit)));
	}
	public static function orderAgeHours(\WC_Order $order): float {$d=$order->get_date_created();return $d?max(0,(time()-$d->getTimestamp())/HOUR_IN_SECONDS):0;}
}
