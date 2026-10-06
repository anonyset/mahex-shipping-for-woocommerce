<?php

namespace HoseinMomeni\MahexWoo\V25;

final class PerformanceBudget {
	public static function evaluate(): array {$budget=Config::int('performance_budget_ms',150,25,5000);$summary=\HoseinMomeni\MahexWoo\V1\Profiler::summary();$out=array();foreach($summary as $key=>$r){$p95=(float)($r['p95']??0);$out[]=array('key'=>$key,'p95'=>$p95,'avg'=>(float)($r['avg']??0),'budget_ms'=>$budget,'pass'=>$p95<=$budget,'over_by'=>max(0,$p95-$budget));}usort($out,static fn($a,$b)=>$b['over_by']<=>$a['over_by']);return $out;}
}
