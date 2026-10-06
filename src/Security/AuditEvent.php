<?php

namespace HoseinMomeni\MahexWoo\Security;

final class AuditEvent {
	/** @param array<string, scalar|null> $context */
	public function __construct(
		public readonly string $action,
		public readonly string $outcome,
		public readonly int $actor_id,
		public readonly \DateTimeImmutable $occurred_at,
		public readonly array $context = array()
	) {
		if ( 1 !== preg_match( '/^[a-z][a-z0-9_.-]{1,63}$/', $action ) ) {
			throw new \InvalidArgumentException( 'Invalid audit action.' );
		}
		if ( ! in_array( $outcome, array( 'success', 'failure', 'denied' ), true ) || $actor_id < 0 ) {
			throw new \InvalidArgumentException( 'Invalid audit event.' );
		}
		foreach ( $context as $key => $value ) {
			if ( ! is_string( $key ) || is_array( $value ) || is_object( $value ) || is_resource( $value ) ) {
				throw new \InvalidArgumentException( 'Audit context must contain flat scalar values.' );
			}
		}
	}

	/** @return array<string, mixed> */
	public function to_array( SecretRedactor $redactor = new SecretRedactor() ): array {
		return array(
			'action'      => $this->action,
			'outcome'     => $this->outcome,
			'actor_id'    => $this->actor_id,
			'occurred_at' => $this->occurred_at->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d\TH:i:s\Z' ),
			'context'     => $redactor->redact( $this->context ),
		);
	}
}
