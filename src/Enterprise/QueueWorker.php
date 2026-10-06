<?php

namespace HoseinMomeni\MahexWoo\Enterprise;

use HoseinMomeni\MahexWoo\Shipments\ShipmentService;
use HoseinMomeni\MahexWoo\Shipments\OrderStatusSync;
use HoseinMomeni\MahexWoo\Shipments\OrderShipmentStore;

final class QueueWorker {
	public const HOOK='hm_mahex_enterprise_queue';
	public static function register(): void {add_action(self::HOOK,array(self::class,'run'));add_action('init',array(self::class,'schedule'));}
	public static function schedule(): void {if(!Config::bool('queue_enabled',true)){wp_clear_scheduled_hook(self::HOOK);return;}if(!wp_next_scheduled(self::HOOK))wp_schedule_event(time()+60,'hm_mahex_five_minutes',self::HOOK);}
	public static function schedules(array $s): array {$s['hm_mahex_five_minutes']=array('interval'=>300,'display'=>'Every five minutes');return $s;}
	public static function run(): void {foreach(QueueStore::claim(10) as $job){try{self::handle($job);QueueStore::complete((int)$job['id']);}catch(\Throwable $e){QueueStore::fail($job,$e->getMessage());}}}
	private static function handle(array $job): void {$p=json_decode((string)$job['payload'],true);$p=is_array($p)?$p:array();$type=(string)$job['type'];
		if(in_array($type,array('shipment_create','shipment_refresh','shipment_cancel','shipment_reissue'),true)){$order=wc_get_order(absint($p['order_id']??0));if(!$order)throw new \RuntimeException('Order not found.');$svc=new ShipmentService();$s=match($type){'shipment_create'=>$svc->create($order,0,false),'shipment_refresh'=>$svc->refresh($order,0),'shipment_cancel'=>$svc->cancel($order,0),'shipment_reissue'=>$svc->create($order,0,true)};if('shipment_refresh'===$type){$aggregate=(new OrderShipmentStore())->aggregateShipment($order);if($aggregate)OrderStatusSync::apply($order,$aggregate);}return;}
		throw new \RuntimeException('Unknown local job type.');}
}
