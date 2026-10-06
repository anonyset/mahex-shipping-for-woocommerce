<?php

namespace HoseinMomeni\MahexWoo\Documents;

/** Builds QR payloads that can only point to the local development site. */
final class LocalTrackingUrl {
	private readonly string $base_url;

	public function __construct( string $base_url = 'http://localhost:8088' ) {
		$parts = parse_url( $base_url );
		if ( false === $parts || isset( $parts['user'] ) || isset( $parts['pass'] ) || isset( $parts['query'] ) || isset( $parts['fragment'] ) ) {
			throw new \InvalidArgumentException( 'QR tracking base URL is malformed.' );
		}
		$host = trim( strtolower( (string) ( $parts['host'] ?? '' ) ), '[]' );
		if ( ! in_array( $host, array( 'localhost', '127.0.0.1', '::1' ), true ) ) {
			throw new \InvalidArgumentException( 'QR tracking base URL must be local.' );
		}
		if ( ! in_array( (string) ( $parts['scheme'] ?? '' ), array( 'http', 'https' ), true ) ) {
			throw new \InvalidArgumentException( 'QR tracking base URL must use HTTP or HTTPS.' );
		}
		if ( isset( $parts['port'] ) && ( $parts['port'] < 1 || $parts['port'] > 65535 ) ) {
			throw new \InvalidArgumentException( 'QR tracking base URL has an invalid port.' );
		}

		$authority = '::1' === $host ? '[' . $host . ']' : $host;
		$authority .= isset( $parts['port'] ) ? ':' . $parts['port'] : '';
		$path = '/' . trim( (string) ( $parts['path'] ?? '' ), '/' );
		$this->base_url = (string) $parts['scheme'] . '://' . $authority . ( '/' === $path ? '' : $path );
	}

	public function for_tracking_code( string $tracking_code ): string {
		if ( '' === trim( $tracking_code ) ) {
			throw new \InvalidArgumentException( 'Tracking code cannot be empty.' );
		}
		if ( strlen( $tracking_code ) > 128 ) {
			throw new \InvalidArgumentException( 'Tracking code is too long.' );
		}
		return rtrim( $this->base_url, '/' ) . '/mahex/track/' . rawurlencode( $tracking_code );
	}
}
