<?php

namespace HoseinMomeni\MahexWoo\Enterprise;

final class Schema {
	public const VERSION='2-local';
	public static function install(): void {
		global $wpdb;require_once ABSPATH.'wp-admin/includes/upgrade.php';$charset=$wpdb->get_charset_collate();
		$jobs=$wpdb->prefix.'hm_mahex_jobs';$audit=$wpdb->prefix.'hm_mahex_audit';$tasks=$wpdb->prefix.'hm_mahex_tasks';
		dbDelta("CREATE TABLE $jobs (id bigint unsigned NOT NULL AUTO_INCREMENT,type varchar(64) NOT NULL,payload longtext NOT NULL,priority int NOT NULL DEFAULT 50,status varchar(20) NOT NULL DEFAULT 'pending',attempts int NOT NULL DEFAULT 0,max_attempts int NOT NULL DEFAULT 5,run_after datetime NOT NULL,locked_at datetime NULL,last_error text NULL,created_at datetime NOT NULL,updated_at datetime NOT NULL,PRIMARY KEY  (id),KEY status_run (status,run_after),KEY priority (priority)) $charset;");
		dbDelta("CREATE TABLE $audit (id bigint unsigned NOT NULL AUTO_INCREMENT,actor_id bigint unsigned NOT NULL DEFAULT 0,action varchar(100) NOT NULL,object_type varchar(50) NOT NULL,object_id varchar(100) NOT NULL,outcome varchar(20) NOT NULL,context longtext NULL,created_at datetime NOT NULL,PRIMARY KEY  (id),KEY created_at (created_at),KEY actor_id (actor_id),KEY action (action)) $charset;");
		dbDelta("CREATE TABLE $tasks (id bigint unsigned NOT NULL AUTO_INCREMENT,order_id bigint unsigned NOT NULL DEFAULT 0,title varchar(190) NOT NULL,status varchar(20) NOT NULL DEFAULT 'open',assigned_user bigint unsigned NOT NULL DEFAULT 0,due_at datetime NULL,note text NULL,created_at datetime NOT NULL,updated_at datetime NOT NULL,PRIMARY KEY  (id),KEY order_status (order_id,status),KEY due_at (due_at)) $charset;");
		update_option('hm_mahex_enterprise_schema',self::VERSION,false);
	}
	public static function maybeInstall(): void { if((string)get_option('hm_mahex_enterprise_schema','')!==self::VERSION)self::install(); }
}
