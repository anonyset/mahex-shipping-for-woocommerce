<?php

namespace HoseinMomeni\MahexWoo\Automation;

final class WordPressCronAdapter implements QueueAdapter {
	public function enqueue( Job $job ): EnqueueResult {
		if ( ! function_exists( 'wp_schedule_single_event' ) || ! function_exists( 'wp_next_scheduled' ) ) {
			return EnqueueResult::unavailable( 'WordPress Cron is not loaded.' );
		}
		$args = $job->arguments();
		if ( false !== wp_next_scheduled( $job->hook, $args ) ) {
			return EnqueueResult::duplicate();
		}
		$result = wp_schedule_single_event( time() + max( 1, $job->delay_seconds ), $job->hook, $args, true );
		if ( is_wp_error( $result ) ) {
			return EnqueueResult::failed( $result->get_error_message() );
		}
		return true === $result ? EnqueueResult::queued( 'wp-cron:' . $job->idempotency_key ) : EnqueueResult::failed( 'WordPress Cron rejected the job.' );
	}
}
