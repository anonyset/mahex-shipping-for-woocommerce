<?php

namespace HoseinMomeni\MahexWoo\V1;

use HoseinMomeni\MahexWoo\Admin\PackagingProfiles;
use HoseinMomeni\MahexWoo\Rates\PricingSettings;

final class SetupVerifier {
	public static function checks(): array {
		$settings=(array)get_option('hm_mahex_settings',array());$pricing=PricingSettings::normalizeStored(get_option(PricingSettings::OPTION_NAME,array()));$profiles=get_option(PackagingProfiles::OPTION_NAME,array());$profiles=is_array($profiles)?$profiles:array();$warehouses=get_option('hm_mahex_warehouses',array());$warehouses=is_array($warehouses)?$warehouses:array();
		return array(
			array('key'=>'woocommerce','ok'=>defined('WC_VERSION'),'label'=>'ووکامرس','detail'=>defined('WC_VERSION')?WC_VERSION:'لود نشده'),
			array('key'=>'currency','ok'=>in_array(get_woocommerce_currency(),array('IRR','IRT'),true),'label'=>'واحد پول','detail'=>get_woocommerce_currency()),
			array('key'=>'sender','ok'=>!empty($settings['sender_name'])&&!empty($settings['origin_city'])&&!empty($settings['origin_address']),'label'=>'فرستنده و مبدا','detail'=>!empty($settings['sender_name'])?'ثبت شده':'ناقص'),
			array('key'=>'pricing','ok'=>(int)$pricing['manual_base_cost']>0||(int)$pricing['fallback_cost']>0,'label'=>'نرخ پایه','detail'=>(string)(int)$pricing['manual_base_cost']),
			array('key'=>'profiles','ok'=>count($profiles)>0,'label'=>'پروفایل بسته‌بندی','detail'=>count($profiles).' پروفایل'),
			array('key'=>'warehouses','ok'=>count($warehouses)>0,'label'=>'انبارها','detail'=>count($warehouses).' انبار'),
			array('key'=>'cron','ok'=>!(defined('DISABLE_WP_CRON')&&DISABLE_WP_CRON),'label'=>'WP-Cron','detail'=>(defined('DISABLE_WP_CRON')&&DISABLE_WP_CRON)?'غیرفعال':'فعال'),
			array('key'=>'safe_mode','ok'=>!SafeMode::active(),'label'=>'Safe Mode','detail'=>SafeMode::active()?'فعال شده':'عادی'),
		);
	}
	public static function ready(): bool {foreach(self::checks() as $c)if(empty($c['ok'])&&in_array($c['key'],array('woocommerce','sender','pricing'),true))return false;return true;}
}
