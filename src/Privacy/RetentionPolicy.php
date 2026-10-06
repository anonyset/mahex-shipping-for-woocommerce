<?php

namespace HoseinMomeni\MahexWoo\Privacy;

final class RetentionPolicy {
	public function __construct( public readonly int $days = 180 ) {
		if ( $days < 1 || $days > 3650 ) {
			throw new \InvalidArgumentException( 'Retention must be between 1 and 3650 days.' );
		}
	}

	public function cutoff( \DateTimeImmutable $now ): \DateTimeImmutable {
		return $now->setTimezone( new \DateTimeZone( 'UTC' ) )->modify( sprintf( '-%d days', $this->days ) );
	}

	public function is_expired( \DateTimeImmutable $created_at, \DateTimeImmutable $now ): bool {
		return $created_at < $this->cutoff( $now );
	}
}
