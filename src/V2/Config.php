<?php

namespace HoseinMomeni\MahexWoo\V2;

final class Config {
	public const OPTION = 'hm_mahex_v2_settings';

	public static function defaults(): array {
		return array(
			'rate_cache_enabled' => true,
			'rate_cache_ttl' => 300,
			'event_retention_days' => 365,
			'problem_scan_enabled' => true,
			'problem_stale_hours' => 48,
			'daily_metrics_enabled' => true,
			'operator_shifts_enabled' => true,
			'insights_enabled' => true,
			'large_store_batch' => 500,
			'return_cost_default_irr' => 0,
		);
	}

	public static function all(): array {
		$raw = get_option( self::OPTION, array() );
		return array_replace( self::defaults(), is_array( $raw ) ? $raw : array() );
	}

	public static function bool( string $key, bool $default = false ): bool {
		$v = self::all()[ $key ] ?? $default;
		return in_array( $v, array( true, 1, '1', 'yes', 'on' ), true );
	}

	public static function int( string $key, int $default, int $min = 0, int $max = PHP_INT_MAX ): int {
		$v = self::all()[ $key ] ?? $default;
		$v = is_numeric( $v ) ? (int) $v : $default;
		return max( $min, min( $max, $v ) );
	}

	public static function sanitize( mixed $input ): array {
		$input = is_array( $input ) ? $input : array();
		$bool = static fn( string $key ): bool => ! empty( $input[ $key ] );
		$num = static function( string $key, int $default, int $min, int $max ) use ( $input ): int {
			$v = $input[ $key ] ?? $default;
			return is_numeric( $v ) ? max( $min, min( $max, (int) $v ) ) : $default;
		};
		return array(
			'rate_cache_enabled' => $bool( 'rate_cache_enabled' ),
			'rate_cache_ttl' => $num( 'rate_cache_ttl', 300, 30, 3600 ),
			'event_retention_days' => $num( 'event_retention_days', 365, 30, 3650 ),
			'problem_scan_enabled' => $bool( 'problem_scan_enabled' ),
			'problem_stale_hours' => $num( 'problem_stale_hours', 48, 6, 720 ),
			'daily_metrics_enabled' => $bool( 'daily_metrics_enabled' ),
			'operator_shifts_enabled' => $bool( 'operator_shifts_enabled' ),
			'insights_enabled' => $bool( 'insights_enabled' ),
			'large_store_batch' => $num( 'large_store_batch', 500, 50, 5000 ),
			'return_cost_default_irr' => $num( 'return_cost_default_irr', 0, 0, 1000000000000 ),
		);
	}
}
