<?php
if(!defined('ABSPATH'))require getenv('MAHEX_WP_ROOT').'/wp-load.php';
wp_set_current_user(1);
use HoseinMomeni\MahexWoo\V34\DispatchWorkbench as D;
use HoseinMomeni\MahexWoo\V34\ServicePolicies as S;
use HoseinMomeni\MahexWoo\V33\Support;
function checkV34($ok,$message){if(!$ok)throw new RuntimeException($message);}
$f=json_decode(file_get_contents(getenv('MAHEX_FIXTURES')),true);$id=$f['orders'][1];
$o=wc_get_order($id);$o->set_shipping_phone('+98 (912)345-6789');$o->save();
$preview=D::preview([$id],'normalize',['fields'=>['phone']]);checkV34(count($preview['rows'][0]['change']['diff'])===1,'Selected phone field diff');
$r=D::apply($preview['token']);checkV34(count($r['done'])===1&&!$r['errors'],'Normalization applied');$o=wc_get_order($id);checkV34($o->get_shipping_phone()==='09123456789','Actual Woo destination corrected');
$blocked=false;try{D::apply($preview['token']);}catch(Throwable $e){$blocked=true;}checkV34($blocked,'Preview one-use token');
$r=D::apply(D::preview([$id],'undo',[])['token']);checkV34(!$r['errors'],'Undo accepted');checkV34(wc_get_order($id)->get_shipping_phone()==='+98 (912)345-6789','Undo persisted original phone');
$r=D::apply(D::preview([$id],'verify',['method'=>'phone','note'=>'Isolated verification fixture'])['token']);checkV34(!$r['errors'],'Verification saved');$o=wc_get_order($id);$o->read_meta_data(true);checkV34(D::reviewed(D::data($o),D::address($o)),'Verification binds actual destination');
$old=$o->get_shipping_city();$o->set_shipping_city($old.' تغییر');$o->save();$o=wc_get_order($id);$o->read_meta_data(true);checkV34(!D::reviewed(D::data($o),D::address($o)),'Changed destination invalidates verification');$o->set_shipping_city($old);$o->save();
$r=D::apply(D::preview([$id],'hold',['reason'=>'other','note'=>'Isolated dispatch hold'])['token']);checkV34(!$r['errors'],'Hold saved');$blocked=false;try{D::assertReady(wc_get_order($id));}catch(Throwable $e){$blocked=true;}checkV34($blocked,'Internal dispatch hold blocks ready');
$blocked=false;try{$heldOrder=wc_get_order($id);$board=HoseinMomeni\MahexWoo\V32\Board::state($heldOrder);HoseinMomeni\MahexWoo\V32\Board::update($id,$board['revision'],'ready',0);}catch(Throwable $e){$blocked=str_contains($e->getMessage(),'توقف داخلی اعزام');}checkV34($blocked,'Legacy board ready mutation respects internal dispatch hold');
$r=D::apply(D::preview([$id],'release',['note'=>'Fixture cleared'])['token']);checkV34(!$r['errors'],'Hold released');
$preview=D::preview([$id],'bin',['bin'=>'QOM-A1']);$o=wc_get_order($id);$o->set_shipping_city($old.' تغییر');$o->save();$r=D::apply($preview['token']);checkV34(count($r['errors'])===1&&!$r['done'],'Stale address cannot apply preview');$o=wc_get_order($id);$o->set_shipping_city($old);$o->delete_meta_data(D::META);$o->save();
$p=S::policy();$promise=S::deadline($o->get_date_created()->date(DATE_ATOM),$p,$o->get_shipping_state(),$o->get_shipping_city());$state=Support::edit($id,S::META,0,fn($s,$order)=>S::transition($s,'promise',['promise'=>$promise],time(),1,$p));
$state=Support::edit($id,S::META,$state['revision'],fn($s,$order)=>S::transition($s,'open',['id'=>'backend-case','type'=>'document','severity'=>'critical','note'=>'Fixture missing document'],time(),1,$p));
$state=Support::edit($id,S::META,$state['revision'],fn($s,$order)=>S::transition($s,'ack',['id'=>'backend-case'],time()+60,1,$p));
$state=Support::edit($id,S::META,$state['revision'],fn($s,$order)=>S::transition($s,'resolve',['id'=>'backend-case','note'=>'Fixture resolved'],time()+120,1,$p));
$o=wc_get_order($id);$o->read_meta_data(true);$persisted=$o->get_meta(S::META,true);checkV34($persisted['incidents']['backend-case']['status']==='closed'&&!empty($persisted['original_deadline']),'Frozen promise and incident persisted via Woo CRUD');$o->delete_meta_data(S::META);$o->save_meta_data();
$privateOrder=wc_create_order();$privateOrder->set_billing_email('private-v34@example.test');$privateOrder->update_meta_data(D::META,['revision'=>1,'packing_instruction'=>'Private fixture']);$privateOrder->update_meta_data(S::META,['revision'=>1,'incidents'=>[]]);$privateOrder->save();$repo=new HoseinMomeni\MahexWoo\Privacy\WooCommercePersonalDataRepository();$export=$repo->export_for_email('private-v34@example.test',1,100);checkV34(count(array_filter($export,fn($x)=>str_contains($x['name'],'Dispatch review')||str_contains($x['name'],'Local service promise')))===2,'Both V34 records included in privacy export');$repo->erase_for_email('private-v34@example.test',1,100);$privateOrder=wc_get_order($privateOrder->get_id());$privateOrder->read_meta_data(true);checkV34($privateOrder->get_meta(D::META,true)===''&&$privateOrder->get_meta(S::META,true)==='','Both V34 records erased');$privateOrder->delete(true);
echo 'V34 real order correction, undo, one-use token, destination drift, hold, stale preview, local promise and incident checks passed'.PHP_EOL;
