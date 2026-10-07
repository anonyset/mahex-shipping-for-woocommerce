<?php
namespace HoseinMomeni\MahexWoo\V33;

/** Axis-aligned extreme-point heuristic. Feasible placements are checked, never claimed optimal. */
final class PackingOptimizer {
 public static function rotations(array $d): array {$out=[];foreach([[0,1,2],[0,2,1],[1,0,2],[1,2,0],[2,0,1],[2,1,0]] as $r){$v=[$d[$r[0]],$d[$r[1]],$d[$r[2]]];$out[implode(':',$v)]=$v;}return array_values($out);}
 public static function overlap(array $a,array $b): bool {for($i=0;$i<3;$i++)if($a['at'][$i]+$a['size'][$i]<=$b['at'][$i]||$b['at'][$i]+$b['size'][$i]<=$a['at'][$i])return false;return true;}
 public static function place(array $units,array $box): ?array {
  if(count($box)!==3||min($box)<=0||count($units)>200)return null;
  uasort($units,static fn($a,$b)=>array_product($b['dimensions_mm'])<=>array_product($a['dimensions_mm']));$points=[[0,0,0]];$placed=[];
  foreach($units as $id=>$unit){$dims=$unit['dimensions_mm']??[];if(count($dims)!==3||min($dims)<=0)return null;$found=null;
   usort($points,static fn($a,$b)=>[$a[2],$a[1],$a[0]]<=>[$b[2],$b[1],$b[0]]);
   foreach($points as $point){foreach(self::rotations($dims) as $rotation){$candidate=['id'=>(string)$id,'name'=>(string)($unit['name']??$id),'at'=>$point,'size'=>$rotation];$fits=true;for($i=0;$i<3;$i++)if($point[$i]+$rotation[$i]>$box[$i])$fits=false;if(!$fits)continue;foreach($placed as $old)if(self::overlap($candidate,$old)){$fits=false;break;}if($fits){$found=$candidate;break 2;}}}
   if(!$found)return null;$placed[]=$found;for($i=0;$i<3;$i++){$next=$found['at'];$next[$i]+=$found['size'][$i];if($next[$i]<$box[$i])$points[]=$next;}
   $unique=[];foreach($points as $p)$unique[implode(':',$p)]=$p;$points=array_values($unique);
  }return $placed;
 }
 /** Canonical IRR prices; comparisons do not alter checkout charges. */
 public static function rank(array $units,array $profiles,float $base,float $perKg,int $divisor=5000): array {
  if(!$units)return [];
  $groups=array_unique(array_column($units,'group'));if(count($groups)>1)return [];$result=[];
  foreach($profiles as $id=>$p){$dims=[(int)($p['length_mm']??0),(int)($p['width_mm']??0),(int)($p['height_mm']??0)];$weight=max(0,(int)($p['empty_weight_g']??0))+array_sum(array_column($units,'weight_g'));if($weight>(int)($p['max_weight_g']??0)||((int)($p['max_items']??0)>0&&count($units)>(int)$p['max_items']))continue;
   $placements=self::place($units,$dims);if($placements===null)continue;$billable=max($weight,(int)ceil(array_product($dims)/max(1000,$divisor)));$freight=max(0,$base)+max(0,$perKg)*ceil($billable/1000);$materials=max(0,(int)($p['unit_cost_irr']??0));$result[]=['profile_id'=>(string)$id,'name'=>(string)($p['name']??$id),'dimensions_mm'=>$dims,'placements'=>$placements,'weight_g'=>$weight,'billable_g'=>$billable,'materials_irr'=>$materials,'freight_irr'=>$freight,'total_irr'=>$materials+$freight];
  }usort($result,static fn($a,$b)=>$a['total_irr']<=>$b['total_irr']);return $result;
 }
}
