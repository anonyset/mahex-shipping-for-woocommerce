<?php

namespace HoseinMomeni\MahexWoo\V2;

final class Schema {
	public const VERSION = '2.0.0';

	public static function install(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$c = $wpdb->get_charset_collate();
		$events = $wpdb->prefix . 'hm_mahex_v2_events';
		$metrics = $wpdb->prefix . 'hm_mahex_v2_daily_metrics';
		$problems = $wpdb->prefix . 'hm_mahex_v2_problems';
		$returns = $wpdb->prefix . 'hm_mahex_v2_returns';
		$shifts = $wpdb->prefix . 'hm_mahex_v2_shifts';
		$parcels = $wpdb->prefix . 'hm_mahex_v2_parcels';

		dbDelta("CREATE TABLE $events (
			id bigint unsigned NOT NULL AUTO_INCREMENT,
			correlation_id varchar(64) NOT NULL,
			order_id bigint unsigned NOT NULL DEFAULT 0,
			actor_id bigint unsigned NOT NULL DEFAULT 0,
			event_type varchar(100) NOT NULL,
			severity varchar(16) NOT NULL DEFAULT 'info',
			message varchar(255) NOT NULL,
			context longtext NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY order_created (order_id,created_at),
			KEY correlation (correlation_id),
			KEY type_created (event_type,created_at)
		) $c;");

		dbDelta("CREATE TABLE $metrics (
			metric_date date NOT NULL,
			orders_count int unsigned NOT NULL DEFAULT 0,
			shipping_revenue decimal(20,4) NOT NULL DEFAULT 0,
			packaging_revenue decimal(20,4) NOT NULL DEFAULT 0,
			carrier_cost decimal(20,4) NOT NULL DEFAULT 0,
			profit decimal(20,4) NOT NULL DEFAULT 0,
			returned_count int unsigned NOT NULL DEFAULT 0,
			return_cost decimal(20,4) NOT NULL DEFAULT 0,
			packed_count int unsigned NOT NULL DEFAULT 0,
			packing_errors int unsigned NOT NULL DEFAULT 0,
			updated_at datetime NOT NULL,
			PRIMARY KEY (metric_date)
		) $c;");

		dbDelta("CREATE TABLE $problems (
			id bigint unsigned NOT NULL AUTO_INCREMENT,
			fingerprint varchar(64) NOT NULL,
			order_id bigint unsigned NOT NULL DEFAULT 0,
			problem_type varchar(80) NOT NULL,
			severity varchar(16) NOT NULL DEFAULT 'warning',
			status varchar(20) NOT NULL DEFAULT 'open',
			title varchar(190) NOT NULL,
			details text NULL,
			first_seen datetime NOT NULL,
			last_seen datetime NOT NULL,
			resolved_at datetime NULL,
			PRIMARY KEY (id),
			UNIQUE KEY fingerprint (fingerprint),
			KEY status_severity (status,severity),
			KEY order_id (order_id)
		) $c;");

		dbDelta("CREATE TABLE $returns (
			id bigint unsigned NOT NULL AUTO_INCREMENT,
			order_id bigint unsigned NOT NULL,
			status varchar(24) NOT NULL DEFAULT 'open',
			reason varchar(120) NOT NULL DEFAULT '',
			return_cost decimal(20,4) NOT NULL DEFAULT 0,
			note text NULL,
			created_by bigint unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY order_status (order_id,status),
			KEY created_at (created_at)
		) $c;");

		dbDelta("CREATE TABLE $shifts (
			id bigint unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint unsigned NOT NULL,
			station varchar(100) NOT NULL DEFAULT 'default',
			status varchar(20) NOT NULL DEFAULT 'open',
			started_at datetime NOT NULL,
			ended_at datetime NULL,
			packed_orders int unsigned NOT NULL DEFAULT 0,
			packed_items int unsigned NOT NULL DEFAULT 0,
			errors int unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY (id),
			KEY user_status (user_id,status),
			KEY started_at (started_at)
		) $c;");

		dbDelta("CREATE TABLE $parcels (
			id bigint unsigned NOT NULL AUTO_INCREMENT,
			order_id bigint unsigned NOT NULL,
			parcel_key varchar(64) NOT NULL,
			tracking varchar(190) NOT NULL DEFAULT '',
			status varchar(32) NOT NULL DEFAULT 'created',
			warehouse_id varchar(64) NOT NULL DEFAULT '',
			weight_g int unsigned NOT NULL DEFAULT 0,
			length_mm int unsigned NOT NULL DEFAULT 0,
			width_mm int unsigned NOT NULL DEFAULT 0,
			height_mm int unsigned NOT NULL DEFAULT 0,
			cost decimal(20,4) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY order_parcel (order_id,parcel_key),
			KEY tracking (tracking(100)),
			KEY status_updated (status,updated_at)
		) $c;");

		update_option( 'hm_mahex_v2_schema', self::VERSION, false );
	}

	public static function maybeInstall(): void {
		if ( (string) get_option( 'hm_mahex_v2_schema', '' ) !== self::VERSION ) self::install();
	}
}
