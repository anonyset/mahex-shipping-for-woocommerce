<?php

namespace HoseinMomeni\MahexWoo\Locations;

final class PersianTextNormalizer {
	public function normalize( string $value ): string {
		$value = strtr(
			$value,
			array(
				'ي' => 'ی',
				'ى' => 'ی',
				'ئ' => 'ی',
				'ك' => 'ک',
				'ة' => 'ه',
				'ۀ' => 'ه',
				'ؤ' => 'و',
				'‌'  => ' ',
			)
		);
		$value = preg_replace( '/[\x{064B}-\x{065F}\x{0670}]/u', '', $value ) ?? $value;
		$value = preg_replace( '/\s+/u', ' ', trim( $value ) ) ?? trim( $value );

		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $value, 'UTF-8' ) : strtolower( $value );
	}
}
