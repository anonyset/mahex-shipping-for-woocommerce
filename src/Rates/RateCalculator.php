<?php

namespace HoseinMomeni\MahexWoo\Rates;

use HoseinMomeni\MahexWoo\Domain\Money;

final class RateCalculator {
	/** @param list<RateRule> $rules */
	public function calculate( Money $base, Money $perKilogram, int $chargeableWeightGrams, string $province, string $city, array $rules = array() ): RateResult {
		$chargeableWeightGrams = max( 0, $chargeableWeightGrams );
		$kilograms = max( 1, intdiv( $chargeableWeightGrams + 999, 1000 ) );
		$total     = $base->add( $perKilogram->multiply( $kilograms ) );
		usort( $rules, static fn ( RateRule $left, RateRule $right ): int => $left->priority <=> $right->priority ?: strcmp( $left->id, $right->id ) );
		foreach ( $rules as $rule ) {
			if ( ! $rule->matches( $province, $city ) ) {
				continue;
			}
			$total = RateRule::FIXED === $rule->mode ? $rule->amount : $total->add( $rule->amount );
			return new RateResult( $total, $chargeableWeightGrams, $rule->id );
		}
		return new RateResult( $total, $chargeableWeightGrams, null );
	}
}
