<?php
namespace HoseinMomeni\MahexWoo\V31;
use HoseinMomeni\MahexWoo\Shipments\OrderShipmentStore;
use HoseinMomeni\MahexWoo\Shipments\Shipment;

final class Operations {
 const OPTION='hm_mahex_v31_operations';
 public static function register(): void {
  add_action('admin_menu',[self::class,'menu'],40);
  foreach(['save','preview','commit','template'] as $method)add_action('admin_post_hm_mahex_v31_'.$method,[self::class,$method]);
  add_action('woocommerce_order_details_after_order_table',[self::class,'tracking']);
  add_shortcode('mahex_order_tracking',[self::class,'shortcode']);
  add_action('hm_mahex_shipment_saved',[self::class,'notify'],30,3);
  add_action('woocommerce_admin_order_data_after_shipping_address',[self::class,'warnings']);
 }
 public static function menu(): void {add_submenu_page('hm-mahex','راه‌اندازی و ارسال ۳.۱','راه‌اندازی و ارسال','manage_woocommerce','hm-mahex-v31-operations',[self::class,'page']);}
 private static function auth(string $action): void {if(!current_user_can('manage_woocommerce'))wp_die('دسترسی کافی ندارید.','',['response'=>403]);check_admin_referer('hm_mahex_v31_'.$action);}
 private static function form(string $action): void {echo '<form method="post" enctype="multipart/form-data" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="hm_mahex_v31_'.esc_attr($action).'">';wp_nonce_field('hm_mahex_v31_'.$action);}
 private static function settings(): array {$s=get_option(self::OPTION,[]);return is_array($s)?$s:[];}
 public static function page(): void {
  if(!current_user_can('manage_woocommerce'))return;
  $s=array_merge(self::settings(),(array)get_option('hm_mahex_settings',[]));$pricing=\HoseinMomeni\MahexWoo\Shipping\PricingSettings::all();
  echo '<div class="wrap" dir="rtl"><h1>راه‌اندازی و عملیات ارسال ماهکس ۳.۱</h1><p>۱. مبدا ← ۲. تعرفه و بسته‌بندی ← ۳. بررسی آمادگی</p>';
  self::form('save');
  echo '<h2>مرحله ۱: مبدا و واحد پول</h2>';
  foreach(['sender_name'=>'نام فرستنده','sender_phone'=>'موبایل فرستنده','origin_state'=>'استان مبدا','origin_city'=>'شهر مبدا','postal_code'=>'کدپستی مبدا','origin_address'=>'آدرس مبدا'] as $key=>$label)echo '<p><label>'.esc_html($label).' <input required name="'.esc_attr($key).'" value="'.esc_attr($s[$key]??'').'"></label></p>';
  echo '<p>واحد فعلی فروشگاه: '.esc_html(get_woocommerce_currency()).'؛ مبلغ‌های زیر همیشه ریال هستند.</p><h2>مرحله ۲: تعرفه و بسته‌بندی</h2>';
  foreach(['manual_base_cost'=>'کرایه پایه','manual_cost_per_kg'=>'کرایه هر کیلو','manual_packaging_cost'=>'بسته‌بندی دستی'] as $key=>$label)echo '<p><label>'.esc_html($label).' <input type="number" min="0" step="1" required name="'.esc_attr($key).'" value="'.esc_attr($pricing[$key]??0).'"></label> ریال</p>';
  echo '<p><label>اعمال بسته‌بندی <select name="packaging_application">';foreach(['always'=>'همه سفارش‌ها','required_products'=>'محصولات نیازمند بسته‌بندی','never'=>'غیرفعال'] as $value=>$label)echo '<option value="'.esc_attr($value).'" '.selected($pricing['packaging_application']??'always',$value,false).'>'.esc_html($label).'</option>';echo '</select></label></p>';
  echo '<h2>اعلان‌های ایمیلی مشتری</h2><p><label><input type="checkbox" name="notifications" value="1" '.checked(!empty($s['notifications']),true,false).'> ارسال ایمیل هنگام تغییر وضعیت ثبت‌شده</label></p><p>متغیرها: {order}، {status}، {waybill}، {tracking_url}</p>';
  echo '<p><input class="large-text" name="subject" value="'.esc_attr($s['subject']??'وضعیت ارسال سفارش {order}').'"></p><textarea class="large-text" rows="4" name="message">'.esc_textarea($s['message']??'سلام، وضعیت سفارش {order}: {status}. شماره بارنامه: {waybill}. پیگیری: {tracking_url}').'</textarea><p><button class="button button-primary">ذخیره و بررسی آمادگی</button></p></form>';
  echo '<h2>مرحله ۳: بررسی آمادگی</h2><ul>';
  foreach(['فرستنده و مبدا ثبت شده'=>!empty($s['sender_name'])&&!empty($s['sender_phone'])&&!empty($s['origin_state'])&&!empty($s['postal_code'])&&!empty($s['origin_city'])&&!empty($s['origin_address']),'واحد پول ریال یا تومان'=>in_array(get_woocommerce_currency(),['IRR','IRT'],true),'تعرفه پایه یا وزن ثبت شده'=>(float)($pricing['manual_base_cost']??0)>0||(float)($pricing['manual_cost_per_kg']??0)>0,'روش ماهکس در منطقه ارسال فعال'=>self::hasShippingMethod()] as $label=>$ok)echo '<li>'.($ok?'✓ ':'⚠ ').esc_html($label).'</li>';
  echo '</ul><p>مبدا در تنظیمات اصلی فرستنده ذخیره می‌شود؛ نرخ‌ها محلی‌اند؛ تعرفهٔ تاریخ‌دار فعال بر مبلغ پایه اولویت دارد. منطقهٔ ارسال را از ووکامرس ← تنظیمات ← حمل‌ونقل تنظیم کنید.</p><h2>ثبت گروهی بارنامه و وضعیت</h2><p>فایل XLSX یا CSV UTF-8 خروجی Excel را انتخاب کنید. حداکثر ۵۰۰ ردیف و ۱ مگابایت؛ ابتدا پیش‌نمایش، سپس تأیید. شماره بارنامه باید از مرسوله واقعی گرفته شود.</p>';
  self::form('template');echo '<button class="button">دانلود الگوی CSV</button></form>';self::form('preview');echo '<p><input type="file" name="csv" accept=".csv,.xlsx" required> <button class="button">پیش‌نمایش و بررسی خطاها</button></p></form>';
  $preview=get_transient('hm_mahex_v31_preview_'.get_current_user_id());
  if(is_array($preview)){echo '<table class="widefat striped"><thead><tr><th>ردیف</th><th>سفارش</th><th>بارنامه</th><th>وضعیت</th><th>خطا</th></tr></thead><tbody>';foreach($preview['rows'] as $row)echo '<tr><td>'.(int)$row['line'].'</td><td>'.esc_html($row['data'][0]??'').'</td><td>'.esc_html($row['data'][1]??'').'</td><td>'.esc_html($row['data'][3]??'').'</td><td>'.esc_html(implode(' / ',$row['errors'])).'</td></tr>';echo '</tbody></table>';self::form('commit');echo '<input type="hidden" name="token" value="'.esc_attr($preview['token']).'"><p><button class="button button-primary">ثبت ردیف‌های بدون خطا</button></p></form>';}
  $result=get_transient('hm_mahex_v31_result_'.get_current_user_id());if($result){echo '<div class="notice notice-info"><p>'.esc_html($result).'</p></div>';delete_transient('hm_mahex_v31_result_'.get_current_user_id());}
  echo '</div>';
 }
 private static function hasShippingMethod(): bool {
  $zones=\WC_Shipping_Zones::get_zones();$zones[]=['zone_id'=>0];foreach($zones as $z){$zone=new \WC_Shipping_Zone((int)$z['zone_id']);foreach($zone->get_shipping_methods(true) as $m)if(strpos($m->id,'mahex')!==false)return true;}return false;
 }
 private static function redirect(string $message=''): void {if($message)set_transient('hm_mahex_v31_result_'.get_current_user_id(),$message,600);wp_safe_redirect(admin_url('admin.php?page=hm-mahex-v31-operations'));exit;}
 private static function input(string $key): string {return isset($_POST[$key])&&is_string($_POST[$key])?wp_unslash($_POST[$key]):'';}
 public static function save(): void {
  self::auth('save');$s=self::settings();foreach(['sender_name','sender_phone','origin_state','origin_city','postal_code','origin_address','subject'] as $key)$s[$key]=sanitize_text_field(self::input($key));$s['message']=sanitize_textarea_field(self::input('message'));$s['notifications']=self::input('notifications')==='1';
  foreach(['sender_name','sender_phone','origin_state','origin_city','postal_code','origin_address'] as $key)if(!$s[$key])self::redirect('همه مشخصات فرستنده و مبدا ضروری‌اند.');
  $s['sender_phone']=OperationsValidation::digits($s['sender_phone']);$s['postal_code']=OperationsValidation::digits($s['postal_code']);
  if(!preg_match('/^(?:09\d{9}|\+989\d{9}|00989\d{9})$/',$s['sender_phone'])||!preg_match('/^\d{10}$/',$s['postal_code']))self::redirect('موبایل یا کدپستی فرستنده معتبر نیست.');
  $origin=(array)get_option('hm_mahex_settings',[]);foreach(['sender_name','sender_phone','origin_state','origin_city','postal_code','origin_address'] as $key)$origin[$key]=$s[$key];
  $pricing=\HoseinMomeni\MahexWoo\Shipping\PricingSettings::all();foreach(['manual_base_cost','manual_cost_per_kg','manual_packaging_cost'] as $key){$value=self::input($key);if(!is_numeric($value)||!is_finite((float)$value)||(float)$value<0)self::redirect('مبلغ نامعتبر است.');$pricing[$key]=(float)$value;}
  $application=self::input('packaging_application');if(!in_array($application,['always','required_products','never'],true))self::redirect('روش بسته‌بندی نامعتبر است.');$pricing['packaging_application']=$application;$pricing['pricing_mode']='manual';
  update_option('hm_mahex_settings',$origin,false);update_option(self::OPTION,$s,false);update_option(\HoseinMomeni\MahexWoo\Shipping\PricingSettings::OPTION_NAME,$pricing,false);self::redirect('تنظیمات ذخیره شد؛ نتیجه بررسی آمادگی را ببینید.');
 }
 public static function template(): void {self::auth('template');nocache_headers();header('Content-Type: text/csv; charset=UTF-8');header('Content-Disposition: attachment; filename="mahex-waybill-template.csv"');echo "\xEF\xBB\xBForder_id,waybill,tracking,status\r\n";exit;}
 public static function preview(): void {
  self::auth('preview');$file=$_FILES['csv']??[];
  if(!is_array($file)||($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||($file['size']??0)>1048576||!is_string($file['tmp_name']??null)||!is_uploaded_file($file['tmp_name'])||!in_array(strtolower(pathinfo((string)($file['name']??''),PATHINFO_EXTENSION)),['csv','xlsx'],true))self::redirect('فایل CSV یا XLSX معتبر تا ۱ مگابایت انتخاب کنید.');
  try{$rows=OperationsImport::parse($file['tmp_name'],strtolower(pathinfo($file['name'],PATHINFO_EXTENSION)));set_transient('hm_mahex_v31_preview_'.get_current_user_id(),['rows'=>$rows,'token'=>wp_generate_password(32,false)],900);self::redirect();}catch(\Throwable $e){self::redirect($e->getMessage());}
 }
 public static function commit(): void {
  self::auth('commit');$key='hm_mahex_v31_preview_'.get_current_user_id();$preview=get_transient($key);
  if(!is_array($preview)||!hash_equals($preview['token'],self::input('token')))self::redirect('پیش‌نمایش منقضی یا نامعتبر است.');
  delete_transient($key);$ok=0;$failed=0;$messages=[];
  foreach($preview['rows'] as $row){if($row['errors']){$failed++;continue;}try{OperationsImport::apply($row);$ok++;}catch(\Throwable $e){$failed++;$messages[]='ردیف '.$row['line'].': '.$e->getMessage();}}
  self::redirect('ثبت/تأیید: '.$ok.'؛ ردشده: '.$failed.'. '.implode(' / ',array_slice($messages,0,10)));
 }
 public static function warnings(\WC_Order $order): void {echo '<div dir="rtl"><strong>کنترل پیش از ارسال ماهکس</strong>';foreach(OperationsValidation::errors($order) as $error)echo '<p>'.esc_html($error).'</p>';echo '</div>';}
 public static function tracking(\WC_Order $order): void {
  if(!is_user_logged_in()||(int)$order->get_customer_id()!==get_current_user_id())return;
  $all=(new OrderShipmentStore())->getAll($order);if(!$all)return;
  echo '<section dir="rtl"><h2>پیگیری ارسال ماهکس</h2><p>وضعیت‌های زیر توسط فروشگاه ثبت شده‌اند.</p>';
  foreach($all as $shipment){echo '<p>بارنامه: '.esc_html($shipment->waybill_number).'؛ کد رهگیری: '.esc_html($shipment->tracking_code).' — '.esc_html(self::labels()[$shipment->status]??$shipment->status).' — '.esc_html($shipment->updated_at).'</p>';if(!empty($shipment->package_snapshot['_manual_carrier_reference']))echo '<p><a target="_blank" rel="noopener noreferrer" href="https://mahex.com/tracking">رهگیری در سایت ماهکس</a></p>';else echo '<p>این شناسه محلی فروشگاه است.</p>';}
  echo '</section>';
 }
 public static function shortcode(): string {if(!is_user_logged_in())return '<p>برای مشاهده ارسال‌ها وارد حساب کاربری شوید.</p>';$orders=wc_get_orders(['customer_id'=>get_current_user_id(),'limit'=>20,'orderby'=>'date','order'=>'DESC']);ob_start();foreach($orders as $order){echo '<h3>سفارش '.esc_html($order->get_order_number()).'</h3>';self::tracking($order);}return ob_get_clean();}
 private static function labels(): array {return ['created'=>'آماده‌سازی','picked_up'=>'تحویل به پست','in_transit'=>'در مسیر','out_for_delivery'=>'در حال توزیع','delivered'=>'تحویل شد','returned'=>'برگشتی','failed'=>'ارسال ناموفق','canceled'=>'لغو شد'];}
 public static function notify(\WC_Order $order,Shipment $shipment,string $action): void {
  $s=self::settings();if(empty($s['notifications']))return;
  $fingerprint=hash('sha256',$shipment->shipment_id.'|'.$shipment->status.'|'.$shipment->waybill_number);$sent=$order->get_meta('_hm_mahex_v31_sent',true);$sent=is_array($sent)?$sent:[];if(in_array($fingerprint,$sent,true))return;
  $email=$order->get_billing_email();if(!is_email($email))return;
  $url=$order->get_customer_id()?$order->get_view_order_url():home_url('/');
  $vars=['{order}'=>$order->get_order_number(),'{status}'=>self::labels()[$shipment->status]??$shipment->status,'{waybill}'=>$shipment->waybill_number,'{tracking_url}'=>$url];
  if(empty($shipment->package_snapshot['_manual_carrier_reference']))$bodyNote='\nاین شناسه محلی فروشگاه است و بارنامه رسمی ماهکس نیست.';else $bodyNote='';
  $subject=str_replace(["\r","\n"],' ',strtr($s['subject']??'وضعیت سفارش {order}',$vars));$body=strtr($s['message']??'وضعیت سفارش {order}: {status}. بارنامه: {waybill}',$vars).str_replace('\n',"\n",$bodyNote);
  if(wp_mail($email,$subject,$body,['Content-Type: text/plain; charset=UTF-8'])){$sent[]=$fingerprint;$order->update_meta_data('_hm_mahex_v31_sent',array_slice($sent,-100));$order->save_meta_data();}
 }
}
