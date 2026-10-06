<?php
namespace HoseinMomeni\MahexWoo\V3;
final class Bootstrap {
 public static function register():void{try{MigrationManager::maybeMigrate();}catch(\Throwable $e){}Admin::register();add_action('woocommerce_checkout_order_created',[self::class,'assignBranch'],20);add_action('woocommerce_new_order',[self::class,'assignBranch'],20);add_action('hm_mahex_v3_daily',[self::class,'daily']);add_action('init',[self::class,'ensureSchedule']);}
 public static function activate():void{\HoseinMomeni\MahexWoo\V25\Bootstrap::activate();MigrationManager::maybeMigrate();Schema::install();self::ensureSchedule();}
 public static function deactivate():void{\HoseinMomeni\MahexWoo\V25\Bootstrap::deactivate();wp_clear_scheduled_hook('hm_mahex_v3_daily');}
 public static function ensureSchedule():void{if(!wp_next_scheduled('hm_mahex_v3_daily'))wp_schedule_event(time()+HOUR_IN_SECONDS,'daily','hm_mahex_v3_daily');}
 public static function assignBranch($order):void{if(!Config::bool('auto_branch',true))return;$id=is_object($order)&&method_exists($order,'get_id')?$order->get_id():(int)$order;if($id)BranchManager::assignOrder($id);}
 public static function daily():void{if(Config::bool('snapshot_enabled',true))Analytics::snapshotDate();}
}
