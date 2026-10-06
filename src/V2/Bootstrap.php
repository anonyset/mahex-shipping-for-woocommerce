<?php

namespace HoseinMomeni\MahexWoo\V2;

final class Bootstrap {
	public static function register(): void {
		try{MigrationManager::maybeMigrate();}catch(\Throwable $e){}
		Schema::maybeInstall();
		RateCache::registerInvalidationHooks();
		EventStore::register();
		ParcelStore::register();
		ReturnManager::register();
		Admin::register();
		Cli::register();
		add_action('init',array(self::class,'ensureSchedule'));
		add_action('hm_mahex_v2_hourly',array(self::class,'hourly'));
		add_action('hm_mahex_v2_daily',array(self::class,'daily'));
	}
	public static function activate(): void { \HoseinMomeni\MahexWoo\V1\Bootstrap::activate(); MigrationManager::maybeMigrate();Schema::install();self::ensureSchedule(); }
	public static function deactivate(): void { \HoseinMomeni\MahexWoo\V1\Bootstrap::deactivate(); wp_clear_scheduled_hook('hm_mahex_v2_hourly');wp_clear_scheduled_hook('hm_mahex_v2_daily'); }
	public static function ensureSchedule(): void {
		if(!wp_next_scheduled('hm_mahex_v2_hourly'))wp_schedule_event(time()+300,'hourly','hm_mahex_v2_hourly');
		if(!wp_next_scheduled('hm_mahex_v2_daily'))wp_schedule_event(time()+HOUR_IN_SECONDS,'daily','hm_mahex_v2_daily');
	}
	public static function hourly(): void { if(Config::bool('problem_scan_enabled',true))ProblemCenter::scan(); if(Config::bool('daily_metrics_enabled',true))DailyMetrics::rebuildDate(gmdate('Y-m-d')); if ( ! get_option('hm_mahex_v2_parcel_backfill_done',0) ) ParcelStore::backfill(Config::int('large_store_batch',500,50,5000)); }
	public static function daily(): void { EventStore::prune(); if(Config::bool('daily_metrics_enabled',true))DailyMetrics::rebuildRecent(35); }
}
