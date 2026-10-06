<?php

namespace HoseinMomeni\MahexWoo\Automation;

final class EnqueueResult {
	private function __construct(
		public readonly string $status,
		public readonly ?string $job_id = null,
		public readonly ?string $message = null
	) {}

	public static function queued( string $job_id ): self {
		return new self( 'queued', $job_id );
	}

	public static function duplicate(): self {
		return new self( 'duplicate' );
	}

	public static function unavailable( string $message ): self {
		return new self( 'unavailable', null, $message );
	}

	public static function failed( string $message ): self {
		return new self( 'failed', null, $message );
	}

	public function accepted(): bool {
		return in_array( $this->status, array( 'queued', 'duplicate' ), true );
	}
}
