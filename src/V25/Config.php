<?php

namespace HoseinMomeni\MahexWoo\V25;

final class Config {
	public const OPTION = 'hm_mahex_v25_settings';
	public const DASHBOARD = 'hm_mahex_v25_dashboard';
	public const REPORTS = 'hm_mahex_v25_reports';
	public static function defaults(): array {
		return array(
			'intelligence_enabled'=>true,'automation_enabled'=>true,'finance_enabled'=>true,'archive_enabled'=>true,
			'forecast_days'=>14,'history_days'=>90,'return_risk_threshold'=>65,'anomaly_multiplier'=>3,
			'sla_hours'=>48,'daily_plan_limit'=>50,'monthly_budget_irr'=>0,'archive_after_days'=>730,
			'performance_budget_ms'=>150,'scheduled_reports_enabled'=>true,'release_guard_enabled'=>true,
			'packing_evidence_enabled'=>true,'operator_balance_enabled'=>true,
		);
	}
	public static function all(): array { $raw=get_option(self::OPTION,array()); return array_replace(self::defaults(),is_array($raw)?$raw:array()); }
	public static function bool(string $key,bool $default=false): bool { $v=self::all()[$key]??$default; return in_array($v,array(true,1,'1','yes','on'),true); }
	public static function int(string $key,int $default,int $min=0,int $max=PHP_INT_MAX): int { $v=self::all()[$key]??$default; $v=is_numeric($v)?(int)$v:$default; return max($min,min($max,$v)); }
	public static function sanitize(mixed $input): array {
		$input=is_array($input)?$input:array();$b=static fn(string $k):bool=>!empty($input[$k]);
		$n=static function(string $k,int $d,int $min,int $max)use($input):int{$v=$input[$k]??$d;return is_numeric($v)?max($min,min($max,(int)$v)):$d;};
		return array(
			'intelligence_enabled'=>$b('intelligence_enabled'),'automation_enabled'=>$b('automation_enabled'),'finance_enabled'=>$b('finance_enabled'),'archive_enabled'=>$b('archive_enabled'),
			'forecast_days'=>$n('forecast_days',14,3,90),'history_days'=>$n('history_days',90,14,730),'return_risk_threshold'=>$n('return_risk_threshold',65,1,100),'anomaly_multiplier'=>$n('anomaly_multiplier',3,2,20),
			'sla_hours'=>$n('sla_hours',48,1,720),'daily_plan_limit'=>$n('daily_plan_limit',50,10,500),'monthly_budget_irr'=>$n('monthly_budget_irr',0,0,2000000000000),
			'archive_after_days'=>$n('archive_after_days',730,180,3650),'performance_budget_ms'=>$n('performance_budget_ms',150,25,5000),
			'scheduled_reports_enabled'=>$b('scheduled_reports_enabled'),'release_guard_enabled'=>$b('release_guard_enabled'),'packing_evidence_enabled'=>$b('packing_evidence_enabled'),'operator_balance_enabled'=>$b('operator_balance_enabled'),
		);
	}
}
