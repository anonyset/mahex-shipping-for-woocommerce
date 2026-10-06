<?php

namespace HoseinMomeni\MahexWoo\V2;

final class Insights {
	public static function generate(): array {
		if ( ! Config::bool( 'insights_enabled', true ) ) return array();
		global $wpdb;
		$idx = $wpdb->prefix . 'hm_mahex_order_index';
		$insights = array();

		$loss = $wpdb->get_row( "SELECT COUNT(*) cnt,COALESCE(SUM(profit),0) profit FROM $idx WHERE created_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 30 DAY) AND profit<0", ARRAY_A ) ?: array();
		if ( (int) ( $loss['cnt'] ?? 0 ) > 0 ) $insights[] = array( 'severity'=>'critical','title'=>'ارسال زیان‌ده','message'=>sprintf('%d سفارش در ۳۰ روز اخیر سود ارسال منفی داشته‌اند.',(int)$loss['cnt']),'action'=>'قوانین سود و نرخ را بررسی کنید.' );

		$cities = $wpdb->get_results( "SELECT province,city,COUNT(*) orders,SUM(profit) profit FROM $idx WHERE created_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 60 DAY) GROUP BY province,city HAVING COUNT(*)>=3 ORDER BY profit ASC LIMIT 5", ARRAY_A ) ?: array();
		foreach ( $cities as $c ) if ( (float) $c['profit'] < 0 ) $insights[] = array( 'severity'=>'warning','title'=>'شهر کم‌بازده','message'=>sprintf('%s / %s در %d سفارش جمعاً سود منفی داشته است.',(string)$c['province'],(string)$c['city'],(int)$c['orders']),'action'=>'برای این مقصد Rule اختصاصی یا حداقل کرایه تعیین کنید.' );

		$metrics = DailyMetrics::range( 14 );
		if ( count( $metrics ) >= 7 ) {
			$recent = array_slice( $metrics, -7 );
			$avg = array_sum( array_map( static fn($r)=>(float)$r['orders_count'], $recent ) ) / max(1,count($recent));
			$pred = (int) ceil( $avg * 7 );
			$insights[] = array( 'severity'=>'info','title'=>'پیش‌بینی حجم هفته آینده','message'=>sprintf('با میانگین ۷ روز اخیر، حدود %d سفارش برای هفته آینده قابل انتظار است.',$pred),'action'=>'موجودی بسته‌بندی را با این حجم مقایسه کنید.' );
		}

		$consumption = \HoseinMomeni\MahexWoo\V1\InventoryLedger::consumptionReport( 30 );
		$profiles = (array) get_option( 'hm_mahex_packaging_profiles', array() );
		foreach ( $consumption as $row ) {
			$id = (string) ( $row['profile_id'] ?? '' ); if ( '' === $id || ! isset( $profiles[ $id ] ) || ! is_array( $profiles[ $id ] ) ) continue;
			$used30 = max( 0, (int) ( $row['qty'] ?? 0 ) ); $weekly = (int) ceil( $used30 / 30 * 7 ); $stock = max( 0, (int) ( $profiles[ $id ]['stock'] ?? 0 ) );
			if ( $weekly > 0 && $stock < $weekly ) $insights[] = array( 'severity'=>'warning','title'=>'ریسک کمبود بسته‌بندی هفته آینده','message'=>sprintf('%s: مصرف پیش‌بینی‌شده هفتگی حدود %d عدد و موجودی فعلی %d است.',(string)($profiles[$id]['name']??$id),$weekly,$stock),'action'=>'پیش از رسیدن موجودی به صفر، تأمین مجدد انجام دهید.' );
		}

		foreach ( $profiles as $id=>$p ) {
			if ( ! is_array($p) ) continue;
			$stock=(int)($p['stock']??0);$min=(int)($p['min_stock']??0);
			if($min>0&&$stock<=$min)$insights[]=array('severity'=>$stock<=0?'critical':'warning','title'=>'موجودی بسته‌بندی کم','message'=>sprintf('%s فقط %d عدد موجودی دارد.',(string)($p['name']??$id),$stock),'action'=>'برای تأمین بسته‌بندی اقدام کنید.');
		}

		$problems = ProblemCenter::open( 200 );
		$critical = count( array_filter( $problems, static fn($r)=>in_array((string)$r['severity'],array('critical','error'),true) ) );
		if($critical>0)$insights[]=array('severity'=>'critical','title'=>'مشکلات عملیاتی باز','message'=>sprintf('%d مشکل مهم هنوز باز است.',$critical),'action'=>'داشبورد مشکلات امروز را بررسی کنید.');
		return array_slice( $insights, 0, 20 );
	}
}
