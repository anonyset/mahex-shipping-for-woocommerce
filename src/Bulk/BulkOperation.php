<?php

namespace HoseinMomeni\MahexWoo\Bulk;

enum BulkOperation: string {
	case CREATE  = 'create';
	case REFRESH = 'refresh';
	case CANCEL  = 'cancel';
	case REISSUE = 'reissue';

	public static function from_input( string $value ): self {
		return match ( $value ) {
			'create' => self::CREATE,
			'refresh' => self::REFRESH,
			'cancel' => self::CANCEL,
			'reissue' => self::REISSUE,
			default => throw new \InvalidArgumentException( 'Unsupported bulk operation.' ),
		};
	}
}
