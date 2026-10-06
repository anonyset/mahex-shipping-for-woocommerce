<?php

namespace HoseinMomeni\MahexWoo\Locations;

final class Location {
	public function __construct(
		public readonly string $province,
		public readonly string $city,
		public readonly ?string $village = null,
		public readonly string $source = 'unknown',
		public readonly ?string $externalId = null
	) {}

	public function type(): string {
		if ( '' === $this->city ) {
			return 'province';
		}
		return null === $this->village ? 'city' : 'village';
	}

	public function typeLabel(): string {
		return match ( $this->type() ) {
			'province' => 'استان (فهرست نشانی)',
			'village'  => 'روستا (داده رسمی)',
			default    => 'شهر (داده رسمی)',
		};
	}

	public function label(): string {
		return implode( '، ', array_filter( array( $this->province, $this->city, $this->village ) ) );
	}

	/** @return array{province:string,city:string,village:?string,type:string,label:string,source:string,external_id:?string} */
	public function toArray(): array {
		return array(
			'province' => $this->province,
			'city'     => $this->city,
			'village'  => $this->village,
			'type'     => $this->type(),
			'label'    => $this->label(),
			'source'   => $this->source,
			'external_id' => $this->externalId,
		);
	}
}
