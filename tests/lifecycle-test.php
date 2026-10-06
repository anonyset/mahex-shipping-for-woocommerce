<?php
require_once __DIR__.'/../src/Shipments/Shipment.php';
require_once __DIR__.'/../src/Shipments/LocalShipmentLifecycle.php';
use HoseinMomeni\MahexWoo\Shipments\Shipment;
use HoseinMomeni\MahexWoo\Shipments\LocalShipmentLifecycle;
$now=new DateTimeImmutable('2026-10-06T00:00:00Z');
$service=new LocalShipmentLifecycle();
$imported=new Shipment('real-1','123456','123456','picked_up',['_manual_carrier_reference'=>true],$now->format(DATE_ATOM),$now->format(DATE_ATOM),1);
if($service->refresh($imported,$now)!==$imported)throw new RuntimeException('Imported carrier statuses must not progress through simulation');
$local=$service->create(1,[],$now);
if($service->refresh($local,$now)->status!=='picked_up')throw new RuntimeException('Existing local workflow changed unexpectedly');
echo "Imported carrier reference lifecycle tests passed\n";
