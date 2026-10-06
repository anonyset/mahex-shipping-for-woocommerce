<?php

namespace HoseinMomeni\MahexWoo\Enterprise;

use HoseinMomeni\MahexWoo\Security\SecretRedactor;

final class AuditLog {
	private static bool $writing=false;
	public static function register(): void { add_action('updated_option',array(self::class,'optionUpdated'),10,3);add_action('added_option',array(self::class,'optionAdded'),10,2); }
	public static function optionUpdated(string $option,mixed $old,mixed $new): void {if(self::$writing||!self::watched($option)||$old===$new)return;self::write('settings.update','option',$option,'success',array('changed_keys'=>implode(',',self::changedKeys($old,$new))));}
	public static function optionAdded(string $option,mixed $value): void {if(self::$writing||!self::watched($option))return;self::write('settings.create','option',$option,'success',array());}
	public static function write(string $action,string $type,string|int $id,string $outcome='success',array $context=array()): void {global $wpdb;self::$writing=true;try{$redacted=(new SecretRedactor())->redact($context);$wpdb->insert($wpdb->prefix.'hm_mahex_audit',array('actor_id'=>get_current_user_id(),'action'=>sanitize_key(str_replace('.','_',$action)),'object_type'=>sanitize_key($type),'object_id'=>sanitize_text_field((string)$id),'outcome'=>in_array($outcome,array('success','failure','denied'),true)?$outcome:'success','context'=>wp_json_encode($redacted,JSON_UNESCAPED_UNICODE),'created_at'=>current_time('mysql',true)),array('%d','%s','%s','%s','%s','%s','%s'));}finally{self::$writing=false;}}
	public static function recent(int $limit=100): array {global $wpdb;$limit=max(1,min(500,$limit));return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}hm_mahex_audit ORDER BY id DESC LIMIT %d",$limit),ARRAY_A)?:array();}
	private static function watched(string $o): bool {return in_array($o,array(Config::OPTION,Config::WAREHOUSES,'hm_mahex_pro_settings','hm_mahex_pricing_settings','hm_mahex_settings','hm_mahex_packaging_profiles','hm_mahex_rate_rules'),true);}
	private static function changedKeys(mixed $old,mixed $new): array {if(!is_array($old)||!is_array($new))return array('value');$keys=array_unique(array_merge(array_keys($old),array_keys($new)));return array_values(array_filter($keys,static fn($k)=>($old[$k]??null)!==($new[$k]??null)));}
}
