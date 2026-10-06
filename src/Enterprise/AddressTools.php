<?php

namespace HoseinMomeni\MahexWoo\Enterprise;

use HoseinMomeni\MahexWoo\Shipping\PricingSettings;

final class AddressTools {
	public static function register(): void {
		add_filter('woocommerce_checkout_posted_data',array(self::class,'normalizePosted'),20);
		add_action('woocommerce_after_checkout_validation',array(self::class,'validateClassic'),20,2);
		add_action('woocommerce_checkout_create_order',array(self::class,'normalizeOrder'),10,2);add_action('woocommerce_store_api_checkout_update_order_from_request',array(self::class,'normalizeBlockOrder'),20,2);add_action('woocommerce_store_api_checkout_update_order_meta',array(self::class,'validateBlockOrder'),20,1);
	}

	public static function digits(string $v): string { return strtr($v,array('۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9')); }
	public static function cleanText(string $v): string {$v=str_replace(array("\xE2\x80\x8C",'ي','ك'),array(' ','ی','ک'),$v);$v=preg_replace('/[\x{200E}\x{200F}\x{202A}-\x{202E}]/u','',$v)??$v;return trim(preg_replace('/\s+/u',' ',$v)??$v);}
	public static function normalizePhone(string $v): string {$v=self::digits($v);$v=preg_replace('/[^0-9+]/','',$v)?:'';if(str_starts_with($v,'+98'))$v='0'.substr($v,3);elseif(str_starts_with($v,'0098'))$v='0'.substr($v,4);elseif(strlen($v)===10&&str_starts_with($v,'9'))$v='0'.$v;return $v;}
	public static function validPhone(string $v): bool {$v=self::normalizePhone($v);return 1===preg_match('/^09\d{9}$/',$v);}
	public static function validPostcode(string $v): bool {$v=preg_replace('/\D/','',self::digits($v))?:'';return strlen($v)===10&&!preg_match('/^(\d)\1{9}$/',$v)&&!preg_match('/^0{5}/',$v);}
	public static function normalizeCity(string $city): string {$city=self::cleanText($city);foreach(self::pairs((string)Config::get('city_aliases','')) as $from=>$to)if(self::cleanText($from)===self::cleanText($city))return self::cleanText($to);return $city;}

	public static function normalizePosted(array $data): array {
		foreach(array('billing_phone','shipping_phone') as $k)if(isset($data[$k]))$data[$k]=self::normalizePhone((string)$data[$k]);
		foreach(array('billing_postcode','shipping_postcode') as $k)if(isset($data[$k]))$data[$k]=preg_replace('/\D/','',self::digits((string)$data[$k]))?:'';
		foreach(array('billing_city','shipping_city') as $k)if(isset($data[$k]))$data[$k]=self::normalizeCity((string)$data[$k]);
		foreach(array('billing_address_1','billing_address_2','shipping_address_1','shipping_address_2','billing_first_name','billing_last_name','shipping_first_name','shipping_last_name') as $k)if(isset($data[$k]))$data[$k]=self::cleanText((string)$data[$k]);
		return $data;
	}

	public static function validateClassic(array $data,\WP_Error $errors): void {
		if ( ! self::mahexSelectedClassic( $data ) ) return;
		$shipping=!empty($data['ship_to_different_address']);$pre=$shipping?'shipping_':'billing_';$phone=(string)($data[$pre.'phone']??$data['billing_phone']??'');$postcode=(string)($data[$pre.'postcode']??'');$address1=trim((string)($data[$pre.'address_1']??''));$address2=trim((string)($data[$pre.'address_2']??''));$city=self::normalizeCity((string)($data[$pre.'city']??''));$province=PricingSettings::province_name((string)($data[$pre.'state']??''));
		if(''===$address1)$errors->add('hm_mahex_address','برای ارسال ماهکس، آدرس خیابان/پلاک الزامی است.');
		elseif(Config::bool('require_plate')&&!self::hasPlate($address1))$errors->add('hm_mahex_plate','شماره پلاک را در نشانی وارد کنید.');
		if(Config::bool('require_address2')&&''===$address2)$errors->add('hm_mahex_address2','واحد/جزئیات تکمیلی آدرس را وارد کنید.');
		if(Config::bool('require_phone')&&!self::validPhone($phone))$errors->add('hm_mahex_phone','شماره موبایل گیرنده معتبر نیست.');
		if(Config::bool('require_postcode')&&''===$postcode)$errors->add('hm_mahex_postcode','کدپستی گیرنده الزامی است.');elseif(Config::bool('validate_postcode')&&''!==$postcode&&!self::validPostcode($postcode))$errors->add('hm_mahex_postcode_invalid','کدپستی باید ۱۰ رقم معتبر باشد.');
		if(!self::cityProvinceMatches($city,$province))$errors->add('hm_mahex_city_province','شهر و استان انتخاب‌شده با قوانین مقصد فروشگاه سازگار نیستند.');
	}

