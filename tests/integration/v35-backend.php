<?php
if(!defined('ABSPATH'))require getenv('MAHEX_WP_ROOT').'/wp-load.php';
add_filter('pre_wp_mail',static fn()=>true);wp_set_current_user(1);
use HoseinMomeni\MahexWoo\V35\LocalHandover as H;
use HoseinMomeni\MahexWoo\V35\OrderQuality as Q;
use HoseinMomeni\MahexWoo\V32\Packing as P;
use HoseinMomeni\MahexWoo\V33\Station as S;
function checkV35($ok,$message){if(!$ok)throw new RuntimeException($message);}
$f=json_decode(file_get_contents(getenv('MAHEX_FIXTURES')),true);
function manifestFixture($product){
 $o=wc_create_order();$o->add_product(wc_get_product($product),1);$o->set_address(['first_name'=>'مانیفست','last_name'=>'آزمایشی','address_1'=>'قم نشانی آزمون','city'=>'قم','state'=>'QOM','postcode'=>'1234567890','country'=>'IR','phone'=>'09120000000','email'=>'manifest-v35@example.test'],'billing');$o->set_address($o->get_address('billing'),'shipping');$o->set_status('processing');$o->save();
 $units=P::units($o);$profile=get_option('hm_mahex_packaging_profiles')['e2e-box'];$plan=P::validate([['profile_id'=>'e2e-box','units'=>array_keys($units)]],$units,['e2e-box'=>$profile]);$plan['revision']=1;$o->update_meta_data(P::META,$plan);$o->update_meta_data(S::META,['revision'=>1,'scans'=>[],'parcels'=>[0=>['weight_g'=>600,'dimensions_mm'=>[300,200,150]]]]);$o->save_meta_data();return $o;
}
// Data-quality review is tied to current evidence and persists with Woo CRUD.
$quality=wc_get_order($f['quality_order']);$before=$quality->get_total();$scan=Q::scan($quality,true);$missing=array_values(array_filter($scan['findings'],fn($x)=>$x['code']==='missing_sku'))[0];
$review=Q::review($quality->get_id(),$missing['id'],$missing['fingerprint'],'confirmed','Fixture SKU requires correction',0);
checkV35($review['summary']['confirmed']===1,'Quality review persisted');checkV35(wc_get_order($quality->get_id())->get_total()===$before,'Quality review cannot alter totals');
$staleOrder=manifestFixture($f['product']);$plan=$staleOrder->get_meta(P::META,true);$plan['fingerprint']='bad-a';$staleOrder->update_meta_data(P::META,$plan);$staleOrder->save_meta_data();$scan=Q::scan($staleOrder,true);$finding=array_values(array_filter($scan['findings'],fn($x)=>$x['code']==='packing_drift'))[0];Q::review($staleOrder->get_id(),$finding['id'],$finding['fingerprint'],'dismissed','Fixture reviewed snapshot',0);
$staleOrder=wc_get_order($staleOrder->get_id());$plan['fingerprint']='bad-b';$staleOrder->update_meta_data(P::META,$plan);$staleOrder->save_meta_data();$scan=Q::scan($staleOrder,true);$findingNew=array_values(array_filter($scan['findings'],fn($x)=>$x['code']==='packing_drift'))[0];checkV35($findingNew['state']==='stale','Changed evidence invalidates review');$blocked=false;try{Q::review($staleOrder->get_id(),$finding['id'],$finding['fingerprint'],'confirmed','Old evidence forbidden',1);}catch(Throwable $e){$blocked=true;}checkV35($blocked,'Stale evidence cannot be reviewed');
wp_set_current_user($f['ops_user']);$opsExport=Q::exportData(wc_get_order($quality->get_id()),false);checkV35(!array_filter($opsExport['findings'],fn($x)=>$x['financial']),'Ops export excludes finance findings');$financial=array_values(array_filter(Q::scan(wc_get_order($quality->get_id()),true)['findings'],fn($x)=>$x['financial']))[0];$blocked=false;try{Q::review($quality->get_id(),$financial['id'],$financial['fingerprint'],'confirmed','Forged financial review',1);}catch(Throwable $e){$blocked=true;}checkV35($blocked,'Ops cannot review crafted financial finding');
wp_set_current_user($f['print_user']);$blocked=false;try{Q::review($quality->get_id(),$missing['id'],$missing['fingerprint'],'confirmed','Forged print-only review',1);}catch(Throwable $e){$blocked=true;}checkV35($blocked,'Print-only cannot modify quality');wp_set_current_user(1);
// Reset isolated fixture so browser starts with an open nonfinancial finding.
Q::erase(wc_get_order($quality->get_id()));$staleOrder->delete(true);
echo 'V35 real order quality review, evidence drift, financial and per-order capability checks passed'.PHP_EOL;
// Complete independent group lifecycle, including drift, duplicate membership and partial results.
$a=manifestFixture($f['product']);$b=manifestFixture($f['product']);
$payload=['order_id'=>$a->get_id(),'title'=>'دفتر آزمون backend','origin'=>'انبار قم','destination'=>'مقصد داخلی','route'=>'branch','start'=>'2026-10-07T10:00','end'=>'2026-10-07T14:00','capacity'=>3];
$g=H::create($payload);$g=H::mutate($g['id'],$g['revision'],'add',['order_id'=>$b->get_id()]);checkV35($g['totals']['orders']===2&&$g['totals']['parcels']===2&&(float)$g['totals']['weight_g']===1200.0,'Actual group aggregates measured orders');
$blocked=false;try{H::create($payload);}catch(Throwable $e){$blocked=true;}checkV35($blocked,'Active membership exclusive');
$blocked=false;try{H::mutate($g['id'],1,'seal',['seal'=>'SEAL-OLD']);}catch(Throwable $e){$blocked=true;}checkV35($blocked,'Stale group revision rejected');
$b=wc_get_order($b->get_id());$b->set_shipping_city('تهران');$b->save();$blocked=false;try{H::mutate($g['id'],$g['revision'],'seal',['seal'=>'SEAL-A']);}catch(Throwable $e){$blocked=true;}checkV35($blocked,'Changed shipment prevents seal');$g=H::mutate($g['id'],$g['revision'],'refresh',['order_id'=>$b->get_id(),'note'=>'مقصد بازبینی شد']);
$g=H::mutate($g['id'],$g['revision'],'reorder',['order'=>[$b->get_id(),$a->get_id()]]);checkV35($g['order']===[$b->get_id(),$a->get_id()],'Group order persisted');$g=H::mutate($g['id'],$g['revision'],'seal',['seal'=>'SEAL-35']);$sealed=$g['sealed_digest'];
$blocked=false;try{H::mutate($g['id'],$g['revision'],'remove',['order_id'=>$b->get_id(),'note'=>'Invalid after seal']);}catch(Throwable $e){$blocked=true;}checkV35($blocked,'Sealed membership immutable');
$g=H::mutate($g['id'],$g['revision'],'dispatch',['receiver'=>'راننده آزمایشی','reference'=>'OUT-35']);$g=H::mutate($g['id'],$g['revision'],'transfer',['receiver'=>'مسئول دوم','place'=>'دفتر داخلی','note'=>'انتقال ثبت دستی']);
$g=H::mutate($g['id'],$g['revision'],'receive',['order_id'=>$a->get_id(),'accepted'=>1,'condition'=>'intact','receiver'=>'پذیرنده اول','reference'=>'IN-A']);
$blocked=false;try{H::mutate($g['id'],$g['revision'],'close',[]);}catch(Throwable $e){$blocked=true;}checkV35($blocked,'Partial receipt cannot close');
$g=H::mutate($g['id'],$g['revision'],'receive',['order_id'=>$b->get_id(),'accepted'=>0,'condition'=>'refused','receiver'=>'پذیرنده دوم','reference'=>'IN-B','note'=>'پذیرش انجام نشد']);checkV35($g['totals']['received_parcels']===1&&$g['totals']['exceptions']===1,'Refusal separated from accepted');$blocked=false;try{H::mutate($g['id'],$g['revision'],'close',[]);}catch(Throwable $e){$blocked=true;}checkV35($blocked,'Exception closure requires note');$g=H::mutate($g['id'],$g['revision'],'close',['note'=>'مغایرت بازبینی و ثبت شد']);
checkV35($g['state']==='closed'&&$g['sealed_digest']===$sealed,'Closed journal and sealed digest persisted');$fresh=wc_get_order($b->get_id());$fresh->read_meta_data(true);checkV35(H::meta($fresh)['refs'][$g['id']]['state']==='closed','Group state in actual order CRUD');
$private=new HoseinMomeni\MahexWoo\Privacy\WooCommercePersonalDataRepository();$privacyOrder=wc_create_order();$privacyOrder->set_billing_email('quality-private-v35@example.test');$privacyOrder->update_meta_data(Q::META,['revision'=>1,'reviews'=>[],'history'=>[]]);$privacyOrder->save();$export=$private->export_for_email('quality-private-v35@example.test',1,100);checkV35(count(array_filter($export,fn($x)=>str_contains($x['name'],'Order quality')))===1,'V35 quality standard privacy export');$private->erase_for_email('quality-private-v35@example.test',1,100);checkV35(wc_get_order($privacyOrder->get_id())->get_meta(Q::META,true)==='','V35 quality standard privacy erase');$privacyOrder->delete(true);
$personal=H::privacy(wc_get_order($a->get_id()));checkV35(!str_contains(wp_json_encode($personal,JSON_UNESCAPED_UNICODE),'پذیرنده دوم'),'Anchor privacy export excludes other member receipt details');
$b=wc_get_order($b->get_id());$b->set_billing_email('nonanchor-private-v35@example.test');$b->save();$private->erase_for_email('nonanchor-private-v35@example.test',1,100);checkV35(H::get($g['id'])['redacted'],'Erasing nonanchor member also removes shared personal journal');
// Separate fresh orders for actual browser drag and lifecycle checks.
$c=manifestFixture($f['product']);$d=manifestFixture($f['product']);$f['handover_orders']=[$c->get_id(),$d->get_id()];file_put_contents(getenv('MAHEX_FIXTURES'),wp_json_encode($f));
echo 'V35 real group lifecycle, actual measurements, revision conflicts, drift, refusal and frozen digest checks passed'.PHP_EOL;
