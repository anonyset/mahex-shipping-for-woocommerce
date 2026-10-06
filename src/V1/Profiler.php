<?php

namespace HoseinMomeni\MahexWoo\V1;

final class Profiler {
	private static array $starts = array();
	private const OPTION = 'hm_mahex_v1_profile_samples';

	public static function start( string $key ): void { if(Config::bool('profiler_enabled',false))self::$starts[$key]=microtime(true); }
	public static function stop( string $key, array $context = array() ): void {
		if(!Config::bool('profiler_enabled',false)||!isset(self::$starts[$key]))return;$ms=(microtime(true)-self::$starts[$key])*1000;unset(self::$starts[$key]);
		$samples=get_option(self::OPTION,array());$samples=is_array($samples)?$samples:array();array_unshift($samples,array('key'=>sanitize_key($key),'ms'=>round($ms,2),'at'=>gmdate(DATE_ATOM),'context'=>array_map(static fn($v)=>is_scalar($v)?substr((string)$v,0,100):'', $context)));update_option(self::OPTION,array_slice($samples,0,200),false);
	}
	public static function samples(): array {$v=get_option(self::OPTION,array());return is_array($v)?$v:array();}
	public static function summary(): array {$g=array();foreach(self::samples() as $s){$k=(string)($s['key']??'unknown');$g[$k][]=(float)($s['ms']??0);} $out=array();foreach($g as $k=>$v){sort($v);$out[$k]=array('count'=>count($v),'avg'=>round(array_sum($v)/max(1,count($v)),2),'max'=>round(max($v),2),'p95'=>round($v[(int)floor((count($v)-1)*.95)]??0,2));}return $out;}
}
