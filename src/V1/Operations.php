<?php

namespace HoseinMomeni\MahexWoo\V1;

final class Operations {
	public static function register(): void {
		add_action( 'admin_post_hm_mahex_v1_print_pick', array( self::class, 'printPick' ) );
		add_action( 'admin_post_hm_mahex_v1_print_packing', array( self::class, 'printPacking' ) );
		add_action( 'admin_post_hm_mahex_v1_verify_packing', array( self::class, 'verifyPacking' ) );
		add_action( 'admin_post_hm_mahex_v1_mark_ready', array( self::class, 'markReady' ) );
		add_action( 'woocommerce_order_status_processing', array( self::class, 'maybeReady' ), 80 );
	}

	public static function expected( \WC_Order $order ): array {
		$out=array(); foreach($order->get_items() as $item){$product=$item->get_product();if(!$product)continue;$sku=trim((string)$product->get_sku());$code=$sku!==''?$sku:(string)$product->get_id();$zone=(string)$product->get_meta('_hm_mahex_pick_zone',true);if(''===$zone&&$product->is_type('variation')){$parent=wc_get_product($product->get_parent_id());if($parent)$zone=(string)$parent->get_meta('_hm_mahex_pick_zone',true);} $out[$code]=array('code'=>$code,'name'=>$item->get_name(),'qty'=>(int)$item->get_quantity(),'zone'=>$zone?:'عمومی','product_id'=>$product->get_id());} return $out;
	}

	public static function zonePickList( array $orders ): array {
		$zones=array();foreach($orders as $order){if(!$order instanceof \WC_Order)continue;foreach(self::expected($order) as $row){$z=$row['zone'];$key=$row['code'];if(!isset($zones[$z][$key]))$zones[$z][$key]=array('code'=>$key,'name'=>$row['name'],'qty'=>0,'orders'=>array());$zones[$z][$key]['qty']+=$row['qty'];$zones[$z][$key]['orders'][]=$order->get_order_number();}}ksort($zones);return $zones;
	}

	public static function verifyPacking(): void {
		self::guard('hm_mahex_v1_verify_packing');$orderId=absint($_POST['order_id']??0);$order=wc_get_order($orderId);if(!$order)wp_die('Order not found','',array('response'=>404));
		$raw=sanitize_textarea_field((string)($_POST['scanned']??''));$tokens=preg_split('/[\s,،;]+/u',$raw)?:array();$scanned=array_count_values(array_filter(array_map('trim',$tokens)));$expected=self::expected($order);$missing=array();$wrong=array();
		foreach($expected as $code=>$row){$have=(int)($scanned[$code]??0);if($have<$row['qty'])$missing[$code]=$row['qty']-$have;unset($scanned[$code]);} foreach($scanned as $code=>$qty)if($qty>0)$wrong[$code]=$qty;
		$ok=!$missing&&!$wrong;$order->update_meta_data('_hm_mahex_packing_status',$ok?'packed':'verification_failed');$order->update_meta_data('_hm_mahex_packing_verification',array('at'=>gmdate(DATE_ATOM),'actor'=>get_current_user_id(),'missing'=>$missing,'wrong'=>$wrong));$order->save_meta_data();
		self::saveSession($orderId,$expected,$tokens,$ok?'packed':'failed');
		if ( class_exists('\HoseinMomeni\MahexWoo\V2\ShiftManager') ) \HoseinMomeni\MahexWoo\V2\ShiftManager::recordPacking( get_current_user_id(), (int) array_sum( array_column( $expected, 'qty' ) ), ! $ok );
		if ( class_exists('\HoseinMomeni\MahexWoo\V2\EventStore') ) \HoseinMomeni\MahexWoo\V2\EventStore::write( $ok?'packing.verified':'packing.failed', $ok?'بسته‌بندی سفارش تأیید شد.':'مغایرت بسته‌بندی ثبت شد.', $orderId, $ok?'info':'critical', array('missing'=>$missing,'wrong'=>$wrong) );
		ActivityLog::write($ok?'packing.verified':'packing.failed',$ok?'بسته‌بندی سفارش تأیید شد.':'مغایرت بسته‌بندی ثبت شد.',$orderId,array('missing'=>$missing,'wrong'=>$wrong));
		set_transient('hm_mahex_pack_result_'.get_current_user_id(),array('ok'=>$ok,'missing'=>$missing,'wrong'=>$wrong),60);wp_safe_redirect(add_query_arg(array('page'=>'hm-mahex-v1','tab'=>'operations','order_id'=>$orderId),admin_url('admin.php')));exit;
	}

