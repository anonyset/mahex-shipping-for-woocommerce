<?php

namespace HoseinMomeni\MahexWoo\Enterprise;

final class OrderWarehousePlanner {
	/** @return list<array<string,mixed>> */
	public static function plan(\WC_Order $order): array {
		$province=(string)($order->get_shipping_state()?:$order->get_billing_state());$city=(string)($order->get_shipping_city()?:$order->get_billing_city());$default=WarehouseRouter::select($province,$city);$groups=array();
		foreach($order->get_items() as $item){$product=$item->get_product();$wid=$product instanceof \WC_Product?WarehouseRouter::resolveProductWarehouse($product,$province,$city):'';if(''===$wid)$wid=(string)($default['id']??'default');$groups[$wid][]=$item;}
		$out=array();foreach($groups as $wid=>$items){$w=WarehouseRepository::get($wid)??$default??array('id'=>$wid,'name'=>$wid);$lines=array();foreach($items as $item)$lines[]=array('product_id'=>$item->get_product_id(),'variation_id'=>$item->get_variation_id(),'name'=>$item->get_name(),'quantity'=>(int)$item->get_quantity(),'total'=>(float)$item->get_total());$out[]=array('warehouse'=>$w,'items'=>$lines,'item_count'=>array_sum(array_column($lines,'quantity')));}
		return $out;
	}
}
