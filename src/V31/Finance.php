<?php
namespace HoseinMomeni\MahexWoo\V31;

final class Finance {
 public static function register(): void {
  add_action('admin_menu',[self::class,'menu']);
  add_action('woocommerce_admin_order_data_after_shipping_address',[self::class,'costFields']);
  add_action('woocommerce_process_shop_order_meta',[self::class,'saveCosts'],40,1);
  add_action('woocommerce_order_details_after_order_table',[self::class,'breakdown']);
  add_action('admin_post_hm_mahex_invoice',[self::class,'invoice']);
  add_action('admin_post_hm_mahex_finance_brand',[self::class,'saveBrand']);
 }
 public static function menu(): void { add_submenu_page('hm-mahex','صورتحساب و سود ارسال','صورتحساب و سود','manage_woocommerce','hm-mahex-finance',[self::class,'page']); }
 public static function isMahex($order): bool { foreach($order->get_items('shipping') as $item) if($item->get_method_id()==='hm_mahex') return true;return false; }
 public static function components($order): array {
  $out=['freight'=>0.0,'packaging'=>0.0,'insurance'=>0.0,'services'=>0.0];
  foreach($order->get_items('shipping') as $item) { if($item->get_method_id()!=='hm_mahex') continue;
   $known=0; foreach(['freight','packaging','insurance'] as $key){$raw=$item->get_meta('hm_mahex_'.$key,true);$v=is_numeric($raw)?max(0,(float)$raw):0;$out[$key]+=$v;$known+=$v;}
   if($known===0.0 || $known===0) $out['freight']+=(float)$item->get_total();
   else $out['services']+=(float)$item->get_total()-$known;
  } return $out;
 }
 public static function allocation($order): array {
  $c=self::components($order);$revenue=array_sum($c);$costs=[];$complete=true;
  foreach(['carrier','packaging','insurance','operational'] as $key) { $raw=$order->get_meta('_hm_mahex_actual_'.$key.'_cost',true);if($key==='operational' && $raw==='') $raw=$order->get_meta('_hm_mahex_operational_cost',true); $valid=is_numeric($raw)&&is_finite((float)$raw)&&(float)$raw>=0;$complete=$complete&&$valid;$costs[$key]=$valid?(float)$raw:null; }
  return ['components'=>$c,'revenue'=>$revenue,'costs'=>$costs,'complete'=>$complete,'profit'=>$complete?$revenue-array_sum($costs):null,'currency'=>$order->get_currency()];
 }
 public static function shippingTax($order): float { $tax=0;foreach($order->get_items('shipping') as $item)if($item->get_method_id()==='hm_mahex')$tax+=(float)$item->get_total_tax();return $tax; }
 public static function labels(): array { return ['freight'=>'کرایه حمل','packaging'=>'بسته‌بندی','insurance'=>'بیمه','services'=>'خدمات / تعدیل']; }
 public static function breakdown($order): void {
  if(!self::isMahex($order)) return;echo '<section dir="rtl"><h3>ریز هزینه ارسال ماهکس</h3><table class="shop_table">';
  foreach(self::components($order) as $key=>$value) echo '<tr><th>'.esc_html(self::labels()[$key]).'</th><td>'.wp_kses_post(wc_price($value,['currency'=>$order->get_currency()])).'</td></tr>';
  echo '<tr><th>مالیات ارسال</th><td>'.wp_kses_post(wc_price(self::shippingTax($order),['currency'=>$order->get_currency()])).'</td></tr></table></section>';
 }
 public static function costFields($order): void {
  if(!current_user_can('manage_woocommerce')||!self::isMahex($order)) return;
  wp_nonce_field('hm_mahex_costs_'.$order->get_id(),'hm_mahex_costs_nonce');echo '<h3>هزینه واقعی ارسال</h3><p>واحد: '.esc_html($order->get_currency()).'؛ مبلغ صفر را صریح ثبت کنید. هزینه نامشخص، سود قطعی ندارد.</p>';
  foreach(['carrier'=>'حمل واقعی','packaging'=>'بسته‌بندی واقعی','insurance'=>'بیمه واقعی','operational'=>'عملیات'] as $key=>$label) { $value=$order->get_meta('_hm_mahex_actual_'.$key.'_cost',true);echo '<p><label>'.esc_html($label).'<input type="number" step="0.01" min="0" name="hm_mahex_cost_'.$key.'" value="'.esc_attr($value).'" style="width:100%"></label></p>'; }
  $mode=(string)$order->get_meta('_hm_mahex_freight_payment',true);echo '<p><label>نوع پرداخت کرایه <select name="hm_mahex_freight_payment">';foreach([''=>'نامشخص','prepaid'=>'پیش‌کرایه','collect'=>'پس‌کرایه'] as $value=>$label)echo '<option value="'.esc_attr($value).'" '.selected($mode,$value,false).'>'.esc_html($label).'</option>';echo '</select></label></p>';
  foreach(['print'=>'صورتحساب / ذخیره PDF','xlsx'=>'دانلود Excel'] as $format=>$label) echo '<p><a target="_blank" rel="noopener" href="'.esc_url(self::invoiceUrl($order->get_id(),$format)).'">'.esc_html($label).'</a></p>';
 }
 public static function saveCosts($id): void {
  if(!current_user_can('manage_woocommerce')||!isset($_POST['hm_mahex_costs_nonce'])||!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['hm_mahex_costs_nonce'])),'hm_mahex_costs_'.absint($id))) return;
  $order=wc_get_order($id);if(!$order||!self::isMahex($order))return;
  foreach(['carrier','packaging','insurance','operational'] as $key){$raw=wp_unslash($_POST['hm_mahex_cost_'.$key]??'');if($raw==='')$order->delete_meta_data('_hm_mahex_actual_'.$key.'_cost');elseif(is_scalar($raw)&&is_numeric($raw)&&is_finite((float)$raw)&&(float)$raw>=0)$order->update_meta_data('_hm_mahex_actual_'.$key.'_cost',wc_format_decimal($raw));}
  $mode=sanitize_key(wp_unslash($_POST['hm_mahex_freight_payment']??''));if(in_array($mode,['','prepaid','collect'],true))$order->update_meta_data('_hm_mahex_freight_payment',$mode);
  $order->save_meta_data();
  \HoseinMomeni\MahexWoo\V1\OrderIndex::upsert($order);
  \HoseinMomeni\MahexWoo\V25\Finance::syncOrder((int)$id);
 }
 public static function invoiceUrl(int $id,string $format): string { return wp_nonce_url(add_query_arg(['action'=>'hm_mahex_invoice','order_id'=>$id,'format'=>$format],admin_url('admin-post.php')),'hm_mahex_invoice_'.$id); }
 public static function brand(): string { return (string)get_option('hm_mahex_v31_invoice_brand','نمایندگی حسین مومنی – ماهکس'); }
 public static function saveBrand(): void { if(!current_user_can('manage_woocommerce'))wp_die('دسترسی مجاز نیست.',403);check_admin_referer('hm_mahex_finance_brand');update_option('hm_mahex_v31_invoice_brand',sanitize_text_field(wp_unslash($_POST['brand']??'')));wp_safe_redirect(admin_url('admin.php?page=hm-mahex-finance'));exit; }
 public static function invoiceRows($order): array {
  $shipment=(new \HoseinMomeni\MahexWoo\Shipments\OrderShipmentStore())->get($order);
  $rows=[[self::brand()],['شماره سفارش',$order->get_order_number()],['بارنامه',$shipment?$shipment->waybill_number:''],['گیرنده',trim($order->get_shipping_first_name().' '.$order->get_shipping_last_name())],['استان',\HoseinMomeni\MahexWoo\Shipping\PricingSettings::province_name($order->get_shipping_state())],['شهر',$order->get_shipping_city()],['پرداخت فروشگاه',$order->get_payment_method_title()],['نوع پرداخت کرایه',['prepaid'=>'پیش‌کرایه','collect'=>'پس‌کرایه'][(string)$order->get_meta('_hm_mahex_freight_payment',true)]??'نامشخص'],['واحد پول',$order->get_currency()],['شرح','مبلغ']];
  foreach(self::components($order) as $key=>$value)$rows[]=[self::labels()[$key],$value];
  $rows[]=['مالیات ارسال',(float)self::shippingTax($order)];$rows[]=['جمع قابل پرداخت ارسال',array_sum(self::components($order))+(float)self::shippingTax($order)];return $rows;
 }
 public static function invoice(): void {
  if(!current_user_can('manage_woocommerce'))wp_die('دسترسی مجاز نیست.',403);$id=absint($_GET['order_id']??0);check_admin_referer('hm_mahex_invoice_'.$id);$order=wc_get_order($id);if(!$order||!self::isMahex($order))wp_die('سفارش ماهکس پیدا نشد.');$rows=self::invoiceRows($order);
  nocache_headers();if(($_GET['format']??'')==='xlsx') { try{$path=FinanceXlsx::create($rows);}catch(\RuntimeException $e){wp_die(esc_html($e->getMessage()));}header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');header('Content-Disposition: attachment; filename="mahex-invoice-'.$id.'.xlsx"');readfile($path);unlink($path);exit; }
  header('Content-Type: text/html; charset=UTF-8');echo '<!doctype html><html lang="fa" dir="rtl"><meta charset="UTF-8"><title>صورتحساب ماهکس</title><style>body{font:15px Tahoma,sans-serif;color:#10223f;margin:35px}header{border-bottom:5px solid #da233b;padding:15px;background:#10223f;color:white}table{width:100%;border-collapse:collapse;margin-top:20px}td{border:1px solid #ddd;padding:12px}tr:last-child{background:#10223f;color:white;font-weight:bold}img{width:120px;background:white;float:left}@media print{button,.help{display:none}body{margin:10mm}}</style><header><img src="'.esc_url(plugins_url('assets/brand/mahex-reference.png',HM_MAHEX_FILE)).'" alt="ماهکس"><h1>'.esc_html(self::brand()).'</h1><p>صورتحساب خدمات ارسال</p></header><p class="help">برای دریافت PDF، چاپ را بزنید و «ذخیره به صورت PDF» را انتخاب کنید.</p><button onclick="window.print()">چاپ / ذخیره PDF</button><table>';
  foreach(array_slice($rows,1) as $row){echo '<tr>';foreach($row as $cell)echo '<td>'.esc_html(is_float($cell)?number_format($cell,2):$cell).'</td>';echo '</tr>';}echo '</table></html>';exit;
 }
 public static function page(): void {
  if(!current_user_can('manage_woocommerce'))return;$page=max(1,absint($_GET['report_page']??1));$result=wc_get_orders(['limit'=>50,'page'=>$page,'paginate'=>true,'orderby'=>'date','order'=>'DESC','status'=>['wc-processing','wc-completed','wc-on-hold']]);
  echo '<div class="wrap" dir="rtl"><h1>صورتحساب و سود ارسال</h1><p>سود = دریافتی ارسال − هزینه واقعی حمل، بسته‌بندی، بیمه و عملیات؛ بدون مالیات. هزینه ناقص با «نامشخص» نمایش داده می‌شود. سفارش‌های لغوشده و بازپرداخت‌شده در این فهرست نیستند؛ سفارش با بازپرداخت جزئی از سود قطعی کنار گذاشته می‌شود. جمع هر واحد پول جدا است.</p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('hm_mahex_finance_brand');echo '<input type="hidden" name="action" value="hm_mahex_finance_brand"><label>سربرگ <input name="brand" value="'.esc_attr(self::brand()).'" size="45"></label>';submit_button('ذخیره سربرگ','secondary');echo '</form><table class="widefat"><tr><th>سفارش</th><th>دریافتی ارسال</th><th>هزینه واقعی</th><th>سود</th><th>صورتحساب</th></tr>';$totals=[];
  foreach($result->orders as $order){if(!self::isMahex($order))continue;$a=self::allocation($order);if((float)$order->get_total_refunded()>0)$a['complete']=false;$currency=$a['currency'];if($a['complete'])$totals[$currency]=($totals[$currency]??0)+$a['profit'];echo '<tr><td><a href="'.esc_url($order->get_edit_order_url()).'">'.esc_html($order->get_order_number()).'</a></td><td>'.wp_kses_post(wc_price($a['revenue'],['currency'=>$currency])).'</td><td>'.($a['complete']?wp_kses_post(wc_price(array_sum($a['costs']),['currency'=>$currency])):'نامشخص').'</td><td>'.($a['complete']?wp_kses_post(wc_price($a['profit'],['currency'=>$currency])):'نامشخص').'</td><td><a target="_blank" rel="noopener" href="'.esc_url(self::invoiceUrl($order->get_id(),'print')).'">PDF / چاپ</a> | <a href="'.esc_url(self::invoiceUrl($order->get_id(),'xlsx')).'">Excel</a></td></tr>';}
  echo '</table>';foreach($totals as $currency=>$total)echo '<p>جمع سود قطعی سفارش‌های همین صفحه: '.wp_kses_post(wc_price($total,['currency'=>$currency])).'</p>';
  for($n=max(1,$page-2);$n<=min($result->max_num_pages,$page+2);$n++)echo '<a class="button" href="'.esc_url(add_query_arg(['page'=>'hm-mahex-finance','report_page'=>$n],admin_url('admin.php'))).'">'.esc_html($n).'</a> ';echo '</div>';
 }
}
