<?php

namespace HoseinMomeni\MahexWoo\V1;

final class Reports {
	public static function summary( int $days = 30 ): array {
		global $wpdb; $days=max(1,min(3650,$days));$t=$wpdb->prefix.'hm_mahex_order_index';
		$row=$wpdb->get_row($wpdb->prepare("SELECT COUNT(*) orders,SUM(shipping_total) shipping,SUM(packaging_total) packaging,SUM(carrier_cost) carrier,SUM(profit) profit,SUM(CASE WHEN shipment_status='returned' THEN carrier_cost ELSE 0 END) return_cost FROM $t WHERE created_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL %d DAY)",$days),ARRAY_A)?:array();
		return array('orders'=>(int)($row['orders']??0),'shipping'=>(float)($row['shipping']??0),'packaging'=>(float)($row['packaging']??0),'carrier'=>(float)($row['carrier']??0),'profit'=>(float)($row['profit']??0),'return_cost'=>(float)($row['return_cost']??0));
	}
	public static function cities( int $days=30, int $limit=20 ): array {global $wpdb;$t=$wpdb->prefix.'hm_mahex_order_index';return $wpdb->get_results($wpdb->prepare("SELECT province,city,COUNT(*) orders,SUM(shipping_total+packaging_total) revenue,SUM(carrier_cost) cost,SUM(profit) profit FROM $t WHERE created_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL %d DAY) GROUP BY province,city ORDER BY profit DESC LIMIT %d",max(1,$days),max(1,min(200,$limit))),ARRAY_A)?:array();}
	public static function customers( int $days=30, int $limit=20 ): array {global $wpdb;$t=$wpdb->prefix.'hm_mahex_order_index';return $wpdb->get_results($wpdb->prepare("SELECT customer_name,phone,COUNT(*) orders,SUM(shipping_total+packaging_total) revenue,SUM(carrier_cost) cost,SUM(profit) profit FROM $t WHERE created_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL %d DAY) GROUP BY customer_name,phone ORDER BY profit DESC LIMIT %d",max(1,$days),max(1,min(200,$limit))),ARRAY_A)?:array();}
	public static function allocation( \WC_Order $order ): array { $shipping=(float)$order->get_shipping_total();$packing=0.0;foreach($order->get_items('shipping') as $item){$v=$item->get_meta('hm_mahex_packaging',true);if(is_numeric($v))$packing+=(float)$v;}$carrier=$order->get_meta('_hm_mahex_actual_carrier_cost',true);$carrier=is_numeric($carrier)?(float)$carrier:0.0;$operational=max(0,(float)$order->get_meta('_hm_mahex_operational_cost',true));return array('shipping_revenue'=>$shipping,'packaging_revenue'=>$packing,'carrier_cost'=>$carrier,'operational_cost'=>$operational,'profit'=>$shipping+$packing-$carrier-$operational); }
}
