<?php

namespace HoseinMomeni\MahexWoo\Rates\Admin;

use HoseinMomeni\MahexWoo\Domain\Money;
use HoseinMomeni\MahexWoo\Rates\RateRule;
use InvalidArgumentException;

final class RateRuleRecord {
	public function __construct(
		public readonly string $id,
		public readonly string $name,
		public readonly int $priority,
		public readonly string $mode,
		public readonly int $amount,
		public readonly string $currency = 'IRR',
		public readonly ?string $province = null,
		public readonly ?string $city = null,
		public readonly bool $active = true
	) {
		if ( '' === $id || 1 !== preg_match( '/^[a-z0-9_-]{1,80}$/', $id ) ) {
			throw new InvalidArgumentException( 'Rule ID is invalid.' );
		}
		if ( '' === trim( $name ) || $priority < 0 || $amount < 0 ) {
			throw new InvalidArgumentException( 'Rule name, priority, or amount is invalid.' );
		}
		if ( ! in_array( $mode, array( RateRule::FIXED, RateRule::SURCHARGE ), true ) ) {
			throw new InvalidArgumentException( 'Rule mode is invalid.' );
		}
		if ( 1 !== preg_match( '/^[A-Z]{3}$/', $currency ) ) {
			throw new InvalidArgumentException( 'Currency is invalid.' );
		}
		if ( null !== $city && null === $province ) {
			throw new InvalidArgumentException( 'A city rule must also specify a province.' );
		}
	}

	/** @param array<string,mixed> $input */
	public static function fromArray( array $input ): self {
		$nullable = static function ( mixed $value ): ?string {
			$value = trim( (string) $value );
			return '' === $value ? null : $value;
		};

		return new self(
			strtolower( trim( (string) ( $input['id'] ?? '' ) ) ),
			trim( (string) ( $input['name'] ?? '' ) ),
			filter_var( $input['priority'] ?? null, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 0 ) ) ) ?: 0,
			(string) ( $input['mode'] ?? '' ),
			self::nonNegativeInteger( $input['amount'] ?? null ),
			strtoupper( trim( (string) ( $input['currency'] ?? 'IRR' ) ) ),
			$nullable( $input['province'] ?? '' ),
			$nullable( $input['city'] ?? '' ),
			filter_var( $input['active'] ?? false, FILTER_VALIDATE_BOOL )
		);
	}

	public function toDomainRule(): RateRule {
		return new RateRule( $this->id, $this->priority, $this->mode, new Money( $this->amount, $this->currency ), $this->province, $this->city );
	}

	/** @return array{id:string,name:string,priority:int,mode:string,amount:int,currency:string,province:?string,city:?string,active:bool} */
	public function toArray(): array {
		return get_object_vars( $this );
	}

	private static function nonNegativeInteger( mixed $value ): int {
		$validated = filter_var( $value, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 0 ) ) );
		if ( false === $validated ) {
			throw new InvalidArgumentException( 'Amount must be a non-negative integer in the smallest currency unit.' );
		}
		return $validated;
	}
}
