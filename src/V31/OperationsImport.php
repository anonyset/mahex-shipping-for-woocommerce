<?php
namespace HoseinMomeni\MahexWoo\V31;
use HoseinMomeni\MahexWoo\Shipments\Shipment;
use HoseinMomeni\MahexWoo\Shipments\OrderShipmentStore;

final class OperationsImport {
 /** Strict CSV parser shared by preview and commit. No writes in preview. */
 public static function parse(string $path, string $format='csv'): array {
  if($format==='xlsx'){
   $handle=fopen('php://temp','w+b');
   foreach(FinanceXlsx::readRows($path) as $cells)fputcsv($handle,$cells,',','"','');
   rewind($handle);
  } else {$handle=fopen($path,'rb');}if(!$handle)throw new \RuntimeException('فایل خوانده نشد.');
  try {
   $header=fgetcsv($handle,0,',','"','');
   if(!$header)throw new \RuntimeException('فایل خالی است.');
   $header[0]=preg_replace('/^\xEF\xBB\xBF/','',$header[0]);
   if($header!==['order_id','waybill','tracking','status'])throw new \RuntimeException('ستون‌ها باید دقیقاً order_id,waybill,tracking,status باشند.');
   $rows=[];$seen=[];$line=1;
   while(($data=fgetcsv($handle,0,',','"',''))!==false){$line++;if($data===[null])continue;if(count($rows)>=500)throw new \RuntimeException('حداکثر ۵۰۰ ردیف مجاز است.');
    $errors=[];$data=array_map(static fn($v)=>trim((string)$v),$data);
    if(count($data)!==4){$rows[]=['line'=>$line,'data'=>$data,'errors'=>['تعداد ستون‌ها نادرست است.']];continue;}
    [$id,$waybill,$tracking,$status]=$data;
    if(!ctype_digit($id)||(int)$id<1)$errors[]='شناسه سفارش نامعتبر است.';
    foreach([$waybill,$tracking] as $code)if(!preg_match('/^[A-Za-z0-9_-]{3,80}$/',$code))$errors[]='بارنامه و رهگیری باید ۳ تا ۸۰ نویسهٔ عدد، حرف یا خط تیره باشند.';
    if(!in_array($status,Shipment::STATUSES,true))$errors[]='وضعیت نامعتبر است.';
    if(isset($seen[$id]))$errors[]='سفارش تکراری است.';$seen[$id]=true;
    $order=ctype_digit($id)?wc_get_order((int)$id):false;
    if(!$order||!current_user_can('edit_shop_order',(int)$id))$errors[]='سفارش موجود نیست یا دسترسی کافی ندارید.';
    if($order){if(count((new OrderShipmentStore())->getAll($order))>1)$errors[]='سفارش چندبسته‌ای است؛ واردکردن گروهی برای آن مجاز نیست.';if(in_array($status,['picked_up','in_transit','out_for_delivery'],true))$errors=array_merge($errors,OperationsValidation::errors($order));}
    $rows[]=['line'=>$line,'data'=>$data,'errors'=>$errors];
   }
   return $rows;
  } finally {fclose($handle);}
 }
 public static function apply(array $row): void {
  [$id,$waybill,$tracking,$status]=$row['data'];
  $lock=new \HoseinMomeni\MahexWoo\Shipments\OptionOperationLock();
  if(!$lock->acquire((int)$id))throw new \RuntimeException('عملیات دیگری روی سفارش در حال اجراست.');
  try { self::applyLocked($row); } finally { $lock->release((int)$id); }
 }
 private static function applyLocked(array $row): void {
  [$id,$waybill,$tracking,$status]=$row['data'];$order=wc_get_order((int)$id);
  if(!$order||!current_user_can('edit_shop_order',(int)$id))throw new \RuntimeException('دسترسی سفارش تغییر کرده است.');
  $store=new OrderShipmentStore();$all=$store->getAll($order);if(count($all)>1)throw new \RuntimeException('سفارش چندبسته‌ای است.');
  if(in_array($status,['picked_up','in_transit','out_for_delivery'],true)&&OperationsValidation::errors($order))throw new \RuntimeException('اطلاعات ارسال ناقص است.');
  $old=$all[0]??null;
  if($old&&$old->waybill_number===$waybill&&$old->tracking_code===$tracking&&$old->status===$status)return;
  $now=gmdate('c');$snapshot=$old?$old->package_snapshot:[];$snapshot['_manual_carrier_reference']=true;
  $next=new Shipment($old?$old->shipment_id:'manual-'.wp_generate_uuid4(),$tracking,$waybill,$status,$snapshot,$old?$old->created_at:$now,$now,$old?$old->revision+1:1);
  $store->save($order,$next,'manual_csv_import',get_current_user_id());
  $order->add_order_note('ماهکس: بارنامه و وضعیت با CSV و به‌صورت دستی ثبت شد.');
 }
}
