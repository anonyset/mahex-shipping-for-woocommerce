<?php

namespace HoseinMomeni\MahexWoo\Rates\Admin;

use HoseinMomeni\MahexWoo\Domain\Money;
use HoseinMomeni\MahexWoo\Rates\RateCalculator;

final class RateDryRunSimulator {
	public function __construct(
		private readonly RateRuleManager $manager,
		private readonly RateCalculator $calculator = new RateCalculator()
	) {}

	public function simulate( Money $base, Money $perKilogram, int $weightGrams, string $province, string $city ): RateSimulation {
		$rules = array();
		foreach ( $this->manager->active() as $record ) {
			if ( $record->currency === $base->currency() ) {
				$rules[] = $record->toDomainRule();
			}
		}
		$weightGrams = max( 0, $weightGrams );
		$kilograms   = max( 1, intdiv( $weightGrams + 999, 1000 ) );
		$unruled     = $base->add( $perKilogram->multiply( $kilograms ) );
		$result      = $this->calculator->calculate( $base, $perKilogram, $weightGrams, trim( $province ), trim( $city ), $rules );

		return new RateSimulation( $result, $kilograms, $unruled->amount(), array_map( static fn ( $rule ): string => $rule->id, $rules ) );
	}
}
