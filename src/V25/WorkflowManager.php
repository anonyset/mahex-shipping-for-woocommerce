<?php

namespace HoseinMomeni\MahexWoo\V25;

final class WorkflowManager {
	public static function register(): void {
		add_action('woocommerce_order_status_refunded',static fn($id)=>self::ensure((int)$id,'return','بازپرداخت سفارش'));
		add_action('woocommerce_order_status_cancelled',static fn($id)=>self::ensure((int)$id,'return','لغو سفارش'));
	}
	public static function ensure(int $orderId,string $type,string $reason='',string $note='',float $cost=0,int $attachmentId=0,int $parentId=0): int {
		global $wpdb;$table=$wpdb->prefix.'hm_mahex_v25_workflows';if(!$orderId||!wc_get_order($orderId))return 0;$type=in_array($type,array('return','exchange','reship','damage','evidence'),true)?$type:'return';
		if(in_array($type,array('return','exchange','reship'),true)){$existing=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE order_id=%d AND workflow_type=%s AND status NOT IN ('closed','cancelled') ORDER BY id DESC LIMIT 1",$orderId,$type));if($existing)return $existing;}
		$wpdb->insert($table,array('order_id'=>$orderId,'workflow_type'=>$type,'status'=>'open','reason'=>substr(sanitize_text_field($reason),0,190),'note'=>sanitize_textarea_field($note),'cost_irr'=>max(0,$cost),'attachment_id'=>max(0,$attachmentId),'parent_id'=>max(0,$parentId),'created_by'=>get_current_user_id(),'created_at'=>current_time('mysql',true),'updated_at'=>current_time('mysql',true)),array('%d','%s','%s','%s','%s','%f','%d','%d','%d','%s','%s'));$id=(int)$wpdb->insert_id;
		if($id&&class_exists('\\HoseinMomeni\\MahexWoo\\V2\\EventStore'))\HoseinMomeni\MahexWoo\V2\EventStore::write('workflow.'.$type,'پرونده عملیاتی جدید ایجاد شد.',$orderId,'info',array('workflow_id'=>$id,'reason'=>$reason));return $id;
	}
	public static function update(int $id,string $status,string $note='',float $cost=0): bool {global $wpdb;$table=$wpdb->prefix.'hm_mahex_v25_workflows';$allowed=array('open','review','approved','in_progress','received','completed','closed','cancelled');$status=in_array($status,$allowed,true)?$status:'open';return false!==$wpdb->update($table,array('status'=>$status,'note'=>sanitize_textarea_field($note),'cost_irr'=>max(0,$cost),'updated_at'=>current_time('mysql',true)),array('id'=>$id),array('%s','%s','%f','%s'),array('%d'));}
	public static function recent(string $type='',int $limit=100): array {global $wpdb;$table=$wpdb->prefix.'hm_mahex_v25_workflows';$limit=max(1,min(500,$limit));if($type)return $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE workflow_type=%s ORDER BY updated_at DESC LIMIT %d",$type,$limit),ARRAY_A)?:array();return $wpdb->get_results($wpdb->prepare("SELECT * FROM $table ORDER BY updated_at DESC LIMIT %d",$limit),ARRAY_A)?:array();}
	public static function addEvidence(int $orderId,string $note,int $attachmentId=0): int {return self::ensure($orderId,'evidence','packing_evidence',$note,0,$attachmentId);}
	public static function handleEvidenceUpload(string $field='evidence_file'): int {if(empty($_FILES[$field]['name']))return 0;require_once ABSPATH.'wp-admin/includes/file.php';require_once ABSPATH.'wp-admin/includes/media.php';require_once ABSPATH.'wp-admin/includes/image.php';$id=media_handle_upload($field,0,array(),array('test_form'=>false));if(is_wp_error($id))return 0;$id=(int)$id;$mime=(string)get_post_mime_type($id);if(!str_starts_with($mime,'image/')){wp_delete_attachment($id,true);return 0;}return $id;}
}
