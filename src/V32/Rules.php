<?php
namespace HoseinMomeni\MahexWoo\V32;
use HoseinMomeni\MahexWoo\V1\RuleEngine;
use HoseinMomeni\MahexWoo\V1\Config;

final class Rules {
 public static function register(): void {
  add_action('wp_ajax_hm_mahex_v32_rules',[self::class,'request']);
  add_filter('woocommerce_cart_shipping_packages',[self::class,'packages']);
 }
 public static function packages(array $packages): array {foreach($packages as &$package)$package['hm_mahex_visual_rules_revision']=self::revision(RuleEngine::rules());unset($package);return $packages;}
 public static function revision(array $rules): string { return hash('sha256',serialize($rules)); }
 public static function normalize(array $rows): array {
  if(count($rows)>200)throw new \InvalidArgumentException('حداکثر ۲۰۰ قانون مجاز است.');
  foreach($rows as $i=>&$r){
   if(!is_array($r))throw new \InvalidArgumentException('ساختار قانون نامعتبر است.');
   foreach($r as $v)if(is_object($v))throw new \InvalidArgumentException('ساختار قانون نامعتبر است.');
   foreach(['name','id','province','city','mode','logic'] as $key)if(isset($r[$key])&&!is_scalar($r[$key]))throw new \InvalidArgumentException('مقدار شرط نامعتبر است.');
   foreach(['categories','shipping_classes','brands'] as $key){if(is_array($r[$key]??null))$r[$key]=implode(',',array_filter($r[$key],'is_scalar'));}
   foreach(['amount','min_weight_g','max_weight_g','min_cart_irr','max_cart_irr','min_qty','max_qty'] as $key){$v=$r[$key]??0;if(!is_scalar($v)||!is_numeric($v)||!is_finite((float)$v)||(float)$v<0)throw new \InvalidArgumentException('مقدار عددی باید نامنفی باشد.');}
   foreach([['min_weight_g','max_weight_g'],['min_cart_irr','max_cart_irr'],['min_qty','max_qty']] as [$min,$max])if((float)($r[$max]??0)>0&&(float)($r[$min]??0)>(float)$r[$max])throw new \InvalidArgumentException('حداکثر شرط از حداقل کمتر است.');
   if(trim((string)($r['name']??''))==='')throw new \InvalidArgumentException('نام قانون ضروری است.');
   $r['priority']=$i*10;
  }unset($r);
  return RuleEngine::sanitizeRules($rows);
 }
 public static function request(): void {
  if(!current_user_can('manage_woocommerce'))wp_send_json_error(['message'=>'دسترسی مجاز نیست.'],403);
  check_ajax_referer('hm_mahex_v32','nonce');
  $raw=isset($_POST['payload'])&&is_string($_POST['payload'])?wp_unslash($_POST['payload']):'';
  if(strlen($raw)>150000)wp_send_json_error(['message'=>'داده بیش از اندازه بزرگ است.'],400);
  $p=json_decode($raw,true);if(!is_array($p))wp_send_json_error(['message'=>'داده نامعتبر است.'],400);
  try{
   $mode=(string)($p['action']??'preview');$rows=self::normalize(is_array($p['rules']??null)?$p['rules']:[]);
   if($mode==='preview'){
    $sample=is_array($p['sample']??null)?$p['sample']:[];
    foreach(['freight','weight_g','cart_irr'] as $key)if(!is_scalar($sample[$key]??0)||!is_numeric($sample[$key]??0)||!is_finite((float)($sample[$key]??0))||(float)($sample[$key]??0)<0)throw new \InvalidArgumentException('مقدار آزمایش نامعتبر است.');
    $package=['contents'=>[],'contents_cost'=>\HoseinMomeni\MahexWoo\Shipping\PricingSettings::to_store_currency((float)($sample['cart_irr']??0))];
    if(!empty($sample['order_id'])){
     $id=absint($sample['order_id']);$order=wc_get_order($id);if(!$order||!current_user_can('edit_shop_order',$id))throw new \InvalidArgumentException('سفارش نمونه پیدا نشد یا دسترسی کافی ندارید.');
     $package['contents_cost']=0;$weight=0;
     foreach($order->get_items() as $line){$product=$line->get_product();if(!$product||$product->is_virtual())continue;$package['contents'][]=['data'=>$product,'quantity'=>$line->get_quantity(),'line_total'=>$line->get_total()];$package['contents_cost']+=(float)$line->get_total();$weight+=(float)wc_get_weight($product->get_weight(),'g')*$line->get_quantity();}
     $sample['weight_g']=(int)ceil($weight);$sample['province']=\HoseinMomeni\MahexWoo\Shipping\PricingSettings::province_name($order->get_shipping_state()?:$order->get_billing_state());$sample['city']=$order->get_shipping_city()?:$order->get_billing_city();
    }
    $filter=static fn()=> $rows;add_filter('pre_option_'.Config::RULES,$filter);
    try{$result=RuleEngine::apply((float)($sample['freight']??0),$package,sanitize_text_field((string)($sample['province']??'')),sanitize_text_field((string)($sample['city']??'')),(int)($sample['weight_g']??0));}
    finally{remove_filter('pre_option_'.Config::RULES,$filter);}
    wp_send_json_success($result);
   }
   if(!in_array($mode,['save','undo'],true))throw new \InvalidArgumentException('عملیات نامعتبر است.');
   $lock=new \HoseinMomeni\MahexWoo\Shipments\OptionOperationLock();if(!$lock->acquire(0))throw new \RuntimeException('ذخیره دیگری در حال اجراست. دوباره تلاش کنید.');
   try{
    $current=RuleEngine::rules();if(!hash_equals(self::revision($current),(string)($p['revision']??'')))throw new \RuntimeException('قوانین توسط کاربر دیگری تغییر کرده‌اند؛ صفحه را تازه کنید.');
    $key='hm_mahex_v32_rules_undo';$saved=get_user_meta(get_current_user_id(),$key,true);
    if($mode==='undo'){
     if(!is_array($saved)||($saved['revision']??'')!==self::revision($current))throw new \RuntimeException('امکان برگشت این تغییر نیست.');
     $rows=$saved['rules'];delete_user_meta(get_current_user_id(),$key);
    }else update_user_meta(get_current_user_id(),$key,['rules'=>$current,'revision'=>self::revision($rows)]);
    update_option(Config::RULES,$rows,false);
    \HoseinMomeni\MahexWoo\V1\ActivityLog::write('rules.visual_'.$mode,'قوانین نرخ از قانون‌ساز تصویری تغییر کرد.',0,['count'=>count($rows)]);
   }finally{$lock->release(0);}
   wp_send_json_success(['rules'=>$rows,'revision'=>self::revision($rows)]);
  }catch(\Throwable $e){wp_send_json_error(['message'=>$e->getMessage()],400);}
 }
}
