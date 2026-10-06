<?php
namespace HoseinMomeni\MahexWoo\V31;

/** Advisory validation; never silently substitutes parcel data. */
final class OperationsValidation {
 public static function digits(string $value): string {
  return strtr($value, array_combine(preg_split('//u','۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩',-1,PREG_SPLIT_NO_EMPTY),str_split('01234567890123456789')));
 }
 public static function errors(\WC_Order $order): array {
  $errors=[];
  $phone=preg_replace('/[\s()\-]/','',self::digits((string)$order->get_billing_phone()));
  if(!preg_match('/^(?:09\d{9}|\+989\d{9}|00989\d{9})$/',$phone))$errors[]='شماره موبایل ایرانی معتبر نیست.';
  $shipping=trim((string)$order->get_shipping_address_1())!=='';
  $postcode=self::digits((string)($shipping?$order->get_shipping_postcode():$order->get_billing_postcode()));
  if(!preg_match('/^\d{10}$/',$postcode))$errors[]='کدپستی باید ۱۰ رقم باشد.';
  foreach(['address_1'=>'آدرس','city'=>'شهر','state'=>'استان'] as $field=>$label){$method='get_'.($shipping?'shipping_':'billing_').$field;if(trim((string)$order->$method())==='')$errors[]=$label.' گیرنده خالی است.';}
  foreach($order->get_items() as $item){$product=$item->get_product();if($product&&!$product->is_virtual()&&(!is_numeric($product->get_weight())||(float)$product->get_weight()<=0))$errors[]='وزن محصول «'.$item->get_name().'» ثبت نشده است.';}
  return array_values(array_unique($errors));
 }
}
