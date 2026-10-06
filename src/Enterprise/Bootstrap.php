<?php

namespace HoseinMomeni\MahexWoo\Enterprise;

final class Bootstrap {
	public static function register(): void {
		Schema::maybeInstall();
		Roles::install();
		add_filter( 'cron_schedules', array( QueueWorker::class, 'schedules' ) );
		WarehouseRouter::register();
		AddressTools::register();
		RiskEngine::register();
		AuditLog::register();
		NdrManager::register();
		QueueWorker::register();
		HealthMonitor::register();
		Admin::register();
	}

	public static function deactivate(): void {
		wp_clear_scheduled_hook( QueueWorker::HOOK );
		wp_clear_scheduled_hook( HealthMonitor::HOOK );
	}

	public static function activate(): void {
		Schema::install();
		Roles::install();
		flush_rewrite_rules( false );
	}
}
