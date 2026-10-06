<?php

namespace HoseinMomeni\MahexWoo\V25;

final class Schema {
	public const VERSION='2.5.0';
	public static function install(): void {
		global $wpdb; require_once ABSPATH.'wp-admin/includes/upgrade.php'; $c=$wpdb->get_charset_collate();
		$inbox=$wpdb->prefix.'hm_mahex_v25_inbox';$ledger=$wpdb->prefix.'hm_mahex_v25_ledger';$wf=$wpdb->prefix.'hm_mahex_v25_workflows';$close=$wpdb->prefix.'hm_mahex_v25_closings';$snap=$wpdb->prefix.'hm_mahex_v25_report_snapshots';$archive=$wpdb->prefix.'hm_mahex_v25_archive';
		dbDelta("CREATE TABLE $inbox (id bigint unsigned NOT NULL AUTO_INCREMENT,fingerprint varchar(64) NOT NULL,order_id bigint unsigned NOT NULL DEFAULT 0,item_type varchar(50) NOT NULL,severity varchar(16) NOT NULL DEFAULT 'info',priority int NOT NULL DEFAULT 0,title varchar(190) NOT NULL,details text NULL,status varchar(20) NOT NULL DEFAULT 'open',due_at datetime NULL,created_at datetime NOT NULL,updated_at datetime NOT NULL,PRIMARY KEY(id),UNIQUE KEY fingerprint(fingerprint),KEY status_priority(status,priority),KEY order_id(order_id)) $c;");
		dbDelta("CREATE TABLE $ledger (id bigint unsigned NOT NULL AUTO_INCREMENT,order_id bigint unsigned NOT NULL DEFAULT 0,entry_type varchar(40) NOT NULL,amount_irr decimal(20,4) NOT NULL DEFAULT 0,cost_center varchar(100) NOT NULL DEFAULT '',reference varchar(120) NOT NULL DEFAULT '',note text NULL,actor_id bigint unsigned NOT NULL DEFAULT 0,created_at datetime NOT NULL,PRIMARY KEY(id),KEY order_type(order_id,entry_type),KEY center_created(cost_center,created_at),KEY created_at(created_at)) $c;");
		dbDelta("CREATE TABLE $wf (id bigint unsigned NOT NULL AUTO_INCREMENT,order_id bigint unsigned NOT NULL,workflow_type varchar(30) NOT NULL,status varchar(30) NOT NULL DEFAULT 'open',reason varchar(190) NOT NULL DEFAULT '',note text NULL,cost_irr decimal(20,4) NOT NULL DEFAULT 0,attachment_id bigint unsigned NOT NULL DEFAULT 0,parent_id bigint unsigned NOT NULL DEFAULT 0,created_by bigint unsigned NOT NULL DEFAULT 0,created_at datetime NOT NULL,updated_at datetime NOT NULL,PRIMARY KEY(id),KEY order_type(order_id,workflow_type),KEY status_updated(status,updated_at)) $c;");
		dbDelta("CREATE TABLE $close (period_key varchar(7) NOT NULL,budget_irr decimal(20,4) NOT NULL DEFAULT 0,shipping_revenue decimal(20,4) NOT NULL DEFAULT 0,packaging_revenue decimal(20,4) NOT NULL DEFAULT 0,total_cost decimal(20,4) NOT NULL DEFAULT 0,return_cost decimal(20,4) NOT NULL DEFAULT 0,profit decimal(20,4) NOT NULL DEFAULT 0,closed_by bigint unsigned NOT NULL DEFAULT 0,closed_at datetime NOT NULL,context longtext NULL,PRIMARY KEY(period_key)) $c;");
		dbDelta("CREATE TABLE $snap (id bigint unsigned NOT NULL AUTO_INCREMENT,report_key varchar(80) NOT NULL,title varchar(190) NOT NULL,payload longtext NULL,created_at datetime NOT NULL,PRIMARY KEY(id),KEY report_created(report_key,created_at)) $c;");
		dbDelta("CREATE TABLE $archive (id bigint unsigned NOT NULL AUTO_INCREMENT,source_table varchar(80) NOT NULL,source_id bigint unsigned NOT NULL DEFAULT 0,payload longtext NULL,archived_at datetime NOT NULL,PRIMARY KEY(id),UNIQUE KEY source(source_table,source_id),KEY archived_at(archived_at)) $c;");
		update_option('hm_mahex_v25_schema',self::VERSION,false);
	}
	public static function maybeInstall(): void { if((string)get_option('hm_mahex_v25_schema','')!==self::VERSION)self::install(); }
}
