<?php

namespace HoseinMomeni\MahexWoo\Locations;

final class LocationSearchService {
	/** @param list<Location> $locations */
	public function __construct(
		private readonly array $locations,
		private readonly PersianTextNormalizer $normalizer = new PersianTextNormalizer()
	) {}

	/** @return list<Location> */
	public function search( string $query = '', ?string $province = null, ?string $city = null, ?string $type = null, int $limit = 25 ): array {
		$query    = $this->normalizer->normalize( $query );
		$province = null === $province ? null : $this->normalizer->normalize( $province );
		$city     = null === $city ? null : $this->normalizer->normalize( $city );
		$type     = null === $type ? null : strtolower( trim( $type ) );
		$limit    = max( 1, min( 100, $limit ) );
		$matches  = array();

		foreach ( $this->locations as $location ) {
			if ( null !== $province && $province !== $this->normalizer->normalize( $location->province ) ) {
				continue;
			}
			if ( null !== $city && $city !== $this->normalizer->normalize( $location->city ) ) {
				continue;
			}
			if ( null !== $type && in_array( $type, array( 'province', 'city', 'village' ), true ) && $type !== $location->type() ) {
				continue;
			}
			$haystack = $this->normalizer->normalize( $location->label() );
			if ( '' !== $query && false === strpos( $haystack, $query ) ) {
				continue;
			}
			$matches[] = $location;
			if ( count( $matches ) >= $limit ) {
				break;
			}
		}

		return $matches;
	}

	/** @return list<string> */
	public function provinces(): array {
		$provinces = array_values( array_unique( array_map( static fn ( Location $location ): string => $location->province, $this->locations ) ) );
		sort( $provinces, SORT_STRING );
		return $provinces;
	}

	/** @return list<string> */
	public function cities( string $province ): array {
		$cities = array_map(
			static fn ( Location $location ): string => $location->city,
			$this->search( '', $province, null, null, 100 )
		);
		$cities = array_values( array_unique( $cities ) );
		$cities = array_values( array_filter( $cities, static fn ( string $city ): bool => '' !== $city ) );
		sort( $cities, SORT_STRING );
		return $cities;
	}
}
