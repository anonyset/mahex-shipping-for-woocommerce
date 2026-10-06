<?php
require __DIR__.'/../src/V32/Workspace.php';
require __DIR__.'/../src/V32/Rules.php';
require __DIR__.'/../src/V1/RuleEngine.php';
function sanitize_text_field($s){return strip_tags($s);}function sanitize_key($s){return strtolower(preg_replace('/[^A-Za-z0-9_-]/','',$s));}function absint($v){return abs((int)$v);}function sanitize_title($s){return $s;}function wp_generate_password(...$v){return 'fixed-id';}
function check($p,$m){if(!$p)throw new RuntimeException($m);}
use HoseinMomeni\MahexWoo\V32\Workspace;
use HoseinMomeni\MahexWoo\V32\Rules;
$layout=Workspace::normalize(['order'=>['finance','finance','bad',['bad']],'hidden'=>['orders','bad']]);check($layout['order'][0]==='finance'&&count($layout['order'])===6,'Known widgets complete and deduplicated');check($layout['hidden']===['orders'],'Only real widget keys can be hidden');
$rows=Rules::normalize([['name'=>'تهران سنگین','id'=>'rule-1','active'=>true,'province'=>'تهران','min_weight_g'=>2000,'mode'=>'surcharge','amount'=>10000,'categories'=>['cosmetics']]]);
check($rows[0]['priority']===0&&$rows[0]['categories']===['cosmetics'],'Visual ordering and existing category conditions preserved');
$rev=Rules::revision($rows);$rows[0]['amount']=20000;check($rev!==Rules::revision($rows),'Revision detects financial edits');
foreach([['min_weight_g'=>3000,'max_weight_g'=>1000],['amount'=>-1],['amount'=>INF],['name'=>[]]] as $invalid){$failed=false;try{Rules::normalize([array_replace(['name'=>'bad'],$invalid)]);}catch(Throwable $e){$failed=true;}check($failed,'Malformed or unsafe conditions rejected');}
echo "Workspace layout and visual pricing normalization tests passed\n";
