<?php

namespace HoseinMomeni\MahexWoo\V25;

final class Bootstrap {
	public static function register(): void {
		try{MigrationManager::maybeMigrate();}catch(\Throwable $e){}
		Finance::register();WorkflowManager::register();Admin::register();
		add_action('init',array(self::class,'ensureSchedule'));
		add_action('hm_mahex_v25_hourly',array(self::class,'hourly'));
		add_action('hm_mahex_v25_daily',array(self::class,'daily'));
	}
	public static function activate(): void {\HoseinMomeni\MahexWoo\V2\Bootstrap::activate();MigrationManager::maybeMigrate();Schema::install();if(Config::bool('release_guard_enabled',true)&&!ReleaseGuard::criticalPass()){if(function_exists('deactivate_plugins')&&defined('HM_MAHEX_FILE'))deactivate_plugins(plugin_basename(HM_MAHEX_FILE));wp_die('Mahex 2.5 Release Guard: critical health checks failed.','Mahex Release Guard',array('back_link'=>true));}self::ensureSchedule();}
	public static function deactivate(): void {\HoseinMomeni\MahexWoo\V2\Bootstrap::deactivate();wp_clear_scheduled_hook('hm_mahex_v25_hourly');wp_clear_scheduled_hook('hm_mahex_v25_daily');}
	public static function ensureSchedule(): void {if(!wp_next_scheduled('hm_mahex_v25_hourly'))wp_schedule_event(time()+300,'hourly','hm_mahex_v25_hourly');if(!wp_next_scheduled('hm_mahex_v25_daily'))wp_schedule_event(time()+HOUR_IN_SECONDS,'daily','hm_mahex_v25_daily');}
	public static function hourly(): void {MigrationManager::backfill(100);if(Config::bool('automation_enabled',true))Operations::scanInbox();}
	public static function daily(): void {if(Config::bool('automation_enabled',true)){Operations::scanInbox();ReportCenter::snapshotScheduled();}if(Config::bool('archive_enabled',true))ArchiveManager::runBatch(300);}
}
