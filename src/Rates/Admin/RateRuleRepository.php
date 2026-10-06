<?php

namespace HoseinMomeni\MahexWoo\Rates\Admin;

interface RateRuleRepository {
	/** @return list<RateRuleRecord> */
	public function all(): array;

	public function find( string $id ): ?RateRuleRecord;

	public function save( RateRuleRecord $rule ): void;

	public function delete( string $id ): bool;
}
