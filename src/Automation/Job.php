<?php

namespace HoseinMomeni\MahexWoo\Automation;

final class Job {
	public function __construct(
		public readonly string $hook,
		public readonly array $payload,
		public readonly string $group,
		public readonly string $idempotency_key,
		public readonly int $attempt = 0,
		public readonly int $delay_seconds = 0
	) {
		if ( '' === trim( $hook ) || '' === trim( $idempotency_key ) ) {
			throw new \InvalidArgumentException( 'A hook and idempotency key are required.' );
		}
		if ( $attempt < 0 || $delay_seconds < 0 ) {
			throw new \InvalidArgumentException( 'Attempt and delay cannot be negative.' );
		}
	}

	public function arguments(): array {
		return array(
			'payload'         => $this->payload,
			'idempotency_key' => $this->idempotency_key,
			'attempt'         => $this->attempt,
		);
	}
}
