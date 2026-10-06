<?php

namespace HoseinMomeni\MahexWoo\V2;

final class ReleaseQualification {
	public static function run(): array {
		$tests=array();$add=static function(string $name,bool $ok,string $detail='')use(&$tests){$tests[]=compact('name','ok','detail');};
		$add('Version constant',defined('HM_MAHEX_VERSION')&&version_compare(HM_MAHEX_VERSION,'2.0.0','>='),defined('HM_MAHEX_VERSION')?HM_MAHEX_VERSION:'missing');
		$add('PHP >= 8.1',version_compare(PHP_VERSION,'8.1','>='),PHP_VERSION);
		$add('V2 schema',(string)get_option('hm_mahex_v2_schema','')===Schema::VERSION,(string)get_option('hm_mahex_v2_schema',''));
		foreach(array('hm_mahex_v2_events','hm_mahex_v2_daily_metrics','hm_mahex_v2_problems','hm_mahex_v2_returns','hm_mahex_v2_shifts','hm_mahex_v2_parcels') as $table)$add('Table '.$table,self::tableExists($table));
		$k=RateCache::key(array('destination'=>array('state'=>'THR'),'contents'=>array(),'contents_cost'=>0),0);$add('Rate cache key',str_starts_with($k,'r_')&&strlen($k)>30);
		$add('Module registry',count(ModuleRegistry::modules())>=5);
		$add('No external API module',!is_dir(dirname(__DIR__,2).'/src/API'));
		$passed=count(array_filter($tests,static fn($t)=>!empty($t['ok'])));return array('passed'=>$passed,'total'=>count($tests),'ok'=>$passed===count($tests),'tests'=>$tests,'ran_at'=>gmdate(DATE_ATOM));
	}
	private static function tableExists(string $suffix): bool {global $wpdb;$t=$wpdb->prefix.$suffix;return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$t))===$t;}
}
