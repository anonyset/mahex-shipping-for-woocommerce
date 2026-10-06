<?php

namespace HoseinMomeni\MahexWoo\Enterprise;

final class QueueStore {
	public static function enqueueUnique(string $type,array $payload,int $priority=50,int $delay=0): int {
		global $wpdb;$type=sanitize_key($type);$json=wp_json_encode($payload,JSON_UNESCAPED_UNICODE);$table=$wpdb->prefix.'hm_mahex_jobs';
		$existing=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE type=%s AND payload=%s AND status IN ('pending','running') ORDER BY id DESC LIMIT 1",$type,$json));
		return $existing>0?$existing:self::enqueue($type,$payload,$priority,$delay);
	}
	public static function enqueue(string $type,array $payload,int $priority=50,int $delay=0): int {global $wpdb;$now=current_time('mysql',true);$run=gmdate('Y-m-d H:i:s',time()+max(0,$delay));$max=Config::int('queue_max_attempts',5,1,20);$wpdb->insert($wpdb->prefix.'hm_mahex_jobs',array('type'=>sanitize_key($type),'payload'=>wp_json_encode($payload,JSON_UNESCAPED_UNICODE),'priority'=>max(0,min(1000,$priority)),'status'=>'pending','attempts'=>0,'max_attempts'=>$max,'run_after'=>$run,'created_at'=>$now,'updated_at'=>$now),array('%s','%s','%d','%s','%d','%d','%s','%s','%s'));return (int)$wpdb->insert_id;}
	public static function claim(int $limit=10): array {global $wpdb;$table=$wpdb->prefix.'hm_mahex_jobs';$wpdb->query("UPDATE $table SET status='pending',locked_at=NULL,last_error='Recovered stale worker lock',updated_at=UTC_TIMESTAMP() WHERE status='running' AND locked_at < (UTC_TIMESTAMP() - INTERVAL 15 MINUTE)");$rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE status='pending' AND run_after<=UTC_TIMESTAMP() ORDER BY priority DESC,id ASC LIMIT %d",max(1,min(50,$limit))),ARRAY_A)?:array();$claimed=array();foreach($rows as $r){$ok=$wpdb->query($wpdb->prepare("UPDATE $table SET status='running',locked_at=UTC_TIMESTAMP(),updated_at=UTC_TIMESTAMP() WHERE id=%d AND status='pending'",(int)$r['id']));if($ok){$r['status']='running';$claimed[]=$r;}}return $claimed;}
	public static function complete(int $id): void {global $wpdb;$wpdb->update($wpdb->prefix.'hm_mahex_jobs',array('status'=>'completed','updated_at'=>current_time('mysql',true)),array('id'=>$id),array('%s','%s'),array('%d'));}
	public static function fail(array $job,string $error): void {global $wpdb;$attempts=(int)$job['attempts']+1;$max=(int)$job['max_attempts'];$dead=$attempts>=$max;$delay=min(3600,30*(2**min(6,$attempts)));$wpdb->update($wpdb->prefix.'hm_mahex_jobs',array('status'=>$dead?'dead':'pending','attempts'=>$attempts,'run_after'=>gmdate('Y-m-d H:i:s',time()+$delay),'last_error'=>substr(sanitize_text_field($error),0,1000),'locked_at'=>null,'updated_at'=>current_time('mysql',true)),array('id'=>(int)$job['id']),array('%s','%d','%s','%s','%s','%s'),array('%d'));}
	public static function retry(int $id): bool {global $wpdb;return (bool)$wpdb->update($wpdb->prefix.'hm_mahex_jobs',array('status'=>'pending','attempts'=>0,'run_after'=>current_time('mysql',true),'last_error'=>null,'locked_at'=>null,'updated_at'=>current_time('mysql',true)),array('id'=>$id),array('%s','%d','%s','%s','%s','%s'),array('%d'));}
	public static function recent(int $limit=100): array {global $wpdb;$limit=max(1,min(500,$limit));return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}hm_mahex_jobs ORDER BY id DESC LIMIT %d",$limit),ARRAY_A)?:array();}
	public static function counts(): array {global $wpdb;$rows=$wpdb->get_results("SELECT status,COUNT(*) n FROM {$wpdb->prefix}hm_mahex_jobs GROUP BY status",ARRAY_A)?:array();$out=array();foreach($rows as $r)$out[$r['status']]=(int)$r['n'];return $out;}
}
