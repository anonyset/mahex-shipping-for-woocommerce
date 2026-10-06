<?php

namespace HoseinMomeni\MahexWoo\V1;

final class Schema {
	public const VERSION = '1.0.0';

	public static function install(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$events = $wpdb->prefix . 'hm_mahex_events';
		$ledger = $wpdb->prefix . 'hm_mahex_inventory_ledger';
		$print = $wpdb->prefix . 'hm_mahex_print_queue';
		$index = $wpdb->prefix . 'hm_mahex_order_index';
		$packing = $wpdb->prefix . 'hm_mahex_packing_sessions';

		dbDelta("CREATE TABLE $events (
			id bigint unsigned NOT NULL AUTO_INCREMENT,
			actor_id bigint unsigned NOT NULL DEFAULT 0,
			order_id bigint unsigned NOT NULL DEFAULT 0,
			event_type varchar(80) NOT NULL,
			message varchar(255) NOT NULL,
			context longtext NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY order_created (order_id,created_at),
			KEY type_created (event_type,created_at)
		) $charset;");

		dbDelta("CREATE TABLE $ledger (
			id bigint unsigned NOT NULL AUTO_INCREMENT,
			profile_id varchar(64) NOT NULL,
			order_id bigint unsigned NOT NULL DEFAULT 0,
			actor_id bigint unsigned NOT NULL DEFAULT 0,
			direction varchar(12) NOT NULL,
			quantity int NOT NULL DEFAULT 0,
			balance_after int NOT NULL DEFAULT 0,
			unit_cost_irr bigint NOT NULL DEFAULT 0,
			reason varchar(120) NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY profile_created (profile_id,created_at),
			KEY order_id (order_id)
		) $charset;");

		dbDelta("CREATE TABLE $print (
			id bigint unsigned NOT NULL AUTO_INCREMENT,
			order_id bigint unsigned NOT NULL,
			document_type varchar(30) NOT NULL DEFAULT 'label',
			status varchar(20) NOT NULL DEFAULT 'pending',
			copies int NOT NULL DEFAULT 1,
			requested_by bigint unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			printed_at datetime NULL,
			PRIMARY KEY  (id),
			KEY status_created (status,created_at),
			KEY order_id (order_id)
		) $charset;");

		dbDelta("CREATE TABLE $index (
			order_id bigint unsigned NOT NULL,
			order_number varchar(64) NOT NULL,
			customer_name varchar(190) NOT NULL,
			phone varchar(40) NOT NULL,
			province varchar(100) NOT NULL,
			city varchar(100) NOT NULL,
			postcode varchar(30) NOT NULL,
			tracking varchar(190) NOT NULL,
			shipment_status varchar(32) NOT NULL,
			shipping_total decimal(20,4) NOT NULL DEFAULT 0,
			packaging_total decimal(20,4) NOT NULL DEFAULT 0,
			carrier_cost decimal(20,4) NOT NULL DEFAULT 0,
			profit decimal(20,4) NOT NULL DEFAULT 0,
			created_at datetime NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (order_id),
			KEY phone (phone),
			KEY location (province,city),
			KEY tracking (tracking(100)),
			KEY status_created (shipment_status,created_at)
		) $charset;");

		dbDelta("CREATE TABLE $packing (
			id bigint unsigned NOT NULL AUTO_INCREMENT,
			order_id bigint unsigned NOT NULL,
			actor_id bigint unsigned NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'open',
			expected_items longtext NULL,
			verified_items longtext NULL,
			note text NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY order_status (order_id,status)
		) $charset;");

		update_option( 'hm_mahex_v1_schema', self::VERSION, false );
	}

	public static function maybeInstall(): void {
		if ( (string) get_option( 'hm_mahex_v1_schema', '' ) !== self::VERSION ) self::install();
	}
}
