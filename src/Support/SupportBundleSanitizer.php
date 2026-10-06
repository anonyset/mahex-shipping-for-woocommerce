<?php

namespace HoseinMomeni\MahexWoo\Support;

use HoseinMomeni\MahexWoo\Security\SecretRedactor;

final class SupportBundleSanitizer {
	public function __construct( private readonly SecretRedactor $redactor = new SecretRedactor() ) {}

	/** @param array<string, mixed> $data @return array<string, mixed> */
	public function sanitize( array $data ): array {
		$clean = $this->redactor->redact( $data );
		return $this->remove_personal_data( is_array( $clean ) ? $clean : array() );
	}

	/** @param array<string, mixed> $data @return array<string, mixed> */
	private function remove_personal_data( array $data ): array {
		foreach ( $data as $key => $value ) {
			if ( preg_match( '/(?:email|phone|mobile|address|customer|recipient|sender|full[_-]?name)/i', (string) $key ) ) {
				$data[ $key ] = SecretRedactor::REDACTED;
			} elseif ( is_array( $value ) ) {
				$data[ $key ] = $this->remove_personal_data( $value );
			} elseif ( is_string( $value ) ) {
				$value        = preg_replace( '/\b[A-Z]:\\\\Users\\\\[^\\\\\s]+/i', '[USER_PATH]', $value ) ?? $value;
				$value        = preg_replace( '/\b[\w.%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}\b/i', '[EMAIL]', $value ) ?? $value;
				$data[ $key ] = $value;
			}
		}
		return $data;
	}
}
