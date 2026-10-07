<?php
namespace HoseinMomeni\MahexWoo\V32;

/** Internal warehouse workflow, independent of carrier lifecycle. */
final class Board {
 const META='_hm_mahex_v32_board';
 const STAGES=['review'=>'بررسی سفارش','packing'=>'در حال بسته‌بندی','packed'=>'بسته‌بندی شده','ready'=>'آماده تحویل'];
 public static function register(): void {
  add_action('admin_menu',[self::class,'menu'],45);
  add_action('admin_enqueue_scripts',[self::class,'assets']);
  add_action('wp_ajax_hm_mahex_v32_board',[self::class,'ajax']);
  add_action('admin_post_hm_mahex_v32_board',[self::class,'post']);
 }
 public static function menu(): void {add_submenu_page('hm-mahex','تابلوی عملیات ارسال','تابلوی عملیات',\HoseinMomeni\MahexWoo\V33\Access::OPERATIONS,'hm-mahex-v32-board',[self::class,'page']);}
 public static function assets(string $hook): void {
  if(strpos($hook,'hm-mahex-v32-board')===false)return;
  wp_enqueue_style('hm-mahex-v32-board',plugins_url('assets/admin/v32-board.css',HM_MAHEX_FILE),[],HM_MAHEX_VERSION);
  wp_enqueue_script('hm-mahex-v32-board',plugins_url('assets/admin/v32-board.js',HM_MAHEX_FILE),[],HM_MAHEX_VERSION,true);
  wp_localize_script('hm-mahex-v32-board','hmMahexBoard',['url'=>admin_url('admin-ajax.php'),'nonce'=>wp_create_nonce('hm_mahex_v32_board')]);
 }
 public static function state(\WC_Order $order): array {
  $state=$order->get_meta(self::META,true);$state=is_array($state)?$state:[];
  return ['stage'=>isset(self::STAGES[$state['stage']??''])?$state['stage']:'review','revision'=>max(0,(int)($state['revision']??0)),'operator'=>max(0,(int)($state['operator']??0)),'history'=>is_array($state['history']??null)?array_slice($state['history'],-30):[]];
 }
 /** Return next state as a pure operation so stale edits and undo are testable. */
 public static function transition(array $state,int $revision,string $stage,int $operator,int $actor,bool $undo=false): array {
  if($revision!==$state['revision'])throw new \RuntimeException('سفارش توسط کاربر دیگری تغییر کرده است؛ صفحه را تازه کنید.');
  if($undo){$last=end($state['history']);if(!$last||!isset($last['from'],$last['operator_from']))throw new \RuntimeException('تغییری برای بازگردانی وجود ندارد.');$stage=$last['from'];$operator=(int)$last['operator_from'];}
  if(!isset(self::STAGES[$stage])||$operator<0)throw new \InvalidArgumentException('مرحله یا مسئول معتبر نیست.');
  if($stage===$state['stage']&&$operator===$state['operator'])return $state;
  $state['history'][]=['from'=>$state['stage'],'to'=>$stage,'operator_from'=>$state['operator'],'operator_to'=>$operator,'actor'=>$actor,'at'=>gmdate('c'),'action'=>$undo?'undo':'update'];
  $state['history']=array_slice($state['history'],-30);$state['stage']=$stage;$state['operator']=$operator;$state['revision']++;return $state;
 }
 public static function update(int $id,int $revision,string $stage,int $operator,bool $undo=false): array {
  if(!\HoseinMomeni\MahexWoo\V33\Access::can('operations',$id))throw new \RuntimeException('اجازه ویرایش این سفارش را ندارید.');
  if($operator&&!(user_can($operator,'manage_woocommerce')||user_can($operator,\HoseinMomeni\MahexWoo\V33\Access::PACK)||user_can($operator,\HoseinMomeni\MahexWoo\V33\Access::OPERATIONS)))throw new \InvalidArgumentException('مسئول باید دسترسی مدیریت ووکامرس داشته باشد.');
  $lock=new \HoseinMomeni\MahexWoo\Shipments\OptionOperationLock();if(!$lock->acquire($id))throw new \RuntimeException('سفارش در حال ویرایش است؛ دوباره تلاش کنید.');
  try {
   \HoseinMomeni\MahexWoo\V33\BoardTools::guardOtherEditor($id);$order=wc_get_order($id);if(!$order)throw new \RuntimeException('سفارش پیدا نشد.');$order->read_meta_data(true);
   $state=self::state($order);
   if(($stage==='ready'||($undo&&($state['history'][count($state['history'])-1]['from']??'')==='ready'))){if(class_exists(\HoseinMomeni\MahexWoo\V34\DispatchWorkbench::class))\HoseinMomeni\MahexWoo\V34\DispatchWorkbench::assertReady($order);$errors=\HoseinMomeni\MahexWoo\V31\OperationsValidation::errors($order);if($errors)throw new \RuntimeException(implode(' / ',$errors));}
   $next=self::transition($state,$revision,$stage,$operator,get_current_user_id(),$undo);
   if($next!==$state){$order->update_meta_data(self::META,$next);$order->save_meta_data();$order->add_order_note('عملیات داخلی ماهکس: '.self::STAGES[$next['stage']].'؛ مسئول #'.$next['operator'].'؛ ویرایش '.$next['revision']);}
   return $next;
  } finally {$lock->release($id);}
 }
 private static function value(string $key): string {return isset($_POST[$key])&&is_string($_POST[$key])?wp_unslash($_POST[$key]):'';}
 public static function ajax(): void {
  if(!\HoseinMomeni\MahexWoo\V33\Access::can('operations')||!check_ajax_referer('hm_mahex_v32_board','nonce',false))wp_send_json_error(['message'=>'درخواست معتبر نیست.'],403);
  try{$next=self::update(absint(self::value('order_id')),absint(self::value('revision')),sanitize_key(self::value('stage')),absint(self::value('operator')),self::value('undo')==='1');wp_send_json_success($next);}catch(\Throwable $e){wp_send_json_error(['message'=>$e->getMessage()],409);}
 }
 public static function post(): void {
  if(!\HoseinMomeni\MahexWoo\V33\Access::can('operations'))wp_die('دسترسی کافی ندارید.','',['response'=>403]);check_admin_referer('hm_mahex_v32_board');
  $rows=isset($_POST['orders'])&&is_array($_POST['orders'])?$_POST['orders']:[];$selected=isset($_POST['selected'])&&is_array($_POST['selected'])?array_map('absint',$_POST['selected']):[];
  $undoId=absint(self::value('undo_order'));$single=$undoId?:absint(self::value('single'));if($single)$selected=[$single];$results=[];
  if(count($selected)>50)wp_die('حداکثر ۵۰ سفارش مجاز است.');
  foreach(array_unique($selected) as $id){$row=$rows[$id]??[];if(!is_array($row))continue;try{self::update($id,absint($row['revision']??0),sanitize_key((string)(($single?$row['stage']:self::value('bulk_stage'))??'')),absint($single?($row['operator']??0):self::value('bulk_operator')),($undoId>0||self::value('undo')==='1'));$results[]='#'.$id.' ثبت شد';}catch(\Throwable $e){$results[]='#'.$id.': '.$e->getMessage();}}
  set_transient('hm_mahex_v32_board_result_'.get_current_user_id(),implode(' | ',$results)?:'سفارشی انتخاب نشد.',600);wp_safe_redirect(admin_url('admin.php?page=hm-mahex-v32-board'));exit;
 }
 public static function page(): void {
  if(!\HoseinMomeni\MahexWoo\V33\Access::can('operations'))return;
  $page=isset($_GET['paged'])&&is_scalar($_GET['paged'])?max(1,absint($_GET['paged'])):1;
  $result=wc_get_orders(['limit'=>50,'page'=>$page,'paginate'=>true,'status'=>['processing','on-hold','pending'],'orderby'=>'date','order'=>'DESC']);
  $users=get_users(['capability__in'=>['manage_woocommerce',\HoseinMomeni\MahexWoo\V33\Access::PACK,\HoseinMomeni\MahexWoo\V33\Access::OPERATIONS],'number'=>100]);$operators=[0=>'بدون مسئول'];foreach($users as $user)$operators[$user->ID]=$user->display_name;
  $groups=array_fill_keys(array_keys(self::STAGES),[]);foreach($result->orders as $order)if(current_user_can('edit_shop_order',$order->get_id()))$groups[self::state($order)['stage']][]=$order;
  echo '<div class="wrap hmx-board" dir="rtl"><h1>تابلوی عملیات داخلی ارسال</h1><p>کارت را جابه‌جا کنید یا از انتخاب مرحله استفاده کنید. این مراحل وضعیت رسمی ماهکس را تغییر نمی‌دهند. سفارش‌های در انتظار، در حال انجام و معلق؛ ۵۰ سفارش در هر صفحه.</p><p id="hmx-board-message" role="status" aria-live="polite"></p>';
  $notice=get_transient('hm_mahex_v32_board_result_'.get_current_user_id());if($notice){echo '<div class="notice notice-info"><p>'.esc_html($notice).'</p></div>';delete_transient('hm_mahex_v32_board_result_'.get_current_user_id());}
  echo '<form id="hmx-board-form" method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="hm_mahex_v32_board">';wp_nonce_field('hm_mahex_v32_board');
  echo '<div class="hmx-board-toolbar"><label>مرحله گروهی ';self::select('bulk_stage',self::STAGES,'packing');echo '</label><label>مسئول گروهی ';self::select('bulk_operator',$operators,0);echo '</label><button class="button button-primary">اعمال به انتخاب‌شده‌ها</button><button type="button" id="hmx-board-print" class="button">چاپ فهرست انتخاب‌شده‌ها</button></div><div class="hmx-board-columns">';
  foreach(self::STAGES as $stage=>$label){echo '<section class="hmx-board-column" data-stage="'.esc_attr($stage).'" aria-label="'.esc_attr($label).'"><h2>'.esc_html($label).' <span class="hmx-board-count">'.count($groups[$stage]).'</span></h2><div class="hmx-board-cards">';foreach($groups[$stage] as $order)self::card($order,$operators);echo '</div></section>';}
  echo '</div></form><p class="hmx-board-pages">';for($i=max(1,$page-2);$i<=min((int)$result->max_num_pages,$page+2);$i++)echo '<a class="button" href="'.esc_url(add_query_arg(['page'=>'hm-mahex-v32-board','paged'=>$i],admin_url('admin.php'))).'">'.($i===$page?'['.$i.']':$i).'</a> ';echo '</p></div>';
 }
 private static function select(string $name,array $options,$value,string $class=''): void {echo '<select class="'.esc_attr($class).'" name="'.esc_attr($name).'">';foreach($options as $key=>$label)echo '<option value="'.esc_attr((string)$key).'" '.selected((string)$value,(string)$key,false).'>'.esc_html($label).'</option>';echo '</select>';}
 private static function card(\WC_Order $order,array $operators): void {
  $id=$order->get_id();$state=self::state($order);if($state['operator']&&!isset($operators[$state['operator']]))$operators[$state['operator']]='کاربر #'.$state['operator'];
  echo '<article class="hmx-board-card" draggable="true" data-id="'.$id.'" data-revision="'.$state['revision'].'"><header><label><input type="checkbox" name="selected[]" value="'.$id.'"> سفارش #'.esc_html($order->get_order_number()).'</label><a href="'.esc_url($order->get_edit_order_url()).'">بازکردن</a></header><p class="hmx-board-customer">'.esc_html($order->get_formatted_billing_full_name()).'</p><p>'.esc_html($order->get_shipping_city()?:$order->get_billing_city()).' · '.(int)$order->get_item_count().' کالا</p><input class="hmx-board-revision" type="hidden" name="orders['.$id.'][revision]" value="'.$state['revision'].'"><label>مرحله ';self::select('orders['.$id.'][stage]',self::STAGES,$state['stage'],'hmx-board-stage');echo '</label><label>مسئول ';self::select('orders['.$id.'][operator]',$operators,$state['operator'],'hmx-board-operator');echo '</label><div class="hmx-board-actions"><button class="button hmx-board-save" name="single" value="'.$id.'">ذخیره</button><button type="submit" name="undo_order" value="'.$id.'" class="button hmx-board-undo" '.(!$state['history']?'disabled':'').'>بازگردانی آخرین تغییر</button>';
  $url=wp_nonce_url(add_query_arg(['action'=>'hm_mahex_print_waybill','order_id'=>$id,'document'=>'combined'],admin_url('admin-post.php')),'hm_mahex_print_waybill:'.$id);echo '<a class="button" target="_blank" rel="noopener" href="'.esc_url($url).'">چاپ بارنامه و تگ</a></div><details><summary>تاریخچه تغییرات</summary><ol>';foreach(array_reverse($state['history']) as $entry)echo '<li>'.esc_html((self::STAGES[$entry['from']]??'').' ← '.(self::STAGES[$entry['to']]??'').' | کاربر #'.($entry['actor']??0).' | '.($entry['at']??'')).'</li>';echo '</ol></details><p><a class="button" href="'.esc_url(add_query_arg(['page'=>'hm-mahex-manual-packing','order_id'=>$id],admin_url('admin.php'))).'">بسته‌بندی دیداری</a> <a class="button" href="'.esc_url(add_query_arg(['page'=>'hm-mahex-label-designer','order_id'=>$id],admin_url('admin.php'))).'">طراح لیبل</a></p></article>';
 }
}
