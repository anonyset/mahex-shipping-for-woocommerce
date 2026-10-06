<?php
namespace HoseinMomeni\MahexWoo\V32;
final class Workspace {
 const WIDGETS=['orders','shipments','validation','finance','packing','rules'];
 public static function register(): void {
  add_action('admin_menu',[self::class,'menu'],50);
  add_action('admin_enqueue_scripts',[self::class,'assets'],100);
  add_action('wp_ajax_hm_mahex_v32_layout',[self::class,'saveLayout']);
 }
 public static function menu(): void {add_submenu_page('hm-mahex','مرکز کار ماهکس','مرکز کار ۳.۲','manage_woocommerce','hm-mahex-workspace',[self::class,'page']);}
 public static function assets(string $hook): void {
  if(strpos($hook,'hm-mahex')===false)return;
  wp_enqueue_style('hm-mahex-v32-theme',plugins_url('assets/admin/v32-workspace.css',HM_MAHEX_FILE),[],HM_MAHEX_VERSION);
  if(strpos($hook,'hm-mahex-workspace')===false)return;
  wp_enqueue_script('hm-mahex-v32-workspace',plugins_url('assets/admin/v32-workspace.js',HM_MAHEX_FILE),[],HM_MAHEX_VERSION,true);
  wp_localize_script('hm-mahex-v32-workspace','MahexWorkspace',['url'=>admin_url('admin-ajax.php'),'nonce'=>wp_create_nonce('hm_mahex_v32'),'rules'=>\HoseinMomeni\MahexWoo\V1\RuleEngine::rules(),'revision'=>Rules::revision(\HoseinMomeni\MahexWoo\V1\RuleEngine::rules())]);
 }
 public static function normalize(array $p): array {
  $order=is_array($p['order']??null)?$p['order']:[];$hidden=is_array($p['hidden']??null)?$p['hidden']:[];
  $order=array_values(array_unique(array_filter($order,static fn($v)=>is_string($v)&&in_array($v,self::WIDGETS,true))));
  return ['order'=>array_values(array_unique(array_merge($order,self::WIDGETS))),'hidden'=>array_values(array_unique(array_filter($hidden,static fn($v)=>is_string($v)&&in_array($v,self::WIDGETS,true))))];
 }
 public static function saveLayout(): void {
  if(!current_user_can('manage_woocommerce'))wp_send_json_error(['message'=>'دسترسی مجاز نیست.'],403);
  check_ajax_referer('hm_mahex_v32','nonce');$raw=isset($_POST['payload'])&&is_string($_POST['payload'])?wp_unslash($_POST['payload']):'';
  if(strlen($raw)>5000)wp_send_json_error(['message'=>'داده بزرگ است.'],400);
  $p=json_decode($raw,true);if(!is_array($p))wp_send_json_error(['message'=>'داده نامعتبر است.'],400);
  $current=self::normalize((array)get_user_meta(get_current_user_id(),'hm_mahex_v32_layout',true));
  if(($p['action']??'')==='undo'){$previous=get_user_meta(get_current_user_id(),'hm_mahex_v32_layout_previous',true);if(is_array($previous))$layout=self::normalize($previous);else $layout=$current;delete_user_meta(get_current_user_id(),'hm_mahex_v32_layout_previous');}
  else{$layout=self::normalize($p);update_user_meta(get_current_user_id(),'hm_mahex_v32_layout_previous',$current);}
  update_user_meta(get_current_user_id(),'hm_mahex_v32_layout',$layout);wp_send_json_success($layout);
 }
 public static function page(): void {
  if(!current_user_can('manage_woocommerce'))return;
  $layout=self::normalize((array)get_user_meta(get_current_user_id(),'hm_mahex_v32_layout',true));
  $cards=['orders'=>['برد آماده‌سازی سفارش','مراحل داخلی، مسئول هر سفارش و چاپ گروهی','hm-mahex-v32-board'],'shipments'=>['ثبت بارنامه واقعی','ورود Excel/CSV و پیگیری وضعیت ثبت‌شده','hm-mahex-v31-operations'],'validation'=>['راه‌اندازی و آمادگی','مبدا، نرخ و کنترل اطلاعات ناقص','hm-mahex-v31-operations'],'finance'=>['صورتحساب و سود','هزینه واقعی، فاکتور فارسی و اکسل','hm-mahex-finance'],'packing'=>['بسته‌بندی دیداری','اقلام واقعی سفارش در بسته‌های مجزا','hm-mahex-manual-packing'],'rules'=>['طراح لیبل','بارکد واقعی، A4 و حرارتی','hm-mahex-label-designer']];
  $recent=wc_get_orders(['limit'=>20,'orderby'=>'date','order'=>'DESC']);$mahex=0;foreach($recent as $o)if(\HoseinMomeni\MahexWoo\V31\Finance::isMahex($o))$mahex++;
  echo '<div class="wrap hmx32-shell" dir="rtl"><header class="hmx32-hero"><div><p>نمایندگی حسین مومنی – ماهکس</p><h1>مرکز کار ارسال</h1><p>از بررسی سفارش تا بسته‌بندی و آماده‌سازی تحویل</p></div><span class="hmx32-version">۳.۲.۰</span></header><p class="hmx32-summary">از '.esc_html(count($recent)).' سفارش اخیر، '.esc_html($mahex).' سفارش روش ارسال ماهکس دارند. آمار از داده همین فروشگاه است.</p><div class="hmx32-toolbar"><button class="button" id="hmx32-show">نمایش همه کارت‌ها</button><button class="button" id="hmx32-layout-undo">برگشت چیدمان</button><span role="status" aria-live="polite" id="hmx32-status">برای مرتب‌سازی، کارت را بکشید یا از دکمه‌ها استفاده کنید.</span></div><div class="hmx32-grid" id="hmx32-grid">';
  foreach($layout['order'] as $key){[$title,$desc,$slug]=$cards[$key];echo '<article tabindex="0" draggable="true" data-widget="'.esc_attr($key).'" class="hmx32-widget" '.(in_array($key,$layout['hidden'],true)?'hidden':'').'><div class="hmx32-widget-tools"><button class="button" data-move="up" aria-label="جابه‌جایی به قبل">↑</button><button class="button" data-move="down" aria-label="جابه‌جایی به بعد">↓</button><button class="button" data-hide aria-label="مخفی‌کردن کارت">×</button></div><h2>'.esc_html($title).'</h2><p>'.esc_html($desc).'</p><a class="button button-primary" href="'.esc_url(admin_url('admin.php?page='.$slug)).'">بازکردن</a></article>';}
  echo '</div><section class="hmx32-panel"><h2>قانون‌ساز تصویری نرخ</h2><p>قوانین به همان موتور نرخ افزونه متصل‌اند. جابه‌جایی کارت‌ها اولویت اجرای قانون را تغییر می‌دهد. ابتدا آزمایش کنید، سپس ذخیره کنید. مبالغ ریال و وزن گرم است. با شناسه سفارش نمونه، مقصد، وزن و محصولات از همان سفارش خوانده می‌شوند.</p><button class="button" id="hmx32-rule-add">افزودن قانون</button><div id="hmx32-rules"></div><div class="hmx32-sample"><h3>آزمایش بدون تغییر نرخ فعال</h3>';
  foreach(['order_id'=>'شناسه سفارش نمونه (اختیاری)','province'=>'استان','city'=>'شهر','weight_g'=>'وزن (گرم)','cart_irr'=>'سبد (ریال)','freight'=>'کرایه اولیه (ریال)'] as $key=>$label)echo '<label>'.esc_html($label).'<input data-sample="'.esc_attr($key).'" type="'.(in_array($key,['province','city'],true)?'text':'number').'" value="'.($key==='freight'?'100000':(in_array($key,['province','city'],true)?'':'0')).'"></label>';
  echo '</div><div class="hmx32-toolbar"><button class="button" id="hmx32-preview">آزمایش قوانین</button><button class="button button-primary" id="hmx32-rule-save">ذخیره و فعال‌سازی</button><button class="button" id="hmx32-rule-undo">برگشت آخرین ذخیره</button><span id="hmx32-rule-status" role="status" aria-live="polite"></span></div></section></div>';
 }
}
