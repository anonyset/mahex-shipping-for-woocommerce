<?php

namespace HoseinMomeni\MahexWoo\Locations;

interface LocationProvider {
	public function fetch(): LocationProviderResult;
}
