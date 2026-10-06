<?php

namespace HoseinMomeni\MahexWoo\Automation;

final class FallbackQueueAdapter implements QueueAdapter {
	public function __construct( private readonly QueueAdapter $primary, private readonly QueueAdapter $fallback ) {}

	public function enqueue( Job $job ): EnqueueResult {
		$result = $this->primary->enqueue( $job );
		return $result->accepted() ? $result : $this->fallback->enqueue( $job );
	}
}
