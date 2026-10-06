<?php

namespace HoseinMomeni\MahexWoo\Security;

final class SecretRedactor {
	public const REDACTED = '[REDACTED]';

	/** @var list<string> */
	private const SECRET_KEYS = array( 'authorization', 'api_key', 'apikey', 'api-key', 'token', 'access_token', 'refresh_token', 'secret', 'client_secret', 'password', 'passwd', 'cookie', 'set-cookie' );

	/** @return mixed */
	public function redact( mixed $value, ?string $key = null ): mixed {
		if ( null !== $key && $this->is_secret_key( $key ) ) {
			return self::REDACTED;
		}
		if ( is_array( $value ) ) {
			$clean = array();
			foreach ( $value as $child_key => $child ) {
				$clean[ $child_key ] = $this->redact( $child, (string) $child_key );
			}
			return $clean;
		}
		if ( ! is_string( $value ) ) {
			return $value;
		}

		$value = preg_replace( '/\b(Bearer|Basic)\s+[A-Za-z0-9._~+\/-]+=*/i', '$1 ' . self::REDACTED, $value ) ?? self::REDACTED;
		$value = preg_replace( '/([?&](?:api[_-]?key|token|access_token|secret|password)=)[^&#\s]*/i', '$1' . rawurlencode( self::REDACTED ), $value ) ?? self::REDACTED;
		return $value;
	}

	private function is_secret_key( string $key ): bool {
		$key = strtolower( trim( $key ) );
		return in_array( $key, self::SECRET_KEYS, true ) || 1 === preg_match( '/(?:^|[_-])(token|secret|password|credential|cookie)(?:$|[_-])/', $key );
	}
}
