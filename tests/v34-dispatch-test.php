<?php
namespace HoseinMomeni\MahexWoo\V33 {final class BoardTools {public static array $blocked=[];static function guardOtherEditor($id){if(in_array($id,self::$blocked,true))throw new \RuntimeException('leased');}}}
namespace HoseinMomeni\MahexWoo\V1 {final class ActivityLog {static function write(...$args){}}}
namespace {
define('ABSPATH',__DIR__);$options=[];$transients=[];$orders=[];$denied=[];$actor=7;
function add_option($k,$v,...$a){global $options;if(isset($options[$k]))return false;$options[$k]=$v;return true;}
function get_option($k,$default=0){global $options;return $options[$k]??$default;}
function delete_option($k){global $options;unset($options[$k]);}
function set_transient($k,$v,$ttl){global $transients;$transients[$k]=$v;}
function get_transient($k){global $transients;return $transients[$k]??false;}
function delete_transient($k){global $transients;unset($transients[$k]);}
function current_user_can($cap,...$args){global $denied;return $cap==='edit_shop_order'?!in_array($args[0],$denied,true):$cap==='manage_woocommerce';}
function get_current_user_id(){global $actor;return $actor;}
function get_userdata($id){return (object)['display_name'=>'Operator '.$id];}
function absint($v){return abs((int)$v);}
function wc_get_order($id){global $orders;return isset($orders[$id])?clone $orders[$id]:false;}
function wc_get_orders($args){global $orders;return array_map(fn($o)=>clone $o,array_values($orders));}
function admin_url($p){return 'https://store.test/wp-admin/'.$p;}
function add_query_arg($args,$url){return $url.'?'.http_build_query($args);}
class DispatchOrder {
 public array $fields=[],$meta=[];public int $saves=0;
 function __construct(public int $id){foreach(['first_name','last_name','country','state','city','address_1','address_2','postcode','phone'] as $f){$this->fields['billing_'.$f]='';$this->fields['shipping_'.$f]='';}$this->fields=array_merge($this->fields,['billing_first_name'=>'علي','billing_country'=>'IR','billing_state'=>'QHM','billing_city'=>'  قم ','billing_address_1'=>'  مدرس  ۲ ','billing_postcode'=>'۱۲۳۴۵-۶۷۸۹۰','billing_phone'=>'+98 912 345 6789']);}
 function __call($method,$args){if(str_starts_with($method,'get_'))return $this->fields[substr($method,4)]??'';if(str_starts_with($method,'set_')){$this->fields[substr($method,4)]=$args[0];return;}throw new \RuntimeException('unknown method');}
 // method_exists requires concrete shipping methods, as on WC_Order.
 function get_shipping_address_1(){return $this->fields['shipping_address_1'];}
 function get_shipping_first_name(){return $this->fields['shipping_first_name'];}function get_shipping_last_name(){return $this->fields['shipping_last_name'];}
 function get_shipping_country(){return $this->fields['shipping_country'];}function get_shipping_state(){return $this->fields['shipping_state'];}function get_shipping_city(){return $this->fields['shipping_city'];}
 function get_shipping_address_2(){return $this->fields['shipping_address_2'];}function get_shipping_postcode(){return $this->fields['shipping_postcode'];}function get_shipping_phone(){return $this->fields['shipping_phone'];}
 function get_id(){return $this->id;}function get_order_number(){return 'MH-'.$this->id;}function get_status(){return 'processing';}function get_date_created(){return new \DateTimeImmutable('2026-10-01');}
 function get_meta($k,$single=true){return $this->meta[$k]??'';}function update_meta_data($k,$v){$this->meta[$k]=$v;}function read_meta_data($fresh){}
 function save(){global $orders;$this->saves++;$orders[$this->id]=clone $this;return $this->id;}
}
require __DIR__.'/../src/Shipments/OptionOperationLock.php';require __DIR__.'/../src/Enterprise/AddressTools.php';require __DIR__.'/../src/V33/Access.php';require __DIR__.'/../src/V34/DispatchWorkbench.php';
use HoseinMomeni\MahexWoo\V34\DispatchWorkbench as W;
function check($condition,$message){if(!$condition)throw new \RuntimeException($message);}
function rejects($callback,$label){try{$callback();}catch(\Throwable $e){return;}throw new \RuntimeException('Expected rejection: '.$label);}
$completeShipping=new DispatchOrder(99);foreach(['first_name','last_name','country','state','city','address_1','address_2','postcode','phone'] as $field)$completeShipping->fields['shipping_'.$field]=$completeShipping->fields['billing_'.$field];$address=W::address($completeShipping);check(!isset(W::remediation($address)['issues']['mixed']),'empty optional fields do not invent mixed destination');$completeShipping->fields['shipping_city']='';$address=W::address($completeShipping);check(isset(W::remediation($address)['issues']['mixed']),'actual billing fallback remains flagged');
$o=new DispatchOrder(10);$a=W::address($o);$d=W::data($o);check(count(array_unique($a['source']))===1&&$a['source']['phone']==='billing','billing destination fallback');
$fix=W::remediation($a);check($fix['diff']['phone']['new']==='09123456789'&&$fix['diff']['postcode']['new']==='1234567890','historical digit/phone remediation');check($fix['diff']['first_name']['new']==='علی','historical text correction');
$o->fields['shipping_address_1']='Shipping';$o->fields['shipping_phone']='09351234567';$mix=W::remediation(W::address($o));check(isset($mix['issues']['mixed'],$mix['issues']['phone_conflict']),'mixed sources and phone conflict');
$a['fields']['address_1']="قم\u{2066} 12";$a['fields']['postcode']='123O567890';$f=W::remediation($a);check(isset($f['issues']['bidi'],$f['issues']['postcode_letters'])&&!isset($f['diff']['postcode']),'ambiguous postcode not erased');check(!str_contains($f['diff']['address_1']['new'],"\u{2066}"),'bidi control correction');
check(W::deadline('2026-10-07T12:00Z')==='2026-10-07T12:00:00Z','UTC minute parsing');rejects(fn()=>W::deadline('2026-02-30T12:00:00Z'),'invalid date');rejects(fn()=>W::deadline('2026-10-07T12:00:00+03:30'),'non UTC');
rejects(fn()=>W::transition($d,$a,'hold',['reason'=>'other'],7),'other requires note');rejects(fn()=>W::transition($d,$a,'release',[],7),'release requires note');rejects(fn()=>W::transition($d,$a,'priority',['priority'=>'vip'],7),'priority reason');rejects(fn()=>W::transition($d,$a,'bin',['bin'=>'<bad>'],7),'bin characters');rejects(fn()=>W::transition($d,$a,'verify',['method'=>'api','note'=>'x'],7),'verification method');rejects(fn()=>W::transition($d,$a,'checklist',['checks'=>['unknown'=>true]],7),'checklist key');
$d=W::transition($d,$a,'verify',['method'=>'phone','note'=>'تماس انجام شد'],7);check(W::reviewed($d,$a)&&$d['verification']['actor']===7,'verification actor/fingerprint');$a2=$a;$a2['fields']['city']='Tehran';check(!W::reviewed($d,$a2),'changed address invalidates verification');
$d=W::transition($d,$a,'checklist',['checks'=>array_fill_keys(array_keys(W::CHECKS),true)],7);check(W::readiness($d,$a,time())['ready'],'full internal checklist ready');check(!W::checklist($d,$a2)['address_reviewed'],'address check invalidates');
$d=W::transition($d,$a,'hold',['reason'=>'contact','note'=>'check','review_at'=>'2026-01-01T00:00:00Z'],7);check(W::readiness($d,$a,time())['overdue_hold']&&!W::readiness($d,$a,time())['ready'],'overdue hold/readiness');$d=W::transition($d,$a,'release',['note'=>'checked'],7);check(!$d['hold']&&$d['history'][array_key_last($d['history'])]['detail']['release_note']==='checked','release audit');
for($i=0;$i<25;$i++)$d=W::transition($d,$a,'bin',['bin'=>'QOM-'.$i],7);check(count($d['history'])===20,'bounded audit');
$d=W::transition($d,$a,'instructions',['packing_instruction'=>'<b>Wrap</b>','contact_instruction'=>'Call','recipient_only'=>true,'do_not_bend'=>true],7);check($d['packing_instruction']==='Wrap'&&$d['recipient_only']&&$d['do_not_bend'],'instructions sanitizer');
$orders=[10=>new DispatchOrder(10),11=>new DispatchOrder(11)];rejects(fn()=>W::preview([0],'normalize',[]),'zero id');rejects(fn()=>W::preview([[10]],'normalize',[]),'nested id');rejects(fn()=>W::preview([10],'normalize',['fields'=>['unknown']]),'unknown correction field');
$p=W::preview([10],'normalize',['fields'=>['phone']]);check($orders[10]->saves===0,'preview readonly');$r=W::apply($p['token']);check(count($r['done'])===1&&!$r['errors'],'selected remediation applies');check($orders[10]->fields['billing_phone']==='09123456789'&&$orders[10]->fields['billing_city']==='  قم ','unselected fields unchanged');rejects(fn()=>W::apply($p['token']),'one time token');
$p=W::preview([10],'undo',[]);$r=W::apply($p['token']);check(!$r['errors']&&$orders[10]->fields['billing_phone']==='+98 912 345 6789','undo restores exact old phone');
$p=W::preview([10,11],'bin',['bin'=>'QOM-A1']);$orders[11]->fields['billing_city']='changed';$r=W::apply($p['token']);check(count($r['done'])===1&&count($r['errors'])===1&&$r['errors'][0]['id']===11,'partial bulk conflict results');check(W::data($orders[10])['bin']==='QOM-A1'&&W::data($orders[11])['bin']==='','conflict never writes');
$p=W::preview([10],'bin',['bin'=>'QOM-C3']);$state=W::data($orders[10]);$state['revision']++;$orders[10]->meta[W::META]=$state;$r=W::apply($p['token']);check(count($r['errors'])===1,'revision conflict rejects stale preview');
$p=W::preview([10],'bin',['bin'=>'QOM-C3']);$options['hm_mahex_shipment_lock_10']=time()+60;$r=W::apply($p['token']);check(count($r['errors'])===1,'order lock rejects concurrent writer');unset($options['hm_mahex_shipment_lock_10']);
$p=W::preview([10],'priority',['priority'=>'urgent','note'=>'rush']);$denied=[10];$r=W::apply($p['token']);check(count($r['errors'])===1,'permission rechecked on apply');$denied=[];
$p=W::preview([10],'bin',['bin'=>'QOM-B2']);\HoseinMomeni\MahexWoo\V33\BoardTools::$blocked=[10];$r=W::apply($p['token']);check(count($r['errors'])===1,'lease prevents save');\HoseinMomeni\MahexWoo\V33\BoardTools::$blocked=[];
$p=W::preview([10],'bin',['bin'=>'QOM-B2']);$key=W::tokenKey($p['token']);$transients[$key]['created']=time()-601;rejects(fn()=>W::apply($p['token']),'expired token');
$p=W::preview([10],'bin',['bin'=>'QOM-B2']);$actor=8;rejects(fn()=>W::apply($p['token']),'user token binding');$actor=7;
$p=W::preview([10],'normalize',[]);W::apply($p['token']);$orders[10]->fields['billing_city']='other';rejects(fn()=>W::preview([10],'undo',[]),'undo after independent edit');
$orders[10]->meta[W::META]=W::transition(W::data($orders[10]),W::address($orders[10]),'hold',['reason'=>'payment'],7);rejects(fn()=>W::assertReady($orders[10]),'held board transition');
echo "V34 dispatch remediation, workflow, preview/apply, partial conflict, permissions, leases, token and undo tests passed\n";
}
