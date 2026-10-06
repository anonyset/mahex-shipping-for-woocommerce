<?php

namespace HoseinMomeni\MahexWoo\Barcode;

/**
 * Cross-process sequence reservation using an exclusive OS file lock.
 * The caller chooses a private writable path; no customer data is persisted.
 */
final class FileSequenceReservation implements SequenceReservation {
	public function __construct( private readonly string $path ) {
		if ( '' === trim( $path ) ) {
			throw new \InvalidArgumentException( 'Reservation path cannot be empty.' );
		}
	}

	public function reserve_next(): int {
		$directory = dirname( $this->path );
		if ( ! is_dir( $directory ) || ! is_writable( $directory ) ) {
			throw new \RuntimeException( 'Reservation directory must exist and be writable.' );
		}

		$handle = fopen( $this->path, 'c+' );
		if ( false === $handle ) {
			throw new \RuntimeException( 'Could not open reservation storage.' );
		}

		try {
			if ( ! flock( $handle, LOCK_EX ) ) {
				throw new \RuntimeException( 'Could not lock reservation storage.' );
			}
			rewind( $handle );
			$current = trim( (string) stream_get_contents( $handle ) );
			if ( '' !== $current && ! preg_match( '/^\d+$/', $current ) ) {
				throw new \RuntimeException( 'Reservation storage is corrupt.' );
			}
			$next = (int) $current + 1;
			if ( $next < 1 ) {
				throw new \OverflowException( 'Reservation sequence overflowed.' );
			}
			ftruncate( $handle, 0 );
			rewind( $handle );
			if ( false === fwrite( $handle, (string) $next ) || ! fflush( $handle ) ) {
				throw new \RuntimeException( 'Could not persist reservation.' );
			}
			return $next;
		} finally {
			flock( $handle, LOCK_UN );
			fclose( $handle );
		}
	}
}
