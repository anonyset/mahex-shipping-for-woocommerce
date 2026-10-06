<?php

namespace HoseinMomeni\MahexWoo\V1;

use HoseinMomeni\MahexWoo\Barcode\Code128CBarcodeRenderer;
use HoseinMomeni\MahexWoo\Documents\OrderWaybillFactory;
use HoseinMomeni\MahexWoo\Documents\WaybillRenderer;
use HoseinMomeni\MahexWoo\Shipments\OrderShipmentStore;

final class CustomerExperience {
	private const ENDPOINT='mahex-addresses';
	private const META='hm_mahex_address_book';
	private const LABEL_ACTION='hm_mahex_customer_label';

	public static function register(): void {
		add_action('init',array(self::class,'rewriteEndpoint'));
		add_filter('woocommerce_account_menu_items',array(self::class,'accountMenu'));
		add_action('woocommerce_account_'.self::ENDPOINT.'_endpoint',array(self::class,'addressBookPage'));
		add_action('admin_post_hm_mahex_v1_address_save',array(self::class,'saveAddress'));
		add_action('admin_post_hm_mahex_v1_address_delete',array(self::class,'deleteAddress'));
		add_action('admin_post_'.self::LABEL_ACTION,array(self::class,'customerLabel'));
		add_action('woocommerce_after_order_notes',array(self::class,'checkoutFields'));
		add_action('woocommerce_checkout_create_order',array(self::class,'saveCheckoutFields'),20,2);
		add_action('woocommerce_order_details_after_order_table',array(self::class,'orderCard'));
		add_filter('woocommerce_checkout_get_value',array(self::class,'checkoutDefault'),20,2);
	}

	public static function rewriteEndpoint(): void { if(Config::bool('address_book',true))add_rewrite_endpoint(self::ENDPOINT,EP_ROOT|EP_PAGES); }

	public static function accountMenu(array $items): array {
		if(!Config::bool('address_book',true))return $items;
		$out=array();foreach($items as $k=>$v){if('customer-logout'===$k)$out[self::ENDPOINT]='دفترچه آدرس ارسال';$out[$k]=$v;}return $out;
	}

	public static function checkoutFields($checkout): void {
		if(!Config::bool('feature_customer',true)||!Config::bool('delivery_preferences',true))return;
		echo '<div id="hm-mahex-delivery-preferences"><h3>ترجیحات تحویل</h3>';
		woocommerce_form_field('hm_mahex_delivery_note',array('type'=>'textarea','label'=>'توضیح برای تحویل','required'=>false,'class'=>array('form-row-wide'),'custom_attributes'=>array('maxlength'=>'300')),$checkout->get_value('hm_mahex_delivery_note'));
		if(Config::bool('preferred_time',true))woocommerce_form_field('hm_mahex_delivery_time',array('type'=>'select','label'=>'زمان ترجیحی تحویل','required'=>false,'class'=>array('form-row-wide'),'options'=>array(''=>'بدون ترجیح','morning'=>'صبح','afternoon'=>'بعدازظهر','evening'=>'عصر')),$checkout->get_value('hm_mahex_delivery_time'));
		echo '</div>';
	}

	public static function saveCheckoutFields(\WC_Order $order,array $data): void {
		$note=isset($_POST['hm_mahex_delivery_note'])?substr(sanitize_textarea_field(wp_unslash($_POST['hm_mahex_delivery_note'])),0,300):''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$time=isset($_POST['hm_mahex_delivery_time'])?sanitize_key(wp_unslash($_POST['hm_mahex_delivery_time'])):''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if(!in_array($time,array('','morning','afternoon','evening'),true))$time='';if(''!==$note)$order->update_meta_data('_hm_mahex_delivery_note',$note);if(''!==$time)$order->update_meta_data('_hm_mahex_delivery_time',$time);
	}

