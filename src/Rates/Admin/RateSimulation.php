<?php

namespace HoseinMomeni\MahexWoo\Rates\Admin;

use HoseinMomeni\MahexWoo\Rates\RateResult;

final class RateSimulation {
	/** @param list<string> $consideredRuleIds */
	public function __construct(
		public readonly RateResult $result,
		public readonly int $billableKilograms,
		public readonly int $baseAndWeightAmount,
		public readonly array $consideredRuleIds
	) {}

	/** @return array{total:int,currency:string,chargeable_weight_grams:int,billable_kilograms:int,base_and_weight_amount:int,applied_rule_id:?string,considered_rule_ids:list<string>} */
	public function toArray(): array {
		return array(
			'total'                   => $this->result->total->amount(),
			'currency'                => $this->result->total->currency(),
			'chargeable_weight_grams' => $this->result->chargeableWeightGrams,
			'billable_kilograms'       => $this->billableKilograms,
			'base_and_weight_amount'   => $this->baseAndWeightAmount,
			'applied_rule_id'          => $this->result->appliedRuleId,
			'considered_rule_ids'      => $this->consideredRuleIds,
		);
	}
}
