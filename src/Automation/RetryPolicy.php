<?php

namespace HoseinMomeni\MahexWoo\Automation;

final class RetryPolicy {
	public function __construct(
		public readonly int $max_attempts = 4,
		public readonly int $initial_delay_seconds = 30,
		public readonly float $multiplier = 2.0,
		public readonly int $max_delay_seconds = 900
	) {
		if ( $max_attempts < 1 || $initial_delay_seconds < 1 || $multiplier < 1 || $max_delay_seconds < $initial_delay_seconds ) {
			throw new \InvalidArgumentException( 'Invalid retry policy.' );
		}
	}

	public function can_retry( int $completed_attempts ): bool {
		return $completed_attempts < $this->max_attempts;
	}

	public function delay_for( int $completed_attempts ): int {
		if ( $completed_attempts < 1 ) {
			throw new \InvalidArgumentException( 'At least one attempt must have completed.' );
		}
		return min( $this->max_delay_seconds, (int) round( $this->initial_delay_seconds * ( $this->multiplier ** ( $completed_attempts - 1 ) ) ) );
	}
}
