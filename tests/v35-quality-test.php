<?php
require_once __DIR__.'/../src/V32/Packing.php';
require_once __DIR__.'/../src/V35/OrderQuality.php';
use HoseinMomeni\MahexWoo\V35\OrderQuality as Q;
function wp_json_encode($v){return json_encode($v);}
function ck($c,$m){if(!$c)throw new RuntimeException($m);}
function rejects(callable $f){try{$f();}catch(Throwable $e){return;}throw new RuntimeException('Expected rejection');}
function codes($s,$finance=false){return array_column(Q::analyze($s,$finance,1000),'code');}
$s=['lines'=>[['id'=>1,'product_id'=>10,'exists'=>true,'virtual'=>false,'quantity'=>1,'sku'=>'A','total'=>10]],'shipping'=>[['id'=>20,'method'=>'flat_rate']],'created'=>100,'paid'=>200,'completed'=>300,'customer_missing'=>false,'status'=>'processing','units'=>['1:0'=>['id'=>'1:0']],'packing'=>[],'station'=>[],'total'=>10,'refund'=>0,'shipping_total'=>2,'payment'=>'cod','currency'=>'IRR'];
ck(!codes($s,true),'clean data');$f=$s;$f['lines']=[];ck(in_array('no_physical',codes($f)),'virtual/no lines');
$f=$s;$f['lines'][0]['exists']=false;ck(in_array('orphan_product',codes($f)),'deleted product');
foreach([0,-1,'abc',INF] as $v){$f=$s;$f['lines'][0]['quantity']=$v;ck(in_array('quantity_invalid',codes($f)),'invalid qty');}
$f=$s;$f['lines'][0]['quantity']=0;$a=Q::analyze($f)[0];$f['lines'][0]['quantity']=-1;$b=Q::analyze($f)[0];ck($a['fingerprint']!==$b['fingerprint'],'changed invalid qty invalidates review');
$f=$s;$f['lines'][0]['quantity']=1.5;ck(in_array('quantity_fraction',codes($f)),'fraction');
$f=$s;$f['lines'][]=array_replace($f['lines'][0],['id'=>2,'product_id'=>11]);ck(in_array('sku_collision',codes($f)),'sku collision');
$f=$s;$f['lines'][0]['sku']='';ck(in_array('missing_sku',codes($f)),'sku missing');
$f=$s;$f['packing']=['fingerprint'=>'old','boxes'=>[['name'=>'کارتن / الف']]];ck(in_array('packing_drift',codes($f)),'packing drift');$a=Q::analyze($f);$f['packing']['fingerprint']='new';$b=Q::analyze($f);ck($a[0]['fingerprint']!==$b[0]['fingerprint'],'drift evidence changes');
$f=$s;$f['station']['committed_fingerprint']='old';ck(in_array('commit_items_drift',codes($f)),'consumption item drift');
$f=$s;$f['packing']=['fingerprint'=>HoseinMomeni\MahexWoo\V32\Packing::fingerprint($f['units']),'boxes'=>[['name'=>'کارتن / الف']]];$f['station']['committed_plan_hash']=hash('sha256',json_encode($f['packing']['boxes']));ck(!in_array('commit_plan_drift',codes($f)),'unicode plan hash same encoding');$f['station']['committed_plan_hash']='old';ck(in_array('commit_plan_drift',codes($f)),'consumption plan drift');
$limited=$s;$limited['unit_error']=true;$limited['units']=[];$limited['packing']=['fingerprint'=>'old','boxes'=>[]];$limited['station']=['committed_fingerprint'=>'old','scans'=>['gone:0'=>[]]];ck(!array_intersect(['packing_drift','commit_items_drift','orphan_scan'],codes($limited)),'incomplete snapshot never invents drift');
$f=$s;$f['station']['scans']=['gone:0'=>[]];ck(in_array('orphan_scan',codes($f)),'orphan scan');$f=$s;$f['station']['parcels']=[4=>[]];ck(in_array('orphan_parcel',codes($f)),'orphan measurement');
$f=$s;$f['shipping']=[];ck(in_array('no_shipping_method',codes($f)),'missing shipping');$f=$s;$f['shipping'][]=['id'=>21,'method'=>''];$c=codes($f);ck(in_array('multiple_shipping',$c)&&in_array('empty_shipping_method',$c),'multiple/missing id');
$variants=['missing_payment_method'=>['payment'=>''],'negative_total'=>['total'=>-1],'over_refunded'=>['refund'=>11],'negative_shipping'=>['shipping_total'=>-1],'empty_currency'=>['currency'=>''],'refunded_active'=>['refund'=>10]];
foreach($variants as $code=>$patch){$f=array_replace($s,$patch);ck(in_array($code,codes($f,true)),$code);ck(!in_array($code,codes($f,false)),'finance private '.$code);}
$f=$s;$f['lines'][0]['total']=INF;ck(in_array('nonfinite_item',codes($f,true))&&!in_array('nonfinite_item',codes($f)),'nonfinite private');
$f=array_replace($s,['paid'=>50,'completed'=>60,'created'=>2000,'customer_missing'=>true]);$c=codes($f);foreach(['paid_before_created','completed_before_created','future_created','missing_customer'] as $code)ck(in_array($code,$c),$code);
$money=array_replace($s,['refund'=>11]);$a=Q::analyze($money,true);$money['refund']=12;$b=Q::analyze($money,true);ck($a[0]['fingerprint']!==$b[0]['fingerprint'],'amount changes stale review without disclosed amount');
$f=$s;$f['lines'][0]['sku']='';$finding=Q::analyze($f)[0];$d=['revision'=>0,'reviews'=>[],'history'=>[]];rejects(fn()=>Q::transition($d,$finding,'confirmed','',1,1000));rejects(fn()=>Q::transition($d,$finding,'bad','test',1,1000));$d=Q::transition($d,$finding,'confirmed','reviewed',1,1000);ck($d['reviews'][$finding['id']]['actor']===1&&$d['revision']===1,'review evidence');$d=Q::transition($d,$finding,'dismissed','acceptable',2,1100);ck($d['reviews'][$finding['id']]['verdict']==='dismissed','false positive');$d=Q::transition($d,$finding,'reset','recheck',2,1200);ck(!isset($d['reviews'][$finding['id']])&&count($d['history'])===3,'reset preserves history');for($i=0;$i<60;$i++)$d=Q::transition($d,$finding,'confirmed','reviewed',1,1300+$i);ck(count($d['history'])===50,'bounded history');
class FakeOrder {public $saved=0;public $meta;function __construct($m){$this->meta=$m;}function get_meta($k,$v){return $this->meta;}function delete_meta_data($k){$this->meta=[];}function save_meta_data(){$this->saved++;}}
$o=new FakeOrder($d);ck(Q::privacy($o)['revision']===$d['revision'],'privacy output');ck(Q::erase($o)&&$o->saved===1&&!Q::erase($o),'erase own metadata');
echo "V35 quality: structural detectors, snapshot drift, financial redaction, review states, history and privacy passed\n";
