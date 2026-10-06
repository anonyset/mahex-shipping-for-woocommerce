<?php

namespace HoseinMomeni\MahexWoo\V1;

final class ActivityLog {
	public static function write( string $type, string $message, int $orderId = 0, array $context = array(), int $actorId = -1 ): void {
		global $wpdb;
		if ( -1 === $actorId ) $actorId = function_exists( 'get_current_user_id' ) ? get_current_user_id() : 0;
		$table = $wpdb->prefix . 'hm_mahex_events';
		$wpdb->insert( $table, array(
			'actor_id' => max( 0, $actorId ),
			'order_id' => max( 0, $orderId ),
			'event_type' => substr( sanitize_key( $type ), 0, 80 ),
			'message' => substr( sanitize_text_field( $message ), 0, 255 ),
			'context' => wp_json_encode( self::sanitizeContext( $context ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
			'created_at' => current_time( 'mysql', true ),
		), array( '%d','%d','%s','%s','%s','%s' ) );
	}

	public static function recent( int $limit = 50 ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'hm_mahex_events';
		$limit = max( 1, min( 500, $limit ) );
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table ORDER BY id DESC LIMIT %d", $limit ), ARRAY_A ) ?: array();
	}

	public static function prune(): void {
		global $wpdb;
		$days = Config::int( 'activity_retention_days', 180, 7, 3650 );
		$table = $wpdb->prefix . 'hm_mahex_events';
		$wpdb->query( $wpdb->prepare( "DELETE FROM $table WHERE created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY)", $days ) );
	}

	private static function sanitizeContext( array $context ): array {
		$out = array();
		foreach ( $context as $key => $value ) {
			$key = sanitize_key( (string) $key );
			if ( preg_match( '/token|secret|password|phone|address|email/i', $key ) ) {
				$out[ $key ] = '[redacted]';
			} elseif ( is_scalar( $value ) || null === $value ) {
				$out[ $key ] = is_string( $value ) ? substr( sanitize_text_field( $value ), 0, 300 ) : $value;
			} elseif ( is_array( $value ) ) {
				$out[ $key ] = self::sanitizeContext( $value );
			}
		}
		return $out;
	}
}
