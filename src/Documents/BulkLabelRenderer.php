<?php

namespace HoseinMomeni\MahexWoo\Documents;

use HoseinMomeni\MahexWoo\Barcode\Code128CBarcodeRenderer;
use HoseinMomeni\MahexWoo\Barcode\QrCodeSvgRenderer;
use HoseinMomeni\MahexWoo\Pro\FeatureSettings;
use HoseinMomeni\MahexWoo\V1\Config as V1Config;

final class BulkLabelRenderer {
	/** @param list<WaybillData> $documents */
	public function render( array $documents, string $layout = 'a4' ): string {
		$barcode = new Code128CBarcodeRenderer();
		$qr = new QrCodeSvgRenderer();
		$e = static fn( string $v ): string => htmlspecialchars( $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
		$cards = '';
		foreach ( $documents as $data ) {
			if ( ! $data instanceof WaybillData ) continue;
			$number = '' !== $data->barcode_number ? $data->barcode_number : $data->waybill_number;
			$cards .= '<article class="label"><header><strong>MAHEX</strong><span>' . $e( $data->destination ) . '</span></header>'
				. '<h2>' . $e( $data->recipient_name ) . '</h2><p class="address">' . $e( $data->recipient_address ) . '</p>'
				. '<div class="two"><span><b>تلفن:</b> <i dir="ltr">' . $e( $data->recipient_phone_masked ) . '</i></span><span><b>اقلام:</b> ' . $e( (string) $data->item_count ) . '</span></div>'
				. '<div class="barcode">' . $barcode->render_svg( $number ) . '<small>' . $e( $number ) . '</small></div>'
				. '<div class="qr">' . $qr->render( $data->qr_payload(), 2 ) . '</div><footer>' . $e( $data->waybill_number ) . '</footer></article>';
		}
		$thermal = 'thermal' === $layout;
		$size = (string) FeatureSettings::get( 'thermal_size', '100x150' );
		$page = $thermal ? ( '80x100' === $size ? '80mm 100mm' : '100mm 150mm' ) : 'A4';
		$cols = V1Config::int( 'a4_columns', 2, 1, 4 );
		$grid = $thermal ? '1fr' : 'repeat(' . $cols . ',1fr)';
		$font = preg_replace( '/[^A-Za-z0-9 _-]/', '', (string) V1Config::get( 'label_font', 'Tahoma' ) ) ?: 'Tahoma';
		return '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><title>چاپ گروهی لیبل ماهکس</title><style>@page{size:' . $page . ';margin:' . ( $thermal ? '4mm' : '8mm' ) . '}*{box-sizing:border-box}body{font-family:' . $font . ',Tahoma,Arial,sans-serif;margin:0;color:#0f172a}.sheet{display:grid;grid-template-columns:' . $grid . ';gap:7mm}.label{position:relative;border:2px solid #082e63;border-radius:10px;padding:10px;break-inside:avoid;min-height:' . ( $thermal ? '88mm' : '125mm' ) . '}.label header{display:flex;justify-content:space-between;align-items:center;border-bottom:2px solid #ef233c;padding-bottom:7px}.label header strong{font-size:22px;color:#082e63}.label h2{font-size:17px;margin:10px 0 6px}.address{min-height:40px;line-height:1.7}.two{display:flex;justify-content:space-between;gap:8px;font-size:12px}.barcode{direction:ltr;text-align:center;margin-top:8px}.mhx-barcode{width:100%;height:62px}.barcode small{display:block;letter-spacing:2px}.qr{position:absolute;left:8px;bottom:8px}.mhx-qr-code{width:56px;height:56px}.label footer{position:absolute;right:10px;bottom:10px;font-size:10px}.label i{font-style:normal}@media print{body{print-color-adjust:exact;-webkit-print-color-adjust:exact}.label{page-break-inside:avoid}' . ( $thermal ? '.label{page-break-after:always}.label:last-child{page-break-after:auto}' : '' ) . '}</style></head><body><main class="sheet">' . $cards . '</main><script>window.addEventListener("load",function(){setTimeout(function(){window.print()},250)});</script></body></html>';
	}
}
