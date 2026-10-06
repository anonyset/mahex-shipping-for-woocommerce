<?php

namespace HoseinMomeni\MahexWoo\Bulk;

final class BatchPlanner {
	public function __construct( public readonly int $batch_size = 20 ) {
		if ( $batch_size < 1 || $batch_size > 100 ) {
			throw new \InvalidArgumentException( 'Batch size must be between 1 and 100.' );
		}
	}

	/** @return list<list<int>> */
	public function plan( array $order_ids ): array {
		$ids = array_values( array_unique( array_filter( array_map( 'intval', $order_ids ), static fn( int $id ): bool => $id > 0 ) ) );
		sort( $ids, SORT_NUMERIC );
		return array_chunk( $ids, $this->batch_size );
	}
}
