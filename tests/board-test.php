<?php
require_once __DIR__.'/../src/V32/Board.php';
require_once __DIR__.'/../src/V33/Access.php';
use HoseinMomeni\MahexWoo\V32\Board;
function assertBoard($ok,$message){if(!$ok)throw new RuntimeException($message);}
$initial=['stage'=>'review','revision'=>0,'operator'=>0,'history'=>[]];
$packing=Board::transition($initial,0,'packing',7,9);
assertBoard($packing['revision']===1&&$packing['stage']==='packing'&&$packing['operator']===7,'Move assigns operator and increments revision');
assertBoard($packing['history'][0]['actor']===9&&$packing['history'][0]['operator_from']===0,'Audit attributes actor and prior operator');
$stale=false;try{Board::transition($packing,0,'ready',7,9);}catch(RuntimeException $e){$stale=true;}assertBoard($stale,'Stale concurrent revision rejected');
assertBoard(Board::transition($packing,1,'packing',7,9)===$packing,'Idempotent update adds no audit');
$undo=Board::transition($packing,1,'packing',7,9,true);assertBoard($undo['stage']==='review'&&$undo['operator']===0&&$undo['revision']===2,'Undo restores stage and operator with new revision');
assertBoard($undo['history'][1]['action']==='undo','Undo retained in audit');
$invalid=false;try{Board::transition($initial,0,'delivered',0,9);}catch(InvalidArgumentException $e){$invalid=true;}assertBoard($invalid,'Official carrier status cannot be an internal stage');
$state=$initial;for($i=0;$i<40;$i++)$state=Board::transition($state,$state['revision'],$i%2?'review':'packing',0,9);assertBoard(count($state['history'])===30&&$state['revision']===40,'History bounded while revision remains monotonic');
class WC_Order{public function get_meta(...$args){return ['stage'=>'delivered','revision'=>-1,'operator'=>-1,'history'=>'bad'];}}
assertBoard(Board::state(new WC_Order())===$initial,'Corrupted metadata normalized');
function current_user_can($cap,$id=0){return false;}
$denied=false;try{Board::update(1,0,'ready',0);}catch(RuntimeException $e){$denied=true;}assertBoard($denied,'Permissions checked before mutation');
echo "Board revision, undo, audit, permission tests passed\n";
