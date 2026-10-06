<?php

namespace HoseinMomeni\MahexWoo\Rates;

use HoseinMomeni\MahexWoo\Domain\Money;

final class RateResult {
	public function __construct(
		public readonly Money $total,
		public readonly int $chargeableWeightGrams,
		public readonly ?string $appliedRuleId
	) {}
}
