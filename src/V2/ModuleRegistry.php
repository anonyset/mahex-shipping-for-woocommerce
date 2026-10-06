<?php

namespace HoseinMomeni\MahexWoo\V2;

final class ModuleRegistry {
	public static function modules(): array {
		return array(
			'performance'=>array('label'=>'Performance','status'=>'active','description'=>'کش نرخ، متریک‌های روزانه و بهینه‌سازی فروشگاه بزرگ'),
			'operations'=>array('label'=>'Operations','status'=>'active','description'=>'شیفت اپراتور، بسته‌بندی، Problem Center و برگشتی‌ها'),
			'analytics'=>array('label'=>'Analytics','status'=>'active','description'=>'تحلیل سود، روند و Insightهای عملیاتی'),
			'event_store'=>array('label'=>'Event Store','status'=>'active','description'=>'رویدادهای ساختاریافته و Correlation ID برای سفارش‌ها'),
			'local_shipping'=>array('label'=>'Local Shipping','status'=>'active','description'=>'نرخ‌گذاری و عملیات کاملاً محلی بدون API خارجی'),
		);
	}
}
