<?php

namespace HoseinMomeni\MahexWoo\Automation;

interface QueueAdapter {
	public function enqueue( Job $job ): EnqueueResult;
}
