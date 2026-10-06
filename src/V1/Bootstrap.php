<?php

namespace HoseinMomeni\MahexWoo\V1;

use HoseinMomeni\MahexWoo\Enterprise\Bootstrap as EnterpriseBootstrap;
use HoseinMomeni\MahexWoo\Enterprise\Roles;

final class Bootstrap {
	public static function register(): void {
		SafeMode::register();
		try { MigrationManager::maybeMigrate(); } catch ( \Throwable $e ) { /* rollback already performed; Safe Mode keeps checkout alive */ }
		Schema::maybeInstall();
		CustomerExperience::rewriteEndpoint();
		Roles::install();
		OrderIndex::register();
		PrintManager::register();
		Operations::register();
		CustomerExperience::register();
		Admin::register();
		add_action( 'hm_mahex_v1_daily_maintenance', array( self::class, 'maintenance' ) );
		add_action( 'init', array( self::class, 'ensureSchedule' ) );
	}

	public static function activate(): void {
		MigrationManager::maybeMigrate();
		Schema::install();
		EnterpriseBootstrap::activate();
		Roles::install();
		CustomerExperience::rewriteEndpoint();
		self::ensureSchedule();
		flush_rewrite_rules( false );
	}

	public static function deactivate(): void {
		EnterpriseBootstrap::deactivate();
		wp_clear_scheduled_hook( 'hm_mahex_v1_daily_maintenance' );
		wp_clear_scheduled_hook( 'hm_mahex_sync_active_shipments' );
		flush_rewrite_rules( false );
	}

	public static function ensureSchedule(): void {
		if ( ! wp_next_scheduled( 'hm_mahex_v1_daily_maintenance' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'hm_mahex_v1_daily_maintenance' );
		}
	}

	public static function maintenance(): void {
		ActivityLog::prune();
		if ( Config::bool( 'large_store_mode', true ) ) {
			Diagnostics::rebuildOrderIndex( 500 );
		}
	}
}