	public static function markReady(): void { self::guard('hm_mahex_v1_mark_ready');$id=absint($_POST['order_id']??0);$o=wc_get_order($id);if($o){$o->update_meta_data('_hm_mahex_packing_status','ready_to_ship');$o->save_meta_data();ActivityLog::write('order.ready_to_ship','سفارش آماده ارسال شد.',$id);}wp_safe_redirect(wp_get_referer()?:admin_url('admin.php?page=hm-mahex-v1&tab=operations'));exit; }
	public static function maybeReady( int $orderId ): void { if(!Config::bool('auto_ready_to_ship',true)||Config::bool('packing_verification',true))return;$o=wc_get_order($orderId);if($o){$o->update_meta_data('_hm_mahex_packing_status','ready_to_ship');$o->save_meta_data();} }

	public static function readyOrders( int $limit=100 ): array { return wc_get_orders(array('limit'=>max(1,min(500,$limit)),'meta_key'=>'_hm_mahex_packing_status','meta_value'=>'ready_to_ship','orderby'=>'date','order'=>'DESC')); }

	public static function printPick(): void { self::printDoc('pick'); }
	public static function printPacking(): void { self::printDoc('packing'); }
	private static function printDoc(string $type): void { self::guard('hm_mahex_v1_print_'.$type);$ids=array_map('absint',(array)($_POST['order_ids']??array()));$orders=array_values(array_filter(array_map('wc_get_order',$ids)));if(!$orders)wp_die('No orders','',array('response'=>400));nocache_headers();header('Content-Type:text/html;charset=utf-8');echo self::renderList($orders,$type);exit; }

	private static function renderList(array $orders,string $type): string {
		$e=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');$body='';
		if('pick'===$type){foreach(self::zonePickList($orders) as $zone=>$rows){$body.='<h2>زون: '.$e($zone).'</h2><table><thead><tr><th>کد</th><th>کالا</th><th>تعداد</th><th>سفارش‌ها</th><th>✓</th></tr></thead><tbody>';foreach($rows as $r)$body.='<tr><td dir="ltr">'.$e($r['code']).'</td><td>'.$e($r['name']).'</td><td>'.$e($r['qty']).'</td><td>'.$e(implode('،',$r['orders'])).'</td><td>□</td></tr>';$body.='</tbody></table>';}}
		else{foreach($orders as $o){$body.='<section><h2>لیست بسته‌بندی سفارش #'.$e($o->get_order_number()).'</h2><p>'.$e($o->get_formatted_shipping_full_name()?:$o->get_formatted_billing_full_name()).'</p><table><thead><tr><th>کد</th><th>کالا</th><th>تعداد</th><th>تأیید</th></tr></thead><tbody>';foreach(self::expected($o) as $r)$body.='<tr><td dir="ltr">'.$e($r['code']).'</td><td>'.$e($r['name']).'</td><td>'.$e($r['qty']).'</td><td>□</td></tr>';$body.='</tbody></table></section>';}}
		return '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><title>'.($type==='pick'?'Pick List':'Packing List').'</title><style>@page{size:A4;margin:10mm}body{font-family:Tahoma,Arial,sans-serif}table{width:100%;border-collapse:collapse;margin:8px 0 20px}th,td{border:1px solid #aaa;padding:7px}h2{color:#082e63}section{break-inside:avoid}</style></head><body>'.$body.'<script>window.onload=()=>window.print()</script></body></html>';
	}

	private static function saveSession(int $orderId,array $expected,array $verified,string $status): void { global $wpdb;$wpdb->insert($wpdb->prefix.'hm_mahex_packing_sessions',array('order_id'=>$orderId,'actor_id'=>get_current_user_id(),'status'=>$status,'expected_items'=>wp_json_encode($expected,JSON_UNESCAPED_UNICODE),'verified_items'=>wp_json_encode($verified,JSON_UNESCAPED_UNICODE),'created_at'=>current_time('mysql',true),'updated_at'=>current_time('mysql',true)),array('%d','%d','%s','%s','%s','%s','%s')); }
	private static function guard(string $nonce): void { if(!current_user_can('manage_woocommerce')&&!current_user_can(\HoseinMomeni\MahexWoo\Enterprise\Roles::PACK))wp_die('Forbidden','',array('response'=>403));check_admin_referer($nonce); }
}
