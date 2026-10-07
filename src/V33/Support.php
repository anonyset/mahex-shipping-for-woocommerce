<?php
namespace HoseinMomeni\MahexWoo\V33;

final class Support {
 public static function can(string $area,int $id=0): bool {return class_exists(Access::class)?Access::can($area,$id):(current_user_can('manage_woocommerce')&&(!$id||current_user_can('edit_shop_order',$id)));}
 public static function auth(string $action,string $area='operations',int $id=0): void {if(!self::can($area,$id))wp_die('دسترسی مجاز نیست.','',['response'=>403]);check_admin_referer($action);}
 public static function form(string $action): void {echo '<form method="post" enctype="multipart/form-data" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="'.esc_attr($action).'">';wp_nonce_field($action);}
 public static function input(string $key,string $default=''): string {return isset($_POST[$key])&&is_scalar($_POST[$key])?wp_unslash((string)$_POST[$key]):$default;}
 public static function number($value,float $max=1000000000000): float {if(!is_scalar($value)||!is_numeric($value)||!is_finite((float)$value)||(float)$value<0||(float)$value>$max)throw new \InvalidArgumentException('عدد خارج از محدوده است.');return (float)$value;}
 public static function edit(int $id,string $key,int $revision,callable $callback): array {
  $lock=new \HoseinMomeni\MahexWoo\Shipments\OptionOperationLock();if(!$lock->acquire($id))throw new \RuntimeException('سفارش در حال ویرایش است.');
  try{$order=wc_get_order($id);if(!$order)throw new \RuntimeException('سفارش پیدا نشد.');$order->read_meta_data(true);$old=$order->get_meta($key,true);$old=is_array($old)?$old:[];if($revision!==(int)($old['revision']??0))throw new \RuntimeException('اطلاعات تغییر کرده است؛ صفحه را تازه کنید.');$next=$callback($old,$order);$next['revision']=$revision+1;$next['updated_at']=gmdate('c');$next['actor']=get_current_user_id();$order->update_meta_data($key,$next);$order->save_meta_data();return $next;}finally{$lock->release($id);}
 }
 public static function redirect(string $slug,string $message,int $id=0): void {set_transient('hm_mahex_v33_notice_'.get_current_user_id(),$message,600);wp_safe_redirect(add_query_arg(['page'=>$slug,'order_id'=>$id],admin_url('admin.php')));exit;}
 public static function notice(): void {$message=get_transient('hm_mahex_v33_notice_'.get_current_user_id());if($message){echo '<div class="notice notice-info"><p>'.esc_html($message).'</p></div>';delete_transient('hm_mahex_v33_notice_'.get_current_user_id());}}
 public static function orderChooser(string $slug,int $id): void {echo '<form method="get"><input type="hidden" name="page" value="'.esc_attr($slug).'"><label>شماره داخلی سفارش <input type="number" name="order_id" min="1" value="'.esc_attr($id?:'').'"></label> <button class="button">باز کردن</button></form>';}
 public static function file(array $upload,array $mimes,int $limit=3145728): array {
  if(($upload['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||!is_string($upload['tmp_name']??null)||!is_uploaded_file($upload['tmp_name'])||filesize($upload['tmp_name'])>$limit)throw new \InvalidArgumentException('فایل نامعتبر یا بزرگ‌تر از حد مجاز است.');
  $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);if(!in_array($mime,$mimes,true))throw new \InvalidArgumentException('نوع فایل مجاز نیست.');$bytes=file_get_contents($upload['tmp_name']);if(str_starts_with($mime,'image/')){$size=getimagesize($upload['tmp_name']);if(!$size||$size[0]>6000||$size[1]>6000)throw new \InvalidArgumentException('ابعاد تصویر نامعتبر یا بیش از حد است.');}if($mime==='application/pdf'&&!str_starts_with($bytes,'%PDF-'))throw new \InvalidArgumentException('PDF معتبر نیست.');
  return ['name'=>sanitize_file_name($upload['name']??'document'),'mime'=>$mime,'bytes'=>base64_encode($bytes),'sha256'=>hash('sha256',$bytes),'created_at'=>gmdate('c')];
 }
 public static function download(array $file): void {$bytes=base64_decode($file['bytes']??'',true);if($bytes===false||!hash_equals((string)($file['sha256']??''),hash('sha256',$bytes)))wp_die('فایل خراب است.');nocache_headers();header('Content-Type: '.$file['mime']);header('X-Content-Type-Options: nosniff');header('Content-Disposition: attachment; filename="'.sanitize_file_name($file['name']).'"');header('Content-Length: '.strlen($bytes));echo $bytes;exit;}
}