	public static function orderCard($order): void {
		if(!Config::bool('account_shipping_card',true)||!$order instanceof \WC_Order)return;
		if(is_user_logged_in()&&(int)$order->get_user_id()!==get_current_user_id()&&!current_user_can('manage_woocommerce'))return;
		$store=new OrderShipmentStore();$shipments=$store->getAll($order);$eta='';foreach($order->get_items('shipping') as $item){$eta=(string)$item->get_meta('hm_mahex_eta',true);if($eta)break;}
		echo '<section class="woocommerce-order-details hm-mahex-account-card" style="border:1px solid #dbe3ec;border-radius:12px;padding:16px;margin:16px 0"><h2>وضعیت ارسال</h2>';
		if(!$shipments)echo '<p>مرسوله هنوز آماده نشده است.</p>';else{echo '<ul>';foreach($shipments as $i=>$s){$url=add_query_arg('hm_mahex_track',$s->tracking_code,home_url('/'));echo '<li>بسته '.esc_html((string)($i+1)).': <strong>'.esc_html(self::statusLabel((string)$s->status)).'</strong> · <a href="'.esc_url($url).'">رهگیری '.esc_html($s->tracking_code).'</a></li>';}echo '</ul>';}
		if($eta)echo '<p><strong>زمان تقریبی تحویل:</strong> '.esc_html($eta).'</p>';$note=(string)$order->get_meta('_hm_mahex_delivery_note',true);if($note)echo '<p><strong>توضیح تحویل:</strong> '.esc_html($note).'</p>';
		if(Config::bool('allow_customer_label',false)&&is_user_logged_in()&&((int)$order->get_user_id()===get_current_user_id()||current_user_can('manage_woocommerce'))){$url=wp_nonce_url(add_query_arg(array('action'=>self::LABEL_ACTION,'order_id'=>$order->get_id()),admin_url('admin-post.php')),self::LABEL_ACTION.':'.$order->get_id());echo '<p><a class="button" target="_blank" rel="noopener" href="'.esc_url($url).'">چاپ لیبل ارسال</a></p>';}
		echo '</section>';
	}

	public static function customerLabel(): void {
		$id=absint($_GET['order_id']??0);check_admin_referer(self::LABEL_ACTION.':'.$id);$order=wc_get_order($id);
		if(!$order||!is_user_logged_in()||((int)$order->get_user_id()!==get_current_user_id()&&!current_user_can('manage_woocommerce')))wp_die('دسترسی غیرمجاز','',array('response'=>403));
		if(!Config::bool('allow_customer_label',false))wp_die('این قابلیت غیرفعال است.','',array('response'=>403));
		nocache_headers();header('Content-Type:text/html;charset=utf-8');do_action('hm_mahex_document_printed',$id,'label',1);echo (new WaybillRenderer(new Code128CBarcodeRenderer()))->render_label((new OrderWaybillFactory())->from_order($order));exit;
	}

