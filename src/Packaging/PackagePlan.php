<?php

namespace HoseinMomeni\MahexWoo\Packaging;

final class PackagePlan {
	/** @param list<array<string, mixed>> $packages */
	public function __construct( private readonly array $packages ) {}

	/** @return list<array<string, mixed>> */
	public function packages(): array {
		return $this->packages;
	}

	public function totalChargeableWeightGrams(): int {
		return array_sum( array_column( $this->packages, 'chargeable_weight_g' ) );
	}
}
