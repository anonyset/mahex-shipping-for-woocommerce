<?php

namespace HoseinMomeni\MahexWoo\V2;

use HoseinMomeni\MahexWoo\V1\BackupManager;
use HoseinMomeni\MahexWoo\V1\ActivityLog;

final class MigrationManager {
	private const OPTION='hm_mahex_v2_migration_version';
	public static function maybeMigrate(): void {
		$current=(string)get_option(self::OPTION,''); if(version_compare($current?:'0.0.0','2.0.0','>='))return;
		$backup=BackupManager::snapshot('pre_migration_2_0_0');
		try{
			Schema::install();
			if(false===get_option(Config::OPTION,false))update_option(Config::OPTION,Config::defaults(),false);
			self::migrateParcels();
			DailyMetrics::rebuildRecent(35);
			update_option(self::OPTION,'2.0.0',false);
			update_option('hm_mahex_last_successful_migration',array('version'=>'2.0.0','backup'=>$backup,'at'=>gmdate(DATE_ATOM)),false);
			ActivityLog::write('migration.complete','مهاجرت به Shipping OS 2.0.0 کامل شد.',0,array('backup_id'=>$backup));
		}catch(\Throwable $e){ BackupManager::restore($backup); update_option('hm_mahex_safe_mode_triggered',array('reason'=>'migration_v2','message'=>substr($e->getMessage(),0,500),'at'=>gmdate(DATE_ATOM)),false); throw $e; }
	}
	private static function migrateParcels(): void {
		if ( ! function_exists( 'wc_get_orders' ) ) return;
		global $wpdb; $t=$wpdb->prefix.'hm_mahex_v2_parcels';
		$orders=wc_get_orders(array('limit'=>Config::int('large_store_batch',500,50,5000),'orderby'=>'date','order'=>'DESC','return'=>'objects'));
		foreach($orders as $order){ if(!$order instanceof \WC_Order)continue; $shipments=$order->get_meta('_hm_mahex_shipments',true); if(!is_array($shipments)||!$shipments)continue; foreach(array_values($shipments) as $i=>$s){if(!is_array($s))continue;$key=sanitize_key((string)($s['id']??('parcel-'.$i)));if(''===$key)$key='parcel-'.$i;$now=current_time('mysql',true);$wpdb->replace($t,array('order_id'=>$order->get_id(),'parcel_key'=>$key,'tracking'=>substr(sanitize_text_field((string)($s['tracking_number']??$s['tracking']??'')),0,190),'status'=>sanitize_key((string)($s['status']??'created')),'warehouse_id'=>sanitize_key((string)($s['warehouse_id']??'')),'weight_g'=>max(0,(int)($s['weight_g']??0)),'length_mm'=>max(0,(int)($s['length_mm']??0)),'width_mm'=>max(0,(int)($s['width_mm']??0)),'height_mm'=>max(0,(int)($s['height_mm']??0)),'cost'=>max(0,(float)($s['cost']??0)),'created_at'=>$now,'updated_at'=>$now),array('%d','%s','%s','%s','%s','%d','%d','%d','%d','%f','%s','%s'));}}
	}
}
