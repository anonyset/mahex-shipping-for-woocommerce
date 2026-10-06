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
	public static function allocation( \WC_Order $order ): array {
        $a=\HoseinMomeni\MahexWoo\V31\Finance::allocation($order);
        $packing=$a['components']['packaging'];
        // Legacy consumers expect separate revenue buckets and numeric estimates.
        // Confirmed profit is available only when every actual cost is recorded.
        return array('shipping_revenue'=>$a['revenue']-$packing,'packaging_revenue'=>$packing,
          'carrier_cost'=>$a['costs']['carrier']??0.0,
          'operational_cost'=>($a['costs']['operational']??0.0)+($a['costs']['packaging']??0.0)+($a['costs']['insurance']??0.0),
          'profit'=>$a['profit']??0.0,'profit_known'=>$a['complete']);
    }
}
