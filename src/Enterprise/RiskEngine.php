<?php

namespace HoseinMomeni\MahexWoo\Enterprise;

final class RiskEngine {
	public static function register(): void { add_action('woocommerce_checkout_create_order',array(self::class,'scoreOrder'),30,2); }
	public static function scoreOrder(\WC_Order $order,array $data=array()): void {
		$phone=AddressTools::normalizePhone((string)($order->get_shipping_phone()?:$order->get_billing_phone()));$postcode=preg_replace('/\D/','',AddressTools::digits((string)($order->get_shipping_postcode()?:$order->get_billing_postcode())))?:'';$score=0;$reasons=array();
		if(self::listed($phone,Config::csv('whitelist_phones'))){$score=0;$reasons[]='whitelist';}else{
			if(self::listed($phone,Config::csv('blacklist_phones'))){$score+=70;$reasons[]='blacklisted_phone';}if(self::prefixListed($postcode,Config::csv('blacklist_postcodes'))){$score+=50;$reasons[]='blacklisted_postcode';}
			if(!AddressTools::validPhone($phone)){$score+=20;$reasons[]='invalid_phone';}if(''!==$postcode&&!AddressTools::validPostcode($postcode)){$score+=15;$reasons[]='invalid_postcode';}
			if('cod'===$order->get_payment_method()&&(float)$order->get_total()>50000000){$score+=15;$reasons[]='high_value_cod';}
			$previous=self::failedOrders($phone);if($previous>0){$score+=min(30,$previous*10);$reasons[]='previous_failed_deliveries:'.$previous;}
			$duplicates=self::duplicateAddressSignals($order,$phone);if($duplicates>0){$score+=min(20,$duplicates*5);$reasons[]='duplicate_address_signal:'.$duplicates;}
		}
		$score=min(100,$score);$order->update_meta_data('_hm_mahex_risk_score',$score);$order->update_meta_data('_hm_mahex_risk_reasons',$reasons);if($score>=Config::int('risk_hold_threshold',80,0,100))$order->update_meta_data('_hm_mahex_risk_review','yes');
	}
	private static function failedOrders(string $phone): int {if(''===$phone)return 0;$orders=wc_get_orders(array('limit'=>10,'orderby'=>'date','order'=>'DESC','status'=>array('wc-cancelled','wc-refunded','wc-failed')));$n=0;foreach($orders as $o)if($o instanceof \WC_Order&&AddressTools::normalizePhone($o->get_billing_phone())===$phone)++$n;return $n;}
	private static function duplicateAddressSignals(\WC_Order $order,string $phone): int {
		$address=AddressTools::cleanText(trim((string)($order->get_shipping_address_1()?:$order->get_billing_address_1())).' '.trim((string)($order->get_shipping_city()?:$order->get_billing_city())));
		if(''===$address)return 0;$orders=wc_get_orders(array('limit'=>30,'orderby'=>'date','order'=>'DESC','status'=>array_keys(wc_get_order_statuses())));$n=0;foreach($orders as $old){if(!$old instanceof \WC_Order)continue;$oldAddress=AddressTools::cleanText(trim((string)($old->get_shipping_address_1()?:$old->get_billing_address_1())).' '.trim((string)($old->get_shipping_city()?:$old->get_billing_city())));if($oldAddress!==$address)continue;$oldPhone=AddressTools::normalizePhone((string)($old->get_shipping_phone()?:$old->get_billing_phone()));if(''!==$oldPhone&&$oldPhone!==$phone)++$n;}return min(4,$n);
	}
	private static function listed(string $v,array $list): bool {foreach($list as $x)if(AddressTools::normalizePhone((string)$x)===$v&&''!==$v)return true;return false;}
	private static function prefixListed(string $v,array $list): bool {foreach($list as $x){$x=preg_replace('/\D/','',AddressTools::digits((string)$x))?:'';if(''!==$x&&str_starts_with($v,$x))return true;}return false;}
}
