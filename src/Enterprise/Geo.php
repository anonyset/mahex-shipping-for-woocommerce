<?php

namespace HoseinMomeni\MahexWoo\Enterprise;

final class Geo {
	/** Approximate provincial centers; used only for warehouse routing, never billing/geocoding. */
	private const PROVINCES = array(
		'تهران'=>array(35.6892,51.3890),'البرز'=>array(35.8400,50.9391),'قم'=>array(34.6416,50.8746),'قزوین'=>array(36.2688,50.0041),'مرکزی'=>array(34.0954,49.7013),
		'اصفهان'=>array(32.6546,51.6680),'فارس'=>array(29.5918,52.5837),'خوزستان'=>array(31.3183,48.6706),'خراسان رضوی'=>array(36.2605,59.6168),'آذربایجان شرقی'=>array(38.0800,46.2919),
		'آذربایجان غربی'=>array(37.5527,45.0761),'اردبیل'=>array(38.2498,48.2933),'ایلام'=>array(33.6374,46.4227),'بوشهر'=>array(28.9234,50.8203),'چهارمحال و بختیاری'=>array(32.3256,50.8644),
		'خراسان جنوبی'=>array(32.8649,59.2262),'خراسان شمالی'=>array(37.4751,57.3333),'زنجان'=>array(36.6736,48.4787),'سمنان'=>array(35.5769,53.3921),'سیستان و بلوچستان'=>array(29.4963,60.8629),
		'کردستان'=>array(35.3219,46.9862),'کرمان'=>array(30.2839,57.0834),'کرمانشاه'=>array(34.3142,47.0650),'کهگیلویه و بویراحمد'=>array(30.6682,51.5879),'گلستان'=>array(36.8427,54.4439),
		'گیلان'=>array(37.2808,49.5832),'لرستان'=>array(33.4878,48.3558),'مازندران'=>array(36.5659,53.0586),'هرمزگان'=>array(27.1832,56.2666),'همدان'=>array(34.7989,48.5150),'یزد'=>array(31.8974,54.3569),
	);

	public static function provincePoint( string $province ): ?array { return self::PROVINCES[ trim( $province ) ] ?? null; }
	public static function distanceKm( float $lat1, float $lon1, float $lat2, float $lon2 ): float {
		$earth = 6371.0; $dLat = deg2rad( $lat2 - $lat1 ); $dLon = deg2rad( $lon2 - $lon1 );
		$a = sin($dLat/2)**2 + cos(deg2rad($lat1))*cos(deg2rad($lat2))*sin($dLon/2)**2;
		return $earth * 2 * atan2( sqrt($a), sqrt(max(0.0,1-$a)) );
	}
}
