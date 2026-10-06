<?php

namespace HoseinMomeni\MahexWoo\V2;

final class Cli {
	public static function register(): void {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! class_exists( '\WP_CLI' ) ) return;
		\WP_CLI::add_command( 'mahex health', array( self::class, 'health' ) );
		\WP_CLI::add_command( 'mahex metrics rebuild', array( self::class, 'metrics' ) );
		\WP_CLI::add_command( 'mahex problems scan', array( self::class, 'problems' ) );
		\WP_CLI::add_command( 'mahex cache flush', array( self::class, 'cache' ) );
	}
	public static function health(): void { $r=ReleaseQualification::run(); \WP_CLI::log(wp_json_encode($r,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)); if(!$r['ok'])\WP_CLI::error('Qualification failed.'); else \WP_CLI::success('All checks passed.'); }
	public static function metrics(): void { DailyMetrics::rebuildRecent(35); \WP_CLI::success('Daily metrics rebuilt.'); }
	public static function problems(): void { $r=ProblemCenter::scan(); \WP_CLI::success(count($r).' problems detected/refreshed.'); }
	public static function cache(): void { RateCache::invalidate(); \WP_CLI::success('Rate cache generation bumped.'); }
}
