<?php

namespace HoseinMomeni\MahexWoo\Rates\Admin;

use InvalidArgumentException;

final class RateRuleManager {
	public function __construct( private readonly RateRuleRepository $repository ) {}

	/** @return list<RateRuleRecord> */
	public function all(): array {
		$rules = $this->repository->all();
		usort( $rules, static fn ( RateRuleRecord $a, RateRuleRecord $b ): int => $a->priority <=> $b->priority ?: strcmp( $a->id, $b->id ) );
		return $rules;
	}

	/** @return list<RateRuleRecord> */
	public function active(): array {
		return array_values( array_filter( $this->all(), static fn ( RateRuleRecord $rule ): bool => $rule->active ) );
	}

	/** @param array<string,mixed> $input */
	public function create( array $input ): RateRuleRecord {
		$rule = RateRuleRecord::fromArray( $input );
		if ( null !== $this->repository->find( $rule->id ) ) {
			throw new InvalidArgumentException( 'A rate rule with this ID already exists.' );
		}
		$this->repository->save( $rule );
		return $rule;
	}

	/** @param array<string,mixed> $input */
	public function update( string $id, array $input ): RateRuleRecord {
		$existing = $this->repository->find( $id );
		if ( null === $existing ) {
			throw new InvalidArgumentException( 'Rate rule was not found.' );
		}
		$input['id'] = $id;
		$rule        = RateRuleRecord::fromArray( $input );
		$this->repository->save( $rule );
		return $rule;
	}

	public function delete( string $id ): bool {
		return $this->repository->delete( $id );
	}
}
