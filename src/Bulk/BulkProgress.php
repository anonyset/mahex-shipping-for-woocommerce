<?php

namespace HoseinMomeni\MahexWoo\Bulk;

final class BulkProgress {
	public function __construct(
		public readonly int $total,
		public readonly int $queued,
		public readonly int $completed = 0,
		public readonly int $failed = 0,
		public readonly int $duplicates = 0
	) {
		foreach ( array( $total, $queued, $completed, $failed, $duplicates ) as $value ) {
			if ( $value < 0 ) {
				throw new \InvalidArgumentException( 'Progress values cannot be negative.' );
			}
		}
		if ( $queued + $completed + $failed > $total ) {
			throw new \InvalidArgumentException( 'Progress cannot exceed its total.' );
		}
	}

	public function processed(): int {
		return $this->completed + $this->failed;
	}

	public function percentage(): int {
		return 0 === $this->total ? 100 : (int) floor( ( $this->processed() / $this->total ) * 100 );
	}

	public function to_array(): array {
		return array(
			'total'      => $this->total,
			'queued'     => $this->queued,
			'completed'  => $this->completed,
			'failed'     => $this->failed,
			'duplicates' => $this->duplicates,
			'processed'  => $this->processed(),
			'percentage' => $this->percentage(),
		);
	}
}
