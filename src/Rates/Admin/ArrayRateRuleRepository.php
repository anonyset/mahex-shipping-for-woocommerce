<?php

namespace HoseinMomeni\MahexWoo\Rates\Admin;

final class ArrayRateRuleRepository implements RateRuleRepository {
	/** @var array<string,RateRuleRecord> */
	private array $rules = array();

	/** @param list<RateRuleRecord> $rules */
	public function __construct( array $rules = array() ) {
		foreach ( $rules as $rule ) {
			$this->rules[ $rule->id ] = $rule;
		}
	}

	public function all(): array {
		return array_values( $this->rules );
	}

	public function find( string $id ): ?RateRuleRecord {
		return $this->rules[ $id ] ?? null;
	}

	public function save( RateRuleRecord $rule ): void {
		$this->rules[ $rule->id ] = $rule;
	}

	public function delete( string $id ): bool {
		if ( ! isset( $this->rules[ $id ] ) ) {
			return false;
		}
		unset( $this->rules[ $id ] );
		return true;
	}
}
