<?php
namespace HoseinMomeni\MahexWoo\V3;
final class Config {
 const KEY='hm_mahex_v3_settings';
 public static function all(): array {return wp_parse_args((array)get_option(self::KEY,array()),array('enabled'=>1,'auto_branch'=>1,'approval_required'=>1,'four_eyes'=>0,'wallet_enabled'=>1,'snapshot_enabled'=>1,'default_branch'=>0));}
 public static function save(array $in): void {$s=self::all();foreach(array('enabled','auto_branch','approval_required','four_eyes','wallet_enabled','snapshot_enabled') as $k)$s[$k]=empty($in[$k])?0:1;$s['default_branch']=max(0,(int)($in['default_branch']??0));update_option(self::KEY,$s,false);}
 public static function bool(string $k,bool $d=false): bool {$s=self::all();return array_key_exists($k,$s)?(bool)$s[$k]:$d;}
}
