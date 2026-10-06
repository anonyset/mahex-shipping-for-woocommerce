<?php

namespace HoseinMomeni\MahexWoo\Locations;

final class LocationValidator {
	/** @param array{province:mixed,city:mixed,village?:mixed,id?:mixed,external_id?:mixed} $row */
	public function fromRow( array $row, string $source ): ?Location {
		$province = $this->text( $row['province'] ?? null );
		$city     = $this->text( $row['city'] ?? null );
		$village  = $this->text( $row['village'] ?? null );
		$id       = $this->text( $row['id'] ?? $row['external_id'] ?? null );
		if ( null === $province || null === $city ) {
			return null;
		}
		if ( null !== $village && $village === $city ) {
			return null;
		}
		return new Location( $province, $city, $village, $source, $id );
	}

	private function text( mixed $value ): ?string {
		if ( ! is_string( $value ) && ! is_int( $value ) ) {
			return null;
		}
		$value = trim( (string) $value );
		return '' === $value || strlen( $value ) > 190 ? null : $value;
	}
}
