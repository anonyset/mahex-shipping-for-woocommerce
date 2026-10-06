<?php

namespace HoseinMomeni\MahexWoo\Barcode;

interface BarcodeRenderer {
	public function render_svg( string $value, int $height = 54 ): string;
}
