<?php
namespace HoseinMomeni\MahexWoo\V3;
final class MigrationManager {const KEY='hm_mahex_v3_migration';public static function maybeMigrate(): void {$s=(string)get_option(self::KEY,'');if($s==='3.0.0')return;Schema::install();if(!BranchManager::all(false)){BranchManager::save(array('code'=>'main','name'=>'شعبه مرکزی','status'=>'active','capacity_daily'=>0));}update_option(self::KEY,'3.0.0',false);}public static function status():string{return(string)get_option(self::KEY,'pending');}}
