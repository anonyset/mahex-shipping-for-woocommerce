<?php

namespace HoseinMomeni\MahexWoo\Locations;

final class LocationProviderResult {
	public const AVAILABLE = 'available';
	public const UNAVAILABLE = 'unavailable';
	public const AWAITING_OFFICIAL_CONTRACT = 'awaiting_official_contract';
	public const INVALID_RESPONSE = 'invalid_response';

	/** @param list<Location> $locations */
	public function __construct(
		public readonly string $status,
		public readonly array $locations,
		public readonly string $source,
		public readonly string $message = '',
		public readonly ?string $syncedAt = null
	) {}

	public function isAvailable(): bool {
		return self::AVAILABLE === $this->status;
	}

	public static function statusLabel( string $status ): string {
		return match ( $status ) {
			self::AVAILABLE                  => 'آماده و همگام‌شده',
			self::UNAVAILABLE                => 'سرویس رسمی در دسترس نیست',
			self::AWAITING_OFFICIAL_CONTRACT => 'در انتظار قرارداد رسمی',
			self::INVALID_RESPONSE           => 'پاسخ رسمی نامعتبر',
			'never_synced'                   => 'هنوز همگام‌سازی نشده',
			default                          => 'وضعیت نامشخص',
		};
	}
}
