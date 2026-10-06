<?php

namespace HoseinMomeni\MahexWoo\Shipments;

defined( 'ABSPATH' ) || exit;

final class OptionOperationLock {
	private const PREFIX = 'hm_mahex_shipment_lock_';

	public function acquire( int $order_id, int $ttl = 30 ): bool {
		$key     = self::PREFIX . $order_id;
		$expires = time() + max( 5, $ttl );
		if ( add_option( $key, $expires, '', false ) ) {
			return true;
		}

		$current = (int) get_option( $key, 0 );
		if ( $current > 0 && $current < time() ) {
			delete_option( $key );
			return add_option( $key, $expires, '', false );
		}
		return false;
	}

	public function release( int $order_id ): void {
		delete_option( self::PREFIX . $order_id );
	}
}
