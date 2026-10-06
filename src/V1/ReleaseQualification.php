<?php

namespace HoseinMomeni\MahexWoo\V1;

final class ReleaseQualification {
	public static function run(): array {
		$tests=array();$add=static function(string $name,bool $ok,string $detail='')use(&$tests){$tests[]=compact('name','ok','detail');};
		$add('PHP >= 8.1',version_compare(PHP_VERSION,'8.1','>='),PHP_VERSION);
		$add('WooCommerce loaded',class_exists('WooCommerce'));
		$add('V1 schema', (string)get_option('hm_mahex_v1_schema','')===Schema::VERSION,(string)get_option('hm_mahex_v1_schema',''));
		$m=MarginEngine::apply(120000);$add('Margin engine numeric',is_numeric($m['freight'])&&$m['freight']>=0,(string)$m['freight']);
		$san=RuleEngine::sanitizeRules(array(array('name'=>'test','active'=>1,'priority'=>10,'logic'=>'AND','mode'=>'surcharge','amount'=>1000)));$add('Rule sanitizer',count($san)===1&&$san[0]['amount']===1000.0);
		$add('Local-only architecture',!is_dir(dirname(__DIR__,2).'/src/API'),'External API module is absent');
		$add('Safe mode available',class_exists(SafeMode::class));
		$add('Order index table',self::tableExists('hm_mahex_order_index'));
		$add('Inventory ledger table',self::tableExists('hm_mahex_inventory_ledger'));
		$add('Print queue table',self::tableExists('hm_mahex_print_queue'));
		$add('Packing sessions table',self::tableExists('hm_mahex_packing_sessions'));
		$pro=(array)get_option('hm_mahex_pro_settings',array()); $enterprise=(array)get_option('hm_mahex_enterprise_settings',array());
		$externalKeys=array('api_enabled','api_token','api_base_url','api_create_path','api_quote_path','api_track_path','api_cancel_path','auto_sync_status','webhook_enabled','webhook_secret','webhook_signature_header','api_log_enabled','api_log_body','rest_enabled','circuit_threshold','circuit_cooldown','rate_limit_per_minute');
		$legacyFree=true; foreach($externalKeys as $key){ if(array_key_exists($key,$pro)||array_key_exists($key,$enterprise)){ $legacyFree=false; break; } }
		$add('Legacy external settings removed',$legacyFree);
		$add('Version constant',defined('HM_MAHEX_VERSION')&&version_compare(HM_MAHEX_VERSION,'1.0.0','>='),defined('HM_MAHEX_VERSION')?HM_MAHEX_VERSION:'missing');
		$passed=count(array_filter($tests,static fn($t)=>!empty($t['ok'])));return array('passed'=>$passed,'total'=>count($tests),'ok'=>$passed===count($tests),'tests'=>$tests,'ran_at'=>gmdate(DATE_ATOM));
	}
	private static function tableExists(string $suffix): bool {global $wpdb;$t=$wpdb->prefix.$suffix;return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$t))===$t;}
}
