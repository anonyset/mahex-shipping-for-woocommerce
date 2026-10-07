<?php
namespace HoseinMomeni\MahexWoo\V32;

use HoseinMomeni\MahexWoo\Documents\OrderWaybillFactory;
use HoseinMomeni\MahexWoo\Barcode\Code128CBarcodeRenderer;

/** Field positions are millimetres, so preview and printed output share the same layout. */
final class Designer {
 const OPTION='hm_mahex_v32_label_layout';
 public static function register(): void {
  add_action('admin_menu',[self::class,'menu']);add_action('admin_enqueue_scripts',[self::class,'enqueue']);
  add_action('admin_post_hm_mahex_v32_label_save',[self::class,'save']);add_action('admin_post_hm_mahex_v32_label_print',[self::class,'printDocument']);
 }
 public static function menu(): void {add_submenu_page('hm-mahex','طراح برچسب','طراح برچسب',\HoseinMomeni\MahexWoo\V33\Access::SETTINGS,'hm-mahex-label-designer',[self::class,'page']);}
 public static function enqueue(): void {
  if(!in_array(sanitize_key($_GET['page']??''),['hm-mahex-label-designer','hm-mahex-manual-packing'],true))return;
  wp_enqueue_style('hm-mahex-v32-designer',plugins_url('assets/admin/v32-designer.css',HM_MAHEX_FILE),[],HM_MAHEX_VERSION);
  wp_enqueue_script('hm-mahex-v32-designer',plugins_url('assets/admin/v32-designer.js',HM_MAHEX_FILE),[],HM_MAHEX_VERSION,true);
 }
 public static function fields(): array {return ['logo'=>'لوگو','brand'=>'نمایندگی','recipient'=>'گیرنده','destination'=>'مقصد','address'=>'نشانی گیرنده','phone'=>'تماس گیرنده','sender_address'=>'نشانی فرستنده','sender_phone'=>'تماس فرستنده','notes'=>'یادداشت مشتری','order'=>'شماره سفارش','waybill'=>'بارنامه','weight'=>'وزن و بسته‌ها','barcode'=>'بارکد'];}
 public static function defaults(): array {
  $fields=[];foreach(['logo'=>[5,4,20,10,10],'brand'=>[28,4,67,10,15],'recipient'=>[5,17,90,9,14],'destination'=>[5,29,90,7,12],'address'=>[5,38,90,19,11],'phone'=>[5,60,90,7,12],'sender_address'=>[5,70,90,14,10],'sender_phone'=>[5,86,90,7,11],'order'=>[5,96,42,8,10],'waybill'=>[50,96,45,8,10],'weight'=>[5,106,90,8,10],'notes'=>[5,116,90,8,9],'barcode'=>[8,127,84,18,10]] as $id=>$v)$fields[$id]=array_combine(['x','y','w','h','font'],$v)+['enabled'=>true];
  return ['format'=>'thermal','fields'=>$fields,'revision'=>0];
 }
 public static function layout(): array {$saved=get_option(self::OPTION,[]);if(!is_array($saved)||!isset($saved['fields']))return self::defaults();$saved['fields']=array_replace(self::defaults()['fields'],$saved['fields']);try{return self::validate($saved)+(isset($saved['previous'])?['previous'=>$saved['previous']]:[]);}catch(\InvalidArgumentException $e){return self::defaults();}}
 public static function validate(array $input): array {
  $format=$input['format']??'';if(!in_array($format,['thermal','a4'],true))throw new \InvalidArgumentException('قطع چاپ نامعتبر است.');$width=$format==='a4'?210:100;$height=$format==='a4'?297:150;$fields=[];
  if(!is_array($input['fields']??null))throw new \InvalidArgumentException('چیدمان نامعتبر است.');
  foreach(self::fields() as $id=>$label){$f=$input['fields'][$id]??null;if(!is_array($f))throw new \InvalidArgumentException('فیلد چیدمان ناقص است.');$out=[];foreach(['x','y','w','h','font'] as $key){$v=$f[$key]??null;if(!is_numeric($v)||!is_finite((float)$v))throw new \InvalidArgumentException('مختصات نامعتبر است.');$out[$key]=round((float)$v,2);}
   if($out['x']<0||$out['y']<0||$out['w']<5||$out['h']<5||$out['x']+$out['w']>$width||$out['y']+$out['h']>$height||$out['font']<8||$out['font']>36)throw new \InvalidArgumentException('فیلد بیرون از کاغذ یا اندازه نامعتبر است.');
   if($id==='barcode'&&($out['w']<60||$out['h']<18))throw new \InvalidArgumentException('بارکد باید حداقل ۶۰×۱۸ میلی‌متر باشد.');
   $out['enabled']=in_array($f['enabled']??false,[true,1,'1','yes'],true);$fields[$id]=$out;
  }return ['format'=>$format,'fields'=>$fields,'revision'=>max(0,(int)($input['revision']??0))];
 }
 public static function values($order): array {
  if(!$order)return ['brand'=>\HoseinMomeni\MahexWoo\V31\Finance::brand(),'recipient'=>'نام گیرنده نمونه','destination'=>'قم ← تهران','address'=>'نشانی گیرنده؛ برای پیش‌نمایش واقعی شماره سفارش را وارد کنید.','phone'=>'۰۹۱۲۱۲۳۴۵۶۷','order'=>'نمونه','waybill'=>'نمونه','weight'=>'۱ بسته؛ ۱۰۰۰ گرم','barcode'=>'1004000000','sender_address'=>'نشانی فرستنده نمونه','sender_phone'=>'تماس فرستنده نمونه','notes'=>'یادداشت مشتری نمونه'];
  $data=(new OrderWaybillFactory())->from_order($order);$packing=Packing::snapshot($order);return ['brand'=>\HoseinMomeni\MahexWoo\V31\Finance::brand(),'recipient'=>$data->recipient_name,'destination'=>$data->destination,'address'=>$data->recipient_address,'phone'=>$data->recipient_phone_masked,'order'=>$order->get_order_number(),'waybill'=>$data->waybill_number,'weight'=>$packing?count($packing['boxes']).' بسته؛ '.$packing['actual_weight_g'].' گرم':$data->weight,'barcode'=>$data->barcode_number,'sender_address'=>$data->sender_address,'sender_phone'=>$data->sender_phone,'notes'=>sanitize_textarea_field((string)$order->get_customer_note())];
 }
 public static function canvas(array $layout,array $values,bool $editing=false): string {
  $html='<div class="mhx-label-paper mhx-label-paper--'.esc_attr($layout['format']).'" dir="rtl">';foreach($layout['fields'] as $id=>$f){if(!$f['enabled']&&!$editing)continue;$style='left:'.$f['x'].'mm;top:'.$f['y'].'mm;width:'.$f['w'].'mm;height:'.$f['h'].'mm;font-size:'.$f['font'].'px;';$value=(string)($values[$id]??'');$content=$id==='logo'?'<img src="'.esc_url(plugins_url('assets/brand/mahex-reference.png',HM_MAHEX_FILE)).'" alt="ماهکس" draggable="false">':($id==='barcode'?(new Code128CBarcodeRenderer())->render_svg($value):esc_html($value));$html.='<div class="mhx-label-field'.(!$f['enabled']?' is-disabled':'').'" data-field="'.esc_attr($id).'" style="'.esc_attr($style).'"'.($editing?' tabindex="0" aria-label="'.esc_attr(self::fields()[$id]).'"':'').'>'.$content.'</div>';}$html.='</div>';return $html;
 }
 public static function page(): void {
  if(!\HoseinMomeni\MahexWoo\V33\Access::can('settings'))return;$id=absint($_GET['order_id']??0);$order=$id?wc_get_order($id):null;if($order&&!current_user_can('edit_shop_order',$id))wp_die('دسترسی سفارش مجاز نیست.','',['response'=>403]);$layout=self::layout();
  echo '<div class="wrap mhx-v32" dir="rtl"><h1>طراح برچسب ماهکس</h1><p>فیلد را بکشید یا با کلیدهای جهت جابه‌جا کنید. تنظیم عددی و نمایش/پنهان‌کردن فیلدها در کنار برگه در دسترس است. اندازه‌ها به میلی‌متر هستند.</p><form method="get"><input type="hidden" name="page" value="hm-mahex-label-designer"><label>شماره داخلی سفارش <input type="number" min="1" name="order_id" value="'.esc_attr($id?:'').'"></label> <button class="button">نمایش سفارش</button></form>';
  if($id&&!$order)echo '<p role="alert">سفارش پیدا نشد؛ داده نمونه نمایش داده می‌شود.</p>';
  echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" data-label-designer><input type="hidden" name="action" value="hm_mahex_v32_label_save"><input type="hidden" name="order_id" value="'.esc_attr($id).'"><input type="hidden" name="revision" value="'.esc_attr($layout['revision']).'">';wp_nonce_field('hm_mahex_v32_label_save');echo '<div class="mhx-design-tools"><label>قطع چاپ <select name="format" data-label-format><option value="thermal" '.selected($layout['format'],'thermal',false).'>حرارتی ۱۰۰×۱۵۰</option><option value="a4" '.selected($layout['format'],'a4',false).'>A4</option></select></label><button class="button button-primary" name="intent" value="save">ذخیره چیدمان</button><button class="button" name="intent" value="undo">بازگردانی آخرین چیدمان ذخیره‌شده</button>';
  if($order)echo '<a class="button" target="_blank" rel="noopener" href="'.esc_url(wp_nonce_url(add_query_arg(['action'=>'hm_mahex_v32_label_print','order_id'=>$id],admin_url('admin-post.php')),'hm_mahex_v32_label_print_'.$id)).'">چاپ چیدمان ذخیره‌شده / PDF</a>';
  echo '</div><div class="mhx-design-layout"><div class="mhx-paper-scroll">'.self::canvas($layout,self::values($order),true).'</div><aside class="mhx-field-settings">';
  foreach(self::fields() as $key=>$label){$f=$layout['fields'][$key];echo '<fieldset data-settings-field="'.esc_attr($key).'"><legend>'.esc_html($label).'</legend><label><input type="checkbox" name="fields['.$key.'][enabled]" value="1" '.checked($f['enabled'],true,false).'>نمایش</label>';foreach(['x'=>'افقی','y'=>'عمودی','w'=>'عرض','h'=>'ارتفاع','font'=>'قلم'] as $k=>$title)echo '<label>'.esc_html($title).'<input type="number" step="0.1" name="fields['.$key.']['.$k.']" data-position="'.$k.'" value="'.esc_attr($f[$k]).'"></label>';echo '</fieldset>';}
  echo '</aside></div><p aria-live="polite" data-design-status></p></form></div>';
 }
 public static function save(): void {
  if(!\HoseinMomeni\MahexWoo\V33\Access::can('settings'))wp_die('دسترسی مجاز نیست.','',['response'=>403]);check_admin_referer('hm_mahex_v32_label_save');
  $lock=new \HoseinMomeni\MahexWoo\Shipments\OptionOperationLock();$key=-32001;
  if(!$lock->acquire($key)){if(isset($_POST['ajax']))wp_send_json_error(['message'=>'چیدمان هم‌اکنون در حال ذخیره است؛ دوباره تلاش کنید.'],409);wp_die('چیدمان هم‌اکنون در حال ذخیره است.');}
  $error=null;
  try {
   $current=self::layout();if(absint($_POST['revision']??0)!==$current['revision'])throw new \InvalidArgumentException('چیدمان در صفحه دیگری تغییر کرده است؛ صفحه را تازه کنید.');
   $next=($_POST['intent']??'')==='undo'?self::validate($current['previous']??self::defaults()):self::validate(wp_unslash($_POST));
   $next['revision']=$current['revision']+1;unset($current['previous']);$next['previous']=$current;
   if(!update_option(self::OPTION,$next,false))throw new \RuntimeException('ذخیره چیدمان انجام نشد.');
  }catch(\InvalidArgumentException|\RuntimeException $e){$error=$e->getMessage();}finally{$lock->release($key);}
  if($error!==null){if(isset($_POST['ajax']))wp_send_json_error(['message'=>$error],409);wp_die(esc_html($error));}
  if(isset($_POST['ajax']))wp_send_json_success(['revision'=>$next['revision'],'message'=>'چیدمان ذخیره شد.']);wp_safe_redirect(add_query_arg(['page'=>'hm-mahex-label-designer','order_id'=>absint($_POST['order_id']??0)],admin_url('admin.php')));exit;
 }
 public static function printDocument(): void {
  $id=absint($_GET['order_id']??0);if(!\HoseinMomeni\MahexWoo\V33\Access::can('print')||!current_user_can('edit_shop_order',$id))wp_die('دسترسی مجاز نیست.','',['response'=>403]);check_admin_referer('hm_mahex_v32_label_print_'.$id);$order=wc_get_order($id);if(!$order)wp_die('سفارش پیدا نشد.');$layout=self::layout();nocache_headers();header('Content-Type: text/html; charset=UTF-8');$size=$layout['format']==='a4'?'A4':'100mm 150mm';
  echo '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="UTF-8"><title>برچسب ماهکس</title><link rel="stylesheet" href="'.esc_url(plugins_url('assets/admin/v32-designer.css',HM_MAHEX_FILE)).'"><style>@page{size:'.$size.';margin:0}body{margin:0;background:white}.mhx-label-paper{border:0;box-shadow:none}@media print{.mhx-print-tools{display:none}}</style></head><body><div class="mhx-print-tools"><button onclick="window.print()">چاپ / ذخیره PDF</button><p>مقیاس چاپ را ۱۰۰٪ و حاشیه را صفر قرار دهید.</p></div>'.self::canvas($layout,self::values($order)).'</body></html>';do_action('hm_mahex_document_printed',$id,'label',1);exit;
 }
}
