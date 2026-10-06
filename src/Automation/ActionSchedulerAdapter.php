<?php

namespace HoseinMomeni\MahexWoo\Automation;

final class ActionSchedulerAdapter implements QueueAdapter {
	private \Closure $available;
	private \Closure $has_scheduled;
	private \Closure $enqueue_async;
	private \Closure $schedule_single;

	public function __construct( callable $available, callable $has_scheduled, callable $enqueue_async, callable $schedule_single ) {
		$this->available       = \Closure::fromCallable( $available );
		$this->has_scheduled   = \Closure::fromCallable( $has_scheduled );
		$this->enqueue_async   = \Closure::fromCallable( $enqueue_async );
		$this->schedule_single = \Closure::fromCallable( $schedule_single );
	}

	public static function from_globals(): self {
		return new self(
			static fn(): bool => function_exists( 'as_enqueue_async_action' ) && function_exists( 'as_schedule_single_action' ),
			static fn( string $hook, array $args, string $group ): bool => function_exists( 'as_has_scheduled_action' ) && false !== as_has_scheduled_action( $hook, $args, $group ),
			static fn( string $hook, array $args, string $group ) => as_enqueue_async_action( $hook, $args, $group, true ),
			static fn( int $timestamp, string $hook, array $args, string $group ) => as_schedule_single_action( $timestamp, $hook, $args, $group, true )
		);
	}

	public function enqueue( Job $job ): EnqueueResult {
		if ( ! ( $this->available )() ) {
			return EnqueueResult::unavailable( 'Action Scheduler is not loaded.' );
		}

		$args = $job->arguments();
		try {
			if ( ( $this->has_scheduled )( $job->hook, $args, $job->group ) ) {
				return EnqueueResult::duplicate();
			}
			$id = $job->delay_seconds > 0
				? ( $this->schedule_single )( time() + $job->delay_seconds, $job->hook, $args, $job->group )
				: ( $this->enqueue_async )( $job->hook, $args, $job->group );

			return is_int( $id ) && $id > 0
				? EnqueueResult::queued( (string) $id )
				: EnqueueResult::failed( 'Action Scheduler rejected the job.' );
		} catch ( \Throwable $error ) {
			return EnqueueResult::failed( $error->getMessage() );
		}
	}
}