	public static function checkoutDefault($value,string $input){
		$addressMap=array('shipping_first_name'=>'first_name','shipping_last_name'=>'last_name','shipping_phone'=>'phone','shipping_state'=>'state','shipping_city'=>'city','shipping_address_1'=>'address_1','shipping_address_2'=>'address_2','shipping_postcode'=>'postcode');
		if(Config::bool('address_book',true)&&isset($addressMap[$input])&&is_user_logged_in()&&!empty($_GET['hm_mahex_address'])){ // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$id=sanitize_key(wp_unslash($_GET['hm_mahex_address']));$book=self::book(get_current_user_id());if(isset($book[$id]))return (string)($book[$id][$addressMap[$input]]??$value); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		if(!Config::bool('reorder_shipping',true)||!in_array($input,array('hm_mahex_delivery_note','hm_mahex_delivery_time'),true)||!empty($value)||!is_user_logged_in())return $value;
		$orders=wc_get_orders(array('customer_id'=>get_current_user_id(),'limit'=>1,'orderby'=>'date','order'=>'DESC','status'=>array('wc-processing','wc-completed')));$o=$orders[0]??null;if(!$o instanceof \WC_Order)return $value;
		return 'hm_mahex_delivery_note'===$input?(string)$o->get_meta('_hm_mahex_delivery_note',true):(string)$o->get_meta('_hm_mahex_delivery_time',true);
	}

	public static function addressBookPage(): void {
		if(!is_user_logged_in()||!Config::bool('address_book',true))return;$book=self::book(get_current_user_id());echo '<h2>دفترچه آدرس ارسال</h2><p>تا ۱۰ آدرس گیرنده می‌توانید ذخیره کنید.</p>';
		foreach($book as $id=>$a){$checkout=add_query_arg('hm_mahex_address',$id,wc_get_checkout_url());$del=wp_nonce_url(add_query_arg(array('action'=>'hm_mahex_v1_address_delete','id'=>$id),admin_url('admin-post.php')),'hm_mahex_v1_address_delete:'.$id);echo '<div style="border:1px solid #ddd;padding:12px;margin:8px 0;border-radius:8px"><strong>'.esc_html($a['label']??'آدرس').'</strong><br>'.esc_html(trim(($a['first_name']??'').' '.($a['last_name']??''))).' — '.esc_html(($a['city']??'').'، '.($a['address_1']??'')).'<p><a class="button" href="'.esc_url($checkout).'">استفاده در تسویه‌حساب</a> <a class="button" href="'.esc_url($del).'" onclick="return confirm(\'حذف شود؟\')">حذف</a></p></div>';}
		echo '<h3>افزودن آدرس</h3><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="hm_mahex_v1_address_save">';wp_nonce_field('hm_mahex_v1_address_save');
		$fields=array('label'=>'عنوان آدرس','first_name'=>'نام','last_name'=>'نام خانوادگی','phone'=>'موبایل','state'=>'استان/کد استان','city'=>'شهر','address_1'=>'نشانی','address_2'=>'پلاک/واحد','postcode'=>'کدپستی');foreach($fields as $k=>$l)echo '<p><label>'.esc_html($l).'<br><input class="input-text" style="width:100%" name="address['.esc_attr($k).']" maxlength="190" '.('label'===$k||'city'===$k||'address_1'===$k?'required':'').'></label></p>';echo '<button class="button" type="submit">ذخیره آدرس</button></form>';
	}

	public static function saveAddress(): void {
		if(!is_user_logged_in())wp_die('Forbidden','',array('response'=>403));check_admin_referer('hm_mahex_v1_address_save');$raw=is_array($_POST['address']??null)?wp_unslash($_POST['address']):array();$a=array();foreach(array('label','first_name','last_name','phone','state','city','address_1','address_2','postcode') as $k)$a[$k]=substr(sanitize_text_field((string)($raw[$k]??'')),0,190);if(''===$a['label']||''===$a['city']||''===$a['address_1'])wp_die('اطلاعات آدرس ناقص است.','',array('response'=>400));$book=self::book(get_current_user_id());if(count($book)>=10)wp_die('حداکثر ۱۰ آدرس مجاز است.','',array('response'=>400));$id='addr-'.wp_generate_password(8,false,false);$book[$id]=$a;update_user_meta(get_current_user_id(),self::META,$book);wp_safe_redirect(wc_get_account_endpoint_url(self::ENDPOINT));exit;
	}

	public static function deleteAddress(): void {
		if(!is_user_logged_in())wp_die('Forbidden','',array('response'=>403));$id=sanitize_key((string)($_GET['id']??''));check_admin_referer('hm_mahex_v1_address_delete:'.$id);$book=self::book(get_current_user_id());unset($book[$id]);update_user_meta(get_current_user_id(),self::META,$book);wp_safe_redirect(wc_get_account_endpoint_url(self::ENDPOINT));exit;
	}

	private static function book(int $userId): array {$b=get_user_meta($userId,self::META,true);return is_array($b)?$b:array();}
	private static function statusLabel(string $status): string {$m=array('created'=>'ثبت شده','picked_up'=>'جمع‌آوری شده','in_transit'=>'در مسیر','out_for_delivery'=>'در حال تحویل','delivered'=>'تحویل شده','returned'=>'برگشتی','failed'=>'ناموفق','canceled'=>'لغو شده');return $m[$status]??$status;}
}
