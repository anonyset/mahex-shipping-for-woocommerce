<?php

namespace HoseinMomeni\MahexWoo\V25;

final class MigrationManager {
	private const CURSOR='hm_mahex_v25_backfill_cursor';private const DONE='hm_mahex_v25_backfill_done';
	public static function maybeMigrate(): void {Schema::maybeInstall();if((string)get_option('hm_mahex_v25_version','')!=='2.5.0'){update_option('hm_mahex_v25_version','2.5.0',false);delete_option(self::DONE);if(false===get_option(self::CURSOR,false))update_option(self::CURSOR,0,false);}}
	public static function backfill(int $batch=100): int {if(get_option(self::DONE,0))return 0;$cursor=max(0,(int)get_option(self::CURSOR,0));$ids=wc_get_orders(array('limit'=>max(10,min(500,$batch)),'offset'=>$cursor,'orderby'=>'ID','order'=>'ASC','return'=>'ids','status'=>array_keys(wc_get_order_statuses())));if(!$ids){update_option(self::DONE,1,false);return 0;}foreach($ids as $id)Finance::syncOrder((int)$id);update_option(self::CURSOR,$cursor+count($ids),false);if(count($ids)<$batch)update_option(self::DONE,1,false);return count($ids);}
	public static function status(): array {return array('cursor'=>(int)get_option(self::CURSOR,0),'done'=>(bool)get_option(self::DONE,0),'schema'=>(string)get_option('hm_mahex_v25_schema',''));}
}
