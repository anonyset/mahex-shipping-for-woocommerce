<?php

namespace HoseinMomeni\MahexWoo\Bulk;

use HoseinMomeni\MahexWoo\Automation\Job;
use HoseinMomeni\MahexWoo\Automation\QueueAdapter;

final class BulkQueueService {
	public const JOB_HOOK  = 'hm_mahex_bulk_shipment_job';
	public const JOB_GROUP = 'hm-mahex';

	public function __construct(
		private readonly QueueAdapter $queue,
		private readonly BatchPlanner $planner = new BatchPlanner()
	) {}

	public function enqueue( BulkOperation $operation, array $order_ids, string $request_key ): BulkProgress {
		if ( '' === trim( $request_key ) ) {
			throw new \InvalidArgumentException( 'A request key is required for idempotency.' );
		}
		$batches    = $this->planner->plan( $order_ids );
		$total      = array_sum( array_map( 'count', $batches ) );
		$queued     = 0;
		$failed     = 0;
		$duplicates = 0;

		foreach ( $batches as $index => $ids ) {
			$key    = hash( 'sha256', $request_key . '|' . $operation->value . '|' . implode( ',', $ids ) );
			$result = $this->queue->enqueue(
				new Job(
					self::JOB_HOOK,
					array( 'operation' => $operation->value, 'order_ids' => $ids, 'batch_index' => $index ),
					self::JOB_GROUP,
					$key
				)
			);
			if ( 'queued' === $result->status ) {
				$queued += count( $ids );
			} elseif ( 'duplicate' === $result->status ) {
				$duplicates += count( $ids );
			} else {
				$failed += count( $ids );
			}
		}

		return new BulkProgress( $total, $queued, 0, $failed, $duplicates );
	}
}
