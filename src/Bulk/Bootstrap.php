<?php

namespace HoseinMomeni\MahexWoo\Bulk;

use HoseinMomeni\MahexWoo\Automation\ActionSchedulerAdapter;
use HoseinMomeni\MahexWoo\Automation\FallbackQueueAdapter;
use HoseinMomeni\MahexWoo\Automation\WordPressCronAdapter;
use HoseinMomeni\MahexWoo\Shipments\OptionOperationLock;
use HoseinMomeni\MahexWoo\Shipments\ShipmentService;

defined( 'ABSPATH' ) || exit;

final class Bootstrap {
	public static function register(): void {
		$queue   = new FallbackQueueAdapter( ActionSchedulerAdapter::from_globals(), new WordPressCronAdapter() );
		$service = new BulkQueueService( $queue );
		( new BulkActionController( $service ) )->register();
		( new BulkJobHandler( $queue, array( self::class, 'process' ) ) )->register();
	}

	public static function process( BulkOperation $operation, int $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			throw new \RuntimeException( 'Order was not found.' );
		}
		$lock = new OptionOperationLock();
		if ( ! $lock->acquire( $order_id, 60 ) ) {
			throw new \RuntimeException( 'Shipment operation is already running.' );
		}
		try {
			$service = new ShipmentService();
			match ( $operation ) {
				BulkOperation::CREATE  => $service->create( $order, 0 ),
				BulkOperation::REFRESH => $service->refresh( $order, 0 ) ?? throw new \RuntimeException( 'Shipment does not exist.' ),
				BulkOperation::CANCEL  => $service->cancel( $order, 0 ) ?? throw new \RuntimeException( 'Shipment does not exist.' ),
				BulkOperation::REISSUE => $service->create( $order, 0, true ),
			};
		} finally {
			$lock->release( $order_id );
		}
	}
}
