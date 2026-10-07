<?php
require_once __DIR__.'/../src/V33/PackingOptimizer.php';
require_once __DIR__.'/../src/V33/Station.php';
require_once __DIR__.'/../src/V33/BoardTools.php';
use HoseinMomeni\MahexWoo\V33\PackingOptimizer;
use HoseinMomeni\MahexWoo\V33\Station;
use HoseinMomeni\MahexWoo\V33\BoardTools;
function stationAssert($yes,$message){if(!$yes)throw new RuntimeException($message);}
$units=['a'=>['product_id'=>1,'name'=>'A','dimensions_mm'=>[20,40,30],'weight_g'=>100,'group'=>'safe','codes'=>['sku1']],'b'=>['product_id'=>1,'name'=>'B','dimensions_mm'=>[20,40,30],'weight_g'=>100,'group'=>'safe','codes'=>['sku1']]];
stationAssert(Station::scanUnit($units,[],'sku1')==='a','First scan consumes first real unit');stationAssert(Station::scanUnit($units,['a'=>true],'sku1')==='b','Second scan consumes second unit');
$thrown=false;try{Station::scanUnit($units,['a'=>true,'b'=>true],'sku1');}catch(InvalidArgumentException $e){$thrown=true;}stationAssert($thrown,'Over-scanning rejected');
$bad=$units;$bad['b']['product_id']=2;$thrown=false;try{Station::scanUnit($bad,[],'sku1');}catch(InvalidArgumentException $e){$thrown=true;}stationAssert($thrown,'Ambiguous codes across products rejected');
$placements=PackingOptimizer::place($units,[40,40,30]);stationAssert($placements!==null&&count($placements)===2,'Geometry fits two rotated items');stationAssert(!PackingOptimizer::overlap($placements[0],$placements[1]),'Placed units do not overlap');stationAssert(PackingOptimizer::place($units,[20,20,20])===null,'Impossible geometry rejected');
foreach($placements as $placement)foreach([0,1,2] as $axis)stationAssert($placement['at'][$axis]+$placement['size'][$axis]<=[40,40,30][$axis],'Every placement inside box');
$profile=['name'=>'box','length_mm'=>40,'width_mm'=>40,'height_mm'=>30,'empty_weight_g'=>10,'max_weight_g'=>1000,'max_items'=>3,'unit_cost_irr'=>100];$rank=PackingOptimizer::rank($units,['box'=>$profile,'expensive'=>array_merge($profile,['unit_cost_irr'=>1000])],1000,200,5000);stationAssert($rank[0]['profile_id']==='box','Feasible materials/freight cost ranking');stationAssert($rank[0]['total_irr']===1300.0,'Cost recomputed canonical IRR');
$stock=['tape'=>['stock'=>5,'unit_cost_irr'=>100]];stationAssert(Station::consumption($stock,['tape'=>2])['tape']['stock']===3,'Confirmed material quantity debited');$thrown=false;try{Station::consumption($stock,['tape'=>6]);}catch(InvalidArgumentException $e){$thrown=true;}stationAssert($thrown,'Insufficient stock rejected without mutation');stationAssert($stock['tape']['stock']===5,'Original stock untouched on failure');
stationAssert(Station::variance(120,100)===20.0,'Real weight variance');$time=BoardTools::timing(0,3600,7200,1);stationAssert($time['late']&&$time['age_hours']===2.0&&$time['stage_hours']===1.0,'Deadline and stage age calculated');stationAssert(BoardTools::candidates([1=>5,2=>10],[1=>5,2=>3],[1=>0,2=>2])===[2],'At-capacity operator excluded');
echo "Station scans, geometry, materials, deadline and capacity tests passed\n";
$thrown=false;try{Station::consumption($stock,['tape'=>INF]);}catch(InvalidArgumentException $e){$thrown=true;}stationAssert($thrown,'Non-finite consumption rejected');
define('ABSPATH',__DIR__);
require_once __DIR__.'/../src/Shipments/OptionOperationLock.php';
require_once __DIR__.'/../src/V33/Access.php';
$GLOBALS['stationOptions']=[];$GLOBALS['stationActor']=1;
function get_option($key,$default=[]){return $GLOBALS['stationOptions'][$key]??$default;}
function update_option($key,$value,...$rest){$GLOBALS['stationOptions'][$key]=$value;return true;}
function add_option($key,$value,...$rest){if(array_key_exists($key,$GLOBALS['stationOptions']))return false;$GLOBALS['stationOptions'][$key]=$value;return true;}
function delete_option($key){unset($GLOBALS['stationOptions'][$key]);}
function current_user_can(...$args){return true;}
function get_current_user_id(){return $GLOBALS['stationActor'];}
function wc_get_order($id){return (object)['id'=>$id];}
function wp_generate_password(...$args){return 'lease-token-123';}
$lease=BoardTools::claim(77,'claim','');stationAssert($lease['actor']===1,'Station lease acquired');
$wrong=false;try{BoardTools::claim(77,'claim','other-tab');}catch(RuntimeException $e){$wrong=true;}stationAssert($wrong,'Second tab blocked even for same operator');
$GLOBALS['stationActor']=2;$wrong=false;try{BoardTools::assertLease(77,$lease['token']);}catch(RuntimeException $e){$wrong=true;}stationAssert($wrong,'Another operator cannot use lease token');
$GLOBALS['stationActor']=1;BoardTools::assertLease(77,$lease['token']);BoardTools::claim(77,'release',$lease['token']);stationAssert(BoardTools::lease(77)===[],'Explicit lease release');
$GLOBALS['stationOptions']['hm_mahex_v33_lease_77']=['actor'=>1,'token'=>'old','expires'=>time()-1];stationAssert(BoardTools::lease(77)===[],'Expired leases no longer lock operator');
echo "Station edit lease isolation tests passed\n";
