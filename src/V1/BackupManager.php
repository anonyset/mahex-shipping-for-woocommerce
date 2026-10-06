<?php

namespace HoseinMomeni\MahexWoo\V1;

final class BackupManager {
	private const OPTION = 'hm_mahex_v1_backups';

	public static function snapshot( string $reason = 'manual' ): string {
		$id = gmdate( 'YmdHis' ) . '-' . strtolower( wp_generate_password( 6, false, false ) );
		$data = array(
			'id' => $id,
			'created_at' => gmdate( DATE_ATOM ),
			'reason' => sanitize_key( $reason ),
			'plugin_version' => defined( 'HM_MAHEX_VERSION' ) ? HM_MAHEX_VERSION : '',
			'options' => array(
				'hm_mahex_settings' => get_option( 'hm_mahex_settings', array() ),
				'hm_mahex_pricing_settings' => get_option( 'hm_mahex_pricing_settings', array() ),
				'hm_mahex_rate_rules' => get_option( 'hm_mahex_rate_rules', array() ),
				'hm_mahex_packaging_profiles' => get_option( 'hm_mahex_packaging_profiles', array() ),
				'hm_mahex_pro_settings' => self::stripExternal( get_option( 'hm_mahex_pro_settings', array() ) ),
				'hm_mahex_enterprise_settings' => self::stripExternal( get_option( 'hm_mahex_enterprise_settings', array() ) ),
				'hm_mahex_warehouses' => get_option( 'hm_mahex_warehouses', array() ),
				Config::OPTION => get_option( Config::OPTION, array() ),
				Config::RULES => get_option( Config::RULES, array() ),
			),
		);
		$backups = get_option( self::OPTION, array() );
		$backups = is_array( $backups ) ? $backups : array();
		array_unshift( $backups, $data );
		$backups = array_slice( $backups, 0, Config::int( 'backup_limit', 10, 1, 50 ) );
		update_option( self::OPTION, $backups, false );
		ActivityLog::write( 'backup.created', 'نسخه پشتیبان تنظیمات ساخته شد.', 0, array( 'backup_id' => $id, 'reason' => $reason ) );
		return $id;
	}

	public static function all(): array {
		$value = get_option( self::OPTION, array() );
		return is_array( $value ) ? $value : array();
	}

	public static function restore( string $id ): bool {
		foreach ( self::all() as $backup ) {
			if ( ! is_array( $backup ) || (string) ( $backup['id'] ?? '' ) !== $id || ! is_array( $backup['options'] ?? null ) ) continue;
			foreach ( $backup['options'] as $key => $value ) update_option( sanitize_key( (string) $key ), $value, false );
			ActivityLog::write( 'backup.restored', 'نسخه پشتیبان تنظیمات بازیابی شد.', 0, array( 'backup_id' => $id ) );
			return true;
		}
		return false;
	}

	public static function export(): array {
		return array(
			'format' => 'hm-mahex-v1',
			'version' => defined( 'HM_MAHEX_VERSION' ) ? HM_MAHEX_VERSION : '',
			'generated_at' => gmdate( DATE_ATOM ),
			'settings' => Config::all(),
			'rules' => RuleEngine::rules(),
			'packaging_profiles' => get_option( 'hm_mahex_packaging_profiles', array() ),
			'warehouses' => get_option( 'hm_mahex_warehouses', array() ),
		);
	}

	private static function stripExternal( mixed $value ): array {
		$value = is_array( $value ) ? $value : array();
		foreach ( array_keys( $value ) as $key ) {
			if ( preg_match( '/api|webhook|token|secret|endpoint|base_url|rest_enabled/i', (string) $key ) ) unset( $value[ $key ] );
		}
		return $value;
	}
}
