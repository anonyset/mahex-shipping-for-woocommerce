<?php

namespace HoseinMomeni\MahexWoo\Packaging;

interface VolumetricWeightCalculatorInterface {
	public function calculateGrams( int $lengthMm, int $widthMm, int $heightMm ): int;
}
