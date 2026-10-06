<?php

namespace HoseinMomeni\MahexWoo\V25;

final class ArchiveManager {
	public static function runBatch(int $limit=200): int {if(!Config::bool('archive_enabled',true))return 0;global $wpdb;$days=Config::int('archive_after_days',730,180,3650);$src=$wpdb->prefix.'hm_mahex_v2_events';$dst=$wpdb->prefix.'hm_mahex_v25_archive';$rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM $src WHERE created_at<DATE_SUB(UTC_TIMESTAMP(),INTERVAL %d DAY) ORDER BY id ASC LIMIT %d",$days,max(1,min(2000,$limit))),ARRAY_A)?:array();$done=0;foreach($rows as $r){$ok=$wpdb->query($wpdb->prepare("INSERT IGNORE INTO $dst (source_table,source_id,payload,archived_at) VALUES ('hm_mahex_v2_events',%d,%s,%s)",(int)$r['id'],wp_json_encode($r,JSON_UNESCAPED_UNICODE),current_time('mysql',true)));if(false!==$ok){$wpdb->delete($src,array('id'=>(int)$r['id']),array('%d'));$done++;}}return $done;}
	public static function stats(): array {global $wpdb;$t=$wpdb->prefix.'hm_mahex_v25_archive';return array('count'=>(int)$wpdb->get_var("SELECT COUNT(*) FROM $t"),'oldest'=>(string)$wpdb->get_var("SELECT MIN(archived_at) FROM $t"),'newest'=>(string)$wpdb->get_var("SELECT MAX(archived_at) FROM $t"));}
	public static function partitionStrategy(): array {global $wpdb;$tables=array('hm_mahex_v2_events','hm_mahex_v2_parcels','hm_mahex_v25_ledger','hm_mahex_v25_archive');$out=array();foreach($tables as $s){$t=$wpdb->prefix.$s;$count=(int)$wpdb->get_var("SELECT COUNT(*) FROM $t");$out[]=array('table'=>$s,'rows'=>$count,'strategy'=>$count>500000?'archive_monthly':($count>100000?'archive_quarterly':'standard'),'recommendation'=>$count>100000?'آرشیو دوره‌ای را فعال نگه دارید تا Queryهای عملیاتی سبک بمانند.':'حجم جدول فعلاً مناسب است.');}return $out;}
}
