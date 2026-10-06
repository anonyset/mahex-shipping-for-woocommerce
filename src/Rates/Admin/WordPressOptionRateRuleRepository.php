<?php

namespace HoseinMomeni\MahexWoo\Rates\Admin;

use InvalidArgumentException;

final class WordPressOptionRateRuleRepository implements RateRuleRepository {
	public const OPTION_NAME = 'hm_mahex_rate_rules';

	public function all(): array {
		$stored = get_option( self::OPTION_NAME, array() );
		if ( ! is_array( $stored ) ) {
			return array();
		}
		$rules = array();
		foreach ( $stored as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			try {
				$rules[] = RateRuleRecord::fromArray( $item );
			} catch ( InvalidArgumentException ) {
				// Ignore corrupt legacy entries instead of breaking checkout/admin.
			}
		}
		return $rules;
	}

	public function find( string $id ): ?RateRuleRecord {
		foreach ( $this->all() as $rule ) {
			if ( $id === $rule->id ) {
				return $rule;
			}
		}
		return null;
	}

	public function save( RateRuleRecord $rule ): void {
		$stored = array();
		foreach ( $this->all() as $existing ) {
			$stored[ $existing->id ] = $existing->toArray();
		}
		$stored[ $rule->id ] = $rule->toArray();
		update_option( self::OPTION_NAME, array_values( $stored ), false );
	}

	public function delete( string $id ): bool {
		$stored  = array();
		$deleted = false;
		foreach ( $this->all() as $rule ) {
			if ( $id === $rule->id ) {
				$deleted = true;
				continue;
			}
			$stored[] = $rule->toArray();
		}
		if ( $deleted ) {
			update_option( self::OPTION_NAME, $stored, false );
		}
		return $deleted;
	}
}
