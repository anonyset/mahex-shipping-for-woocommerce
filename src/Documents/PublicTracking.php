<?php

namespace HoseinMomeni\MahexWoo\Documents;

use HoseinMomeni\MahexWoo\Shipments\OrderShipmentStore;

final class PublicTracking {
	public static function register(): void { add_action('template_redirect',array(self::class,'render')); }

	public static function render(): void {
		if(empty($_GET['hm_mahex_track']))return; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$code=sanitize_text_field(wp_unslash($_GET['hm_mahex_track'])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if(''===$code||strlen($code)>128)return;
		$orders=wc_get_orders(array('limit'=>1,'meta_query'=>array('relation'=>'OR',array('key'=>OrderShipmentStore::TRACKING_INDEX,'value'=>$code,'compare'=>'='),array('key'=>OrderShipmentStore::TRACKING_META,'value'=>$code,'compare'=>'='))));
		$order=$orders[0]??null;$shipment=null;$status='';
		if($order instanceof \WC_Order){$store=new OrderShipmentStore();foreach($store->getAll($order) as $s)if(hash_equals((string)$s->tracking_code,$code)){$shipment=$s;break;}$shipment=$shipment?:$store->aggregateShipment($order);$status=$shipment?->status??'';}
		$labels=self::labels();$statusLabel=$labels[$status]??($status!==''?$status:'کد رهگیری پیدا نشد');$eta='';$audit=array();
		if($order instanceof \WC_Order){foreach($order->get_items('shipping') as $item){$eta=(string)$item->get_meta('hm_mahex_eta',true);if($eta)break;}$raw=$order->get_meta(OrderShipmentStore::AUDIT_META,true);$audit=is_array($raw)?array_slice($raw,-20):array();}
		status_header($order?200:404);nocache_headers();header('Content-Type:text/html;charset=utf-8');$site=get_bloginfo('name');
		echo '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow,noarchive"><title>رهگیری مرسوله</title><style>body{margin:0;background:#f3f6fb;font-family:Tahoma,Arial,sans-serif;color:#111827}.card{max-width:720px;margin:6vh auto;background:#fff;border-radius:18px;padding:28px;box-shadow:0 16px 45px #0f172a18;border-top:6px solid #ef233c}.brand{font-size:28px;font-weight:900;color:#082e63}.status{font-size:24px;font-weight:800;margin:24px 0;padding:18px;border-radius:12px;background:#eff6ff}.code{direction:ltr;background:#f8fafc;padding:12px;border-radius:8px;font-family:monospace}.muted{color:#64748b}.timeline{border-right:3px solid #cbd5e1;margin:24px 8px;padding-right:20px}.event{position:relative;margin:0 0 18px}.event:before{content:"";position:absolute;right:-27px;top:5px;width:11px;height:11px;border-radius:50%;background:#082e63}.event b{display:block}.event small{color:#64748b}</style></head><body><main class="card"><div class="brand">MAHEX · '.esc_html($site).'</div><h1>رهگیری مرسوله</h1><div class="status">'.esc_html($statusLabel).'</div><p>کد رهگیری:</p><div class="code">'.esc_html($code).'</div>';
		if($eta)echo '<p><strong>زمان تقریبی تحویل:</strong> '.esc_html($eta).'</p>';
		if($order){echo '<h2>تاریخچه</h2><div class="timeline">';if(!$audit&&$shipment)echo '<div class="event"><b>'.esc_html($statusLabel).'</b><small>'.esc_html($shipment->updated_at).'</small></div>';foreach(array_reverse($audit) as $row){if(!is_array($row))continue;$st=(string)($row['status']??'');$at=(string)($row['occurred_at']??'');$action=(string)($row['action']??'');echo '<div class="event"><b>'.esc_html($labels[$st]??$st).'</b><small>'.esc_html($at).($action!==''?' · '.esc_html(self::actionLabel($action)):'').'</small></div>';}echo '</div><p class="muted">این صفحه وضعیت ثبت‌شده در خود فروشگاه را نمایش می‌دهد.</p>';}else echo '<p class="muted">کد واردشده در فروشگاه پیدا نشد.</p>';
		echo '</main></body></html>';exit;
	}

	private static function labels(): array {return array('created'=>'ثبت مرسوله','picked_up'=>'جمع‌آوری شده','in_transit'=>'در مسیر','out_for_delivery'=>'در حال تحویل','delivered'=>'تحویل شده','returned'=>'برگشتی','failed'=>'ناموفق','canceled'=>'لغو شده');}
	private static function actionLabel(string $action): string {return match($action){'create'=>'ایجاد','refresh'=>'به‌روزرسانی','reissue'=>'صدور مجدد','cancel'=>'لغو',default=>$action};}
}
