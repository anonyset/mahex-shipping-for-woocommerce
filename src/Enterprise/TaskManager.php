<?php

namespace HoseinMomeni\MahexWoo\Enterprise;

final class TaskManager {
	public static function create(int $orderId,string $title,string $note='',?string $due=null,int $assigned=0): int {global $wpdb;$now=current_time('mysql',true);$wpdb->insert($wpdb->prefix.'hm_mahex_tasks',array('order_id'=>$orderId,'title'=>substr(sanitize_text_field($title),0,190),'status'=>'open','assigned_user'=>$assigned,'due_at'=>$due,'note'=>sanitize_textarea_field($note),'created_at'=>$now,'updated_at'=>$now),array('%d','%s','%s','%d','%s','%s','%s','%s'));return (int)$wpdb->insert_id;}
	public static function update(int $id,string $status,string $note='',?string $due=null,int $assigned=0): bool {global $wpdb;$status=in_array($status,array('open','in_progress','done','canceled'),true)?$status:'open';return (bool)$wpdb->update($wpdb->prefix.'hm_mahex_tasks',array('status'=>$status,'note'=>sanitize_textarea_field($note),'due_at'=>$due,'assigned_user'=>$assigned,'updated_at'=>current_time('mysql',true)),array('id'=>$id),array('%s','%s','%s','%d','%s'),array('%d'));}
	public static function recent(int $limit=100): array {global $wpdb;$limit=max(1,min(500,$limit));return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}hm_mahex_tasks ORDER BY FIELD(status,'open','in_progress','done','canceled'),due_at IS NULL,due_at ASC,id DESC LIMIT %d",$limit),ARRAY_A)?:array();}
}