	public static function normalizeBlockOrder(\WC_Order $order, \WP_REST_Request $request): void { self::normalizeOrder( $order, array() ); }
	public static function validateBlockOrder(\WC_Order $order): void {
		if ( ! self::mahexSelectedOrder( $order ) ) return;
		$phone=method_exists($order,'get_shipping_phone')&&$order->get_shipping_phone()?$order->get_shipping_phone():$order->get_billing_phone();$postcode=$order->get_shipping_postcode()?:$order->get_billing_postcode();$city=self::normalizeCity($order->get_shipping_city()?:$order->get_billing_city());$province=PricingSettings::province_name($order->get_shipping_state()?:$order->get_billing_state());$address1=$order->get_shipping_address_1()?:$order->get_billing_address_1();$address2=$order->get_shipping_address_2()?:$order->get_billing_address_2();
		$message='';if(''===trim($address1))$message='برای ارسال ماهکس، آدرس خیابان/پلاک الزامی است.';elseif(Config::bool('require_plate')&&!self::hasPlate((string)$address1))$message='شماره پلاک را در نشانی وارد کنید.';elseif(Config::bool('require_address2')&&''===trim($address2))$message='واحد/جزئیات تکمیلی آدرس را وارد کنید.';elseif(Config::bool('require_phone')&&!self::validPhone((string)$phone))$message='شماره موبایل گیرنده معتبر نیست.';elseif(Config::bool('require_postcode')&&''===trim((string)$postcode))$message='کدپستی گیرنده الزامی است.';elseif(Config::bool('validate_postcode')&&''!==trim((string)$postcode)&&!self::validPostcode((string)$postcode))$message='کدپستی باید ۱۰ رقم معتبر باشد.';elseif(!self::cityProvinceMatches($city,$province))$message='شهر و استان انتخاب‌شده با قوانین مقصد فروشگاه سازگار نیستند.';
		if(''!==$message){$class='\\Automattic\\WooCommerce\\StoreApi\\Exceptions\\RouteException';if(class_exists($class))throw new $class('hm_mahex_invalid_address',$message,400);throw new \RuntimeException($message);}
	}

	public static function normalizeOrder(\WC_Order $order,array $data): void {
		$order->set_billing_first_name(self::cleanText($order->get_billing_first_name()));$order->set_billing_last_name(self::cleanText($order->get_billing_last_name()));$order->set_shipping_first_name(self::cleanText($order->get_shipping_first_name()));$order->set_shipping_last_name(self::cleanText($order->get_shipping_last_name()));
		$order->set_billing_address_1(self::cleanText($order->get_billing_address_1()));$order->set_billing_address_2(self::cleanText($order->get_billing_address_2()));$order->set_shipping_address_1(self::cleanText($order->get_shipping_address_1()));$order->set_shipping_address_2(self::cleanText($order->get_shipping_address_2()));
		$order->set_billing_phone(self::normalizePhone($order->get_billing_phone()));if(method_exists($order,'get_shipping_phone')&&method_exists($order,'set_shipping_phone'))$order->set_shipping_phone(self::normalizePhone((string)$order->get_shipping_phone()));
		$order->set_billing_city(self::normalizeCity($order->get_billing_city()));$order->set_shipping_city(self::normalizeCity($order->get_shipping_city()));$order->set_billing_postcode(preg_replace('/\D/','',self::digits($order->get_billing_postcode()))?:'');$order->set_shipping_postcode(preg_replace('/\D/','',self::digits($order->get_shipping_postcode()))?:'');
	}

	private static function hasPlate(string $address): bool { $address=self::digits(self::cleanText($address)); return str_contains($address,'پلاک') || 1===preg_match('/(?:^|\s)\d{1,4}(?:\s|$|،|,)/u',$address); }
	private static function mahexSelectedClassic(array $data): bool { $methods=$data['shipping_method']??($_POST['shipping_method']??array());$methods=is_array($methods)?$methods:array($methods);foreach($methods as $method)if(str_starts_with(sanitize_text_field((string)$method),'hm_mahex'))return true;return false; }
	private static function mahexSelectedOrder(\WC_Order $order): bool { foreach($order->get_shipping_methods() as $item){if(method_exists($item,'get_method_id')&&'hm_mahex'===$item->get_method_id())return true;}return false; }
	private static function cityProvinceMatches(string $city,string $province): bool {$rules=self::pairs((string)Config::get('city_province_rules',''));if(!$rules||''===$city)return true;foreach($rules as $c=>$p)if(self::cleanText($c)===self::cleanText($city))return self::cleanText($p)===self::cleanText($province);return true;}
	private static function pairs(string $raw): array {$out=array();foreach(preg_split('/\r\n|\r|\n/',$raw)?:array() as $line){$p=array_map('trim',explode('|',$line,2));if(count($p)===2&&''!==$p[0]&&''!==$p[1])$out[$p[0]]=$p[1];}return $out;}
}
