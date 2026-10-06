<?php
namespace HoseinMomeni\MahexWoo\V31;

/** Immutable scheduled pricing revisions; amounts use canonical IRR. */
final class FinanceRates {
 const OPTION = 'hm_mahex_v31_tariffs';
 public static function register(): void {
  add_action('admin_menu', [self::class,'menu']);
  add_action('admin_post_hm_mahex_schedule_tariff', [self::class,'save']);
  add_filter('woocommerce_cart_shipping_packages',[self::class,'packages']);
 }
 public static function packages(array $packages): array { $stamp=hash('sha256',wp_json_encode([self::active(),\HoseinMomeni\MahexWoo\Shipping\PricingSettings::all()]));foreach($packages as &$package)$package['hm_mahex_tariff_revision']=$stamp;unset($package);return $packages; }
 public static function active(?string $date = null): array {
  $date = $date ?? wp_date('Y-m-d'); $rows = get_option(self::OPTION, []); $active = [];
  foreach (is_array($rows)?$rows:[] as $row) {
   if (is_array($row) && ($row['date']??'9999') <= $date && (!$active || $row['date'] >= $active['date'])) $active=$row;
  }
  return $active;
 }
 public static function settings(array $current): array {
  $row=self::active(); return isset($row['settings']) && is_array($row['settings']) ? array_replace($current,$row['settings']) : $current;
 }
 public static function menu(): void { add_submenu_page('hm-mahex','تعرفه‌های تاریخ‌دار','تعرفه‌های تاریخ‌دار','manage_woocommerce','hm-mahex-tariffs',[self::class,'page']); }
 public static function page(): void {
  if(!current_user_can('manage_woocommerce')) return;
  echo '<div class="wrap" dir="rtl"><h1>تعرفه‌های تاریخ‌دار</h1><p>مبالغ به ریال ذخیره می‌شوند. تعرفه از تاریخ انتخاب‌شده در منطقه زمانی وردپرس اجرا می‌شود. اطلاعات مالی سفارش‌های قبلی تغییر نمی‌کند. تعرفه‌های زمان‌بندی‌شده بر نرخ پایه فعلی اولویت دارند.</p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
  wp_nonce_field('hm_mahex_schedule_tariff');
  echo '<input type="hidden" name="action" value="hm_mahex_schedule_tariff"><p><label>عنوان <input name="title" required maxlength="100"></label></p><p><label>تاریخ شروع (میلادی) <input type="date" name="date" required></label></p>';
  foreach(self::fields() as $key=>$label) echo '<p><label>'.esc_html($label).' (ریال) <input type="number" min="0" step="1" name="'.esc_attr($key).'" required></label></p>';
  submit_button('ثبت نسخه تعرفه'); echo '</form><table class="widefat"><tr><th>عنوان</th><th>تاریخ</th><th>شناسه نسخه</th></tr>';
  foreach(array_reverse((array)get_option(self::OPTION,[])) as $row) echo '<tr><td>'.esc_html($row['title']).'</td><td>'.esc_html($row['date']).'</td><td>'.esc_html($row['id']).'</td></tr>';
  echo '</table></div>';
 }
 public static function fields(): array { return ['manual_base_cost'=>'کرایه پایه','manual_cost_per_kg'=>'هر کیلوگرم','manual_packaging_cost'=>'بسته‌بندی','manual_insurance_cost'=>'بیمه']; }
 public static function save(): void {
  if(!current_user_can('manage_woocommerce')) wp_die('دسترسی مجاز نیست.',403);
  check_admin_referer('hm_mahex_schedule_tariff');
  $date=sanitize_text_field(wp_unslash($_POST['date']??'')); $parsed=\DateTimeImmutable::createFromFormat('!Y-m-d',$date);
  if(!$parsed || $parsed->format('Y-m-d')!==$date) wp_die('تاریخ نامعتبر است.');
  $settings=[];
  foreach(self::fields() as $key=>$label) { $v=wp_unslash($_POST[$key]??''); if(!is_scalar($v)||!is_numeric($v)||!is_finite((float)$v)||(float)$v<0) wp_die('مبلغ نامعتبر است.'); $settings[$key]=(float)$v; }
  $rows=get_option(self::OPTION,[]); $rows=is_array($rows)?$rows:[];
  $rows[]=['id'=>wp_generate_uuid4(),'title'=>sanitize_text_field(wp_unslash($_POST['title']??'')),'date'=>$date,'settings'=>$settings,'created_at'=>current_time('mysql',true)];
  update_option(self::OPTION,$rows,false);
  if(WC()->session) WC()->session->set('shipping_for_package_0',null);
  wp_safe_redirect(admin_url('admin.php?page=hm-mahex-tariffs')); exit;
 }
}
