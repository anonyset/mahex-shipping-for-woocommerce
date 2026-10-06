<?php

namespace HoseinMomeni\MahexWoo\V1;

final class MigrationManager {
	public const OPTION = 'hm_mahex_v1_migration_version';

	public static function maybeMigrate(): void {
		$current = (string) get_option( self::OPTION, '' );
		if ( version_compare( $current ?: '0.0.0', '1.0.0', '>=' ) ) return;
		self::migrate();
	}

	public static function migrate(): void {
		$backup = BackupManager::snapshot( 'pre_migration_1_0_0' );
		try {
			Schema::install();
			self::migratePackagingProfiles();
			self::disableExternalIntegrationOptions();
			self::seedV1Config();
			update_option( self::OPTION, '1.0.0', false );
			update_option( 'hm_mahex_last_successful_migration', array( 'version' => '1.0.0', 'backup' => $backup, 'at' => gmdate( DATE_ATOM ) ), false );
			ActivityLog::write( 'migration.complete', 'مهاجرت داده‌ها به نسخه 1.0.0 کامل شد.', 0, array( 'backup_id' => $backup ) );
		} catch ( \Throwable $e ) {
			BackupManager::restore( $backup );
			update_option( 'hm_mahex_safe_mode_triggered', array( 'reason' => 'migration', 'message' => substr( $e->getMessage(), 0, 500 ), 'at' => gmdate( DATE_ATOM ) ), false );
			throw $e;
		}
	}

	private static function seedV1Config(): void {
		$existing = get_option( Config::OPTION, null );
		if ( is_array( $existing ) && $existing ) return;
		update_option( Config::OPTION, Config::defaults(), false );
		if ( false === get_option( Config::RULES, false ) ) update_option( Config::RULES, array(), false );
	}

	private static function migratePackagingProfiles(): void {
		$profiles = get_option( 'hm_mahex_packaging_profiles', array() );
		if ( ! is_array( $profiles ) ) return;
		$changed = false;
		foreach ( $profiles as $id => $profile ) {
			if ( ! is_array( $profile ) ) continue;
			$defaults = array( 'max_items' => 0, 'min_stock' => 0, 'price_history' => array() );
			$profiles[ $id ] = array_replace( $defaults, $profile );
			if ( empty( $profiles[ $id ]['price_history'] ) && (int) ( $profile['unit_cost_irr'] ?? 0 ) > 0 ) {
				$profiles[ $id ]['price_history'] = array( array( 'at' => gmdate( DATE_ATOM ), 'cost_irr' => (int) $profile['unit_cost_irr'] ) );
			}
			$changed = true;
		}
		if ( $changed ) update_option( 'hm_mahex_packaging_profiles', $profiles, false );
	}

	private static function disableExternalIntegrationOptions(): void {
		$pro = get_option( 'hm_mahex_pro_settings', array() );
		if ( is_array( $pro ) ) {
			foreach ( array( 'api_enabled','api_base_url','api_token','api_create_path','api_quote_path','api_track_path','api_cancel_path','auto_sync_status' ) as $key ) unset( $pro[ $key ] );
			update_option( 'hm_mahex_pro_settings', $pro, false );
		}
		$enterprise = get_option( 'hm_mahex_enterprise_settings', array() );
		if ( is_array( $enterprise ) ) {
			foreach ( array( 'webhook_enabled','webhook_secret','webhook_signature_header','api_log_enabled','api_log_body','rest_enabled','circuit_threshold','circuit_cooldown','rate_limit_per_minute' ) as $key ) unset( $enterprise[ $key ] );
			update_option( 'hm_mahex_enterprise_settings', $enterprise, false );
		}
	}
}
