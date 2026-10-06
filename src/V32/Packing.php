<?php
namespace HoseinMomeni\MahexWoo\V32;

use HoseinMomeni\MahexWoo\Admin\PackagingProfiles;
use HoseinMomeni\MahexWoo\Shipping\PricingSettings;

/** Manual packing is an order plan; inventory and charged shipping are never silently changed. */
final class Packing {
 const META='_hm_mahex_v32_manual_packing';
 public static function register(): void {add_action('admin_menu',[self::class,'menu']);add_action('admin_post_hm_mahex_v32_packing_save',[self::class,'save']);add_action('woocommerce_admin_order_data_after_shipping_address',[self::class,'orderLink']);}
 public static function menu(): void {add_submenu_page('hm-mahex','چیدمان دستی بسته‌ها','چیدمان دستی بسته‌ها','manage_woocommerce','hm-mahex-manual-packing',[self::class,'page']);}
 public static function orderLink($order): void {if(!current_user_can('manage_woocommerce'))return;echo '<p><a class="button" href="'.esc_url(add_query_arg(['page'=>'hm-mahex-manual-packing','order_id'=>$order->get_id()],admin_url('admin.php'))).'">چیدمان دستی بسته‌ها</a></p>';}
 public static function units($order): array {
  $units=[];foreach($order->get_items() as $lineId=>$line){$p=$line->get_product();if(!$p||$p->is_virtual())continue;$parent=$p->is_type('variation')?wc_get_product($p->get_parent_id()):null;
   $meta=static function($key)use($p,$parent){$v=$p->get_meta($key,true);return $v===''&&$parent?$parent->get_meta($key,true):$v;};
   $weight=(int)round(wc_get_weight((float)$p->get_weight(),'g'));if($weight<=0)$weight=PricingSettings::int_value('fallback_weight_g',500,1,1000000);
   $dims=[];foreach(['length','width','height'] as $axis){$method='get_'.$axis;$n=(int)round(wc_get_dimension((float)$p->$method(),'mm'));if($meta('_hm_mahex_packaging_mode')==='custom')$n=(int)$meta('_hm_mahex_'.$axis.'_mm');$dims[]=$n>0?$n:PricingSettings::int_value('fallback_'.$axis.'_mm',100,1,10000);}
   $fragile=$meta('_hm_mahex_fragile')==='yes';$liquid=$meta('_hm_mahex_liquid')==='yes';$separate=$meta('_hm_mahex_ship_separately')==='yes';$noMix=sanitize_key((string)$meta('_hm_mahex_no_mix_group'));$danger=sanitize_key((string)$meta('_hm_mahex_dangerous_group'));
   for($i=0;$i<(int)$line->get_quantity();$i++){if(count($units)>=500)throw new \InvalidArgumentException('چیدمان دستی حداکثر ۵۰۰ واحد کالا دارد.');$id=$lineId.':'.$i;$group=$separate?'single:'.$id:'mix:'.$noMix.':danger:'.$danger.':fragile:'.(int)$fragile.':liquid:'.(int)$liquid;
    $units[$id]=['id'=>$id,'line_id'=>(int)$lineId,'product_id'=>$p->get_id(),'name'=>$line->get_name().' #'.($i+1),'weight_g'=>$weight,'dimensions_mm'=>$dims,'group'=>$group];
   }
  }return $units;
 }
 public static function fingerprint(array $units): string {return hash('sha256',json_encode($units,JSON_UNESCAPED_UNICODE));}
 public static function snapshot($order): ?array {$plan=$order->get_meta(self::META,true);if(!is_array($plan)||!isset($plan['boxes']))return null;try{$units=self::units($order);if(($plan['fingerprint']??'')!==self::fingerprint($units))return null;}catch(\InvalidArgumentException $e){return null;}return $plan;}
 /** Pure validator ignores all client totals and recomputes from trusted product/profile data. */
 public static function validate(array $assignments,array $units,array $profiles,int $divisor=5000): array {
  if(!$units)throw new \InvalidArgumentException('کالای قابل ارسال وجود ندارد.');if(count($assignments)<1||count($assignments)>100)throw new \InvalidArgumentException('تعداد بسته باید بین ۱ و ۱۰۰ باشد.');$seen=[];$boxes=[];$actual=0;$vol=0;$chargeable=0;$cost=0;
  foreach($assignments as $index=>$assignment){if(!is_array($assignment)||!is_array($assignment['units']??null))throw new \InvalidArgumentException('بسته نامعتبر است.');$pid=(string)($assignment['profile_id']??'');$profile=$profiles[$pid]??null;if(!is_array($profile))throw new \InvalidArgumentException('پروفایل بسته پیدا نشد.');$ids=$assignment['units'];if(!$ids)throw new \InvalidArgumentException('بسته خالی را حذف کنید.');$dims=[(int)($profile['length_mm']??0),(int)($profile['width_mm']??0),(int)($profile['height_mm']??0)];if(min($dims)<1)throw new \InvalidArgumentException('ابعاد پروفایل نامعتبر است.');$capacity=(int)($profile['max_weight_g']??0);if($capacity<1)throw new \InvalidArgumentException('ظرفیت وزنی پروفایل باید مثبت باشد.');$maxItems=(int)($profile['max_items']??0);if($maxItems>0&&count($ids)>$maxItems)throw new \InvalidArgumentException('تعداد اقلام از ظرفیت بسته بیشتر است.');$weight=max(0,(int)($profile['empty_weight_g']??0));$volume=0;$group=null;
   foreach($ids as $id){if(!is_string($id)||!isset($units[$id])||isset($seen[$id]))throw new \InvalidArgumentException('کالا ناشناخته یا تکراری است.');$seen[$id]=true;$unit=$units[$id];if($group!==null&&$group!==$unit['group'])throw new \InvalidArgumentException('گروه‌های شکننده، مایع، خطرناک یا ممنوعیت اختلاط باید جدا باشند.');$group=$unit['group'];$ud=$unit['dimensions_mm'];$bd=$dims;sort($ud);sort($bd);foreach($ud as $i=>$size)if($size>$bd[$i])throw new \InvalidArgumentException('ابعاد کالا در بسته جا نمی‌شود.');$weight+=(int)$unit['weight_g'];$volume+=array_product($unit['dimensions_mm']);}
   if($weight>$capacity)throw new \InvalidArgumentException('وزن بسته بیش از ظرفیت مجاز است.');if($volume>array_product($dims))throw new \InvalidArgumentException('حجم کالاها بیش از ظرفیت بسته است.');$vw=(int)ceil(array_product($dims)/max(1000,$divisor));$cw=max($weight,$vw);$unitCost=max(0,(int)($profile['unit_cost_irr']??0));$boxes[]=['profile_id'=>$pid,'profile_name'=>(string)($profile['name']??$pid),'dimensions_mm'=>$dims,'units'=>array_values($ids),'actual_weight_g'=>$weight,'volumetric_weight_g'=>$vw,'chargeable_weight_g'=>$cw,'cost_irr'=>$unitCost];$actual+=$weight;$vol+=$vw;$chargeable+=$cw;$cost+=$unitCost;
  }
  if(count($seen)!==count($units))throw new \InvalidArgumentException('تمام واحدهای سفارش باید دقیقاً یک بار در بسته قرار بگیرند.');return ['boxes'=>$boxes,'actual_weight_g'=>$actual,'volumetric_weight_g'=>$vol,'chargeable_weight_g'=>$chargeable,'cost_irr'=>$cost,'fingerprint'=>self::fingerprint($units)];
 }
 public static function page(): void {
  if(!current_user_can('manage_woocommerce'))return;$id=absint($_GET['order_id']??0);$order=$id?wc_get_order($id):null;echo '<div class="wrap mhx-v32" dir="rtl"><h1>چیدمان دستی بسته‌های سفارش</h1><form method="get"><input type="hidden" name="page" value="hm-mahex-manual-packing"><label>شماره داخلی سفارش <input type="number" min="1" name="order_id" value="'.esc_attr($id?:'').'"></label> <button class="button">باز کردن سفارش</button></form>';if(!$order){echo '<p>برای شروع شماره سفارش را وارد کنید.</p></div>';return;}
  if(!current_user_can('edit_shop_order',$id)){echo '<p>دسترسی سفارش مجاز نیست.</p></div>';return;}try{$units=self::units($order);}catch(\InvalidArgumentException $e){echo '<p>'.esc_html($e->getMessage()).'</p></div>';return;}$profiles=PackagingProfiles::all();$saved=$order->get_meta(self::META,true);$saved=is_array($saved)?$saved:[];$plan=self::snapshot($order);$boxes=$plan['boxes']??[];
  echo '<p>هر واحد کالا را داخل بسته بکشید یا از منوی انتقال استفاده کنید. ظرفیت، وزن، حجم و ممنوعیت اختلاط روی سرور کنترل می‌شوند. این برنامه بسته‌بندی، کرایه سفارش یا موجودی انبار را خودکار تغییر نمی‌دهد. کنترل حجم به معنی تضمین چیدمان هندسی نیست؛ بسته را هنگام آماده‌سازی بررسی کنید.</p>';
  if($saved&&!$plan)echo '<p role="alert">اطلاعات کالا تغییر کرده؛ طرح قبلی معتبر نیست و باید دوباره چیدمان شود.</p>';
  echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" data-packing-editor><input type="hidden" name="action" value="hm_mahex_v32_packing_save"><input type="hidden" name="order_id" value="'.$id.'"><input type="hidden" name="revision" value="'.esc_attr((int)($saved['revision']??0)).'"><input type="hidden" name="assignments" value="'.esc_attr(wp_json_encode($boxes)).'" data-packing-json>';wp_nonce_field('hm_mahex_v32_packing_save_'.$id);
  echo '<div class="mhx-design-tools"><label>بسته استاندارد <select data-profile-selector>';foreach($profiles as $pid=>$p)echo '<option value="'.esc_attr($pid).'">'.esc_html($p['name']??$pid).'</option>';echo '</select></label><button type="button" class="button" data-add-box>افزودن بسته</button><button class="button button-primary" name="intent" value="save">ذخیره طرح بسته‌بندی</button><button class="button" name="intent" value="undo">بازگردانی طرح قبلی</button></div><div class="mhx-packing-summary" data-packing-summary></div><div class="mhx-packing-board"><section class="mhx-packing-bin" data-packing-bin="unassigned"><h2>کالاهای بدون بسته</h2><div data-unit-list></div></section><div data-packing-boxes></div></div><p aria-live="polite" data-design-status></p></form>';
  echo '<script type="application/json" data-packing-data>'.wp_json_encode(['units'=>$units,'profiles'=>$profiles,'boxes'=>$boxes],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).'</script></div>';
 }
 public static function save(): void {
  $id=absint($_POST['order_id']??0);if(!current_user_can('manage_woocommerce')||!current_user_can('edit_shop_order',$id))wp_die('دسترسی مجاز نیست.','',['response'=>403]);check_admin_referer('hm_mahex_v32_packing_save_'.$id);
  $lock=new \HoseinMomeni\MahexWoo\Shipments\OptionOperationLock();
  if(!$lock->acquire($id)){if(isset($_POST['ajax']))wp_send_json_error(['message'=>'عملیات دیگری روی سفارش در حال اجراست.'],409);wp_die('عملیات دیگری روی سفارش در حال اجراست.');}
  $error=null;
  try {
   // Load the order inside the shared operations lock, never compare a cached pre-lock revision.
   $order=wc_get_order($id);if(!$order)throw new \InvalidArgumentException('سفارش پیدا نشد.');$order->read_meta_data(true);
   $old=$order->get_meta(self::META,true);$old=is_array($old)?$old:[];if(absint($_POST['revision']??0)!==(int)($old['revision']??0))throw new \InvalidArgumentException('طرح در صفحه دیگری تغییر کرده؛ صفحه را تازه کنید.');
   $units=self::units($order);$input=($_POST['intent']??'')==='undo'?($old['previous']['boxes']??[]):json_decode(wp_unslash($_POST['assignments']??''),true);if(!is_array($input))throw new \InvalidArgumentException('چیدمان نامعتبر است.');
   $plan=self::validate($input,$units,PackagingProfiles::all(),PricingSettings::int_value('volumetric_divisor',5000,1000,50000));
   unset($old['previous']);$plan['previous']=$old;$plan['revision']=(int)($old['revision']??0)+1;$plan['saved_at']=current_time('mysql',true);$order->update_meta_data(self::META,$plan);$order->save_meta_data();$order->add_order_note('طرح دستی بسته‌بندی ذخیره شد: '.count($plan['boxes']).' بسته، '.$plan['actual_weight_g'].' گرم.');
  }catch(\InvalidArgumentException|\RuntimeException $e){$error=$e->getMessage();}finally{$lock->release($id);}
  if($error!==null){if(isset($_POST['ajax']))wp_send_json_error(['message'=>$error],409);wp_die(esc_html($error));}
  if(isset($_POST['ajax']))wp_send_json_success(['revision'=>$plan['revision'],'message'=>'طرح بسته‌بندی ذخیره شد.','plan'=>$plan]);wp_safe_redirect(add_query_arg(['page'=>'hm-mahex-manual-packing','order_id'=>$id],admin_url('admin.php')));exit;
 }
}
