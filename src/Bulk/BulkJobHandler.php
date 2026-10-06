<?php

namespace HoseinMomeni\MahexWoo\Bulk;

use HoseinMomeni\MahexWoo\Automation\Job;
use HoseinMomeni\MahexWoo\Automation\QueueAdapter;
use HoseinMomeni\MahexWoo\Automation\RetryPolicy;

final class BulkJobHandler {
	private \Closure $processor;

	public function __construct(
		private readonly QueueAdapter $queue,
		callable $processor,
		private readonly RetryPolicy $retry_policy = new RetryPolicy()
	) {
		$this->processor = \Closure::fromCallable( $processor );
	}

	public function register(): void {
		if ( function_exists( 'add_action' ) ) {
			add_action( BulkQueueService::JOB_HOOK, array( $this, 'handle' ), 10, 3 );
		}
	}

	public function handle( array $payload, string $idempotency_key, int $attempt = 0 ): BulkProgress {
		$operation = BulkOperation::from_input( (string) ( $payload['operation'] ?? '' ) );
		$order_ids = ( new BatchPlanner( 100 ) )->plan( (array) ( $payload['order_ids'] ?? array() ) )[0] ?? array();
		$completed = 0;
		$failed    = 0;
		$failed_ids = array();

		foreach ( $order_ids as $order_id ) {
			try {
				( $this->processor )( $operation, $order_id );
				++$completed;
			} catch ( \Throwable $error ) {
				++$failed;
				$failed_ids[] = $order_id;
			}
		}

		$completed_attempts = $attempt + 1;
		if ( $failed > 0 && $this->retry_policy->can_retry( $completed_attempts ) ) {
			$retry_payload              = $payload;
			$retry_payload['order_ids'] = $failed_ids;
			$this->queue->enqueue(
				new Job(
					BulkQueueService::JOB_HOOK,
					$retry_payload,
					BulkQueueService::JOB_GROUP,
					$idempotency_key . ':retry:' . $completed_attempts,
					$completed_attempts,
					$this->retry_policy->delay_for( $completed_attempts )
				)
			);
		}

		return new BulkProgress( count( $order_ids ), 0, $completed, $failed );
	}
}
