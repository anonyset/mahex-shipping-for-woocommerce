<?php

namespace HoseinMomeni\MahexWoo\Orders;

use HoseinMomeni\MahexWoo\Shipments\ShipmentActions;

defined( 'ABSPATH' ) || exit;

final class Bootstrap {
	public static function register(): void {
		OrderPanel::register();
		ShipmentActions::register();
	}
}
