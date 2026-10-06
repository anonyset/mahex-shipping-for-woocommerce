<?php

namespace HoseinMomeni\MahexWoo\Barcode;

/** Small, dependency-free Code 39 renderer suitable for printed waybills. */
final class Code39BarcodeRenderer implements BarcodeRenderer {
	private const PATTERNS = array(
		'0' => 'nnnwwnwnn', '1' => 'wnnwnnnnw', '2' => 'nnwwnnnnw', '3' => 'wnwwnnnnn',
		'4' => 'nnnwwnnnw', '5' => 'wnnwwnnnn', '6' => 'nnwwwnnnn', '7' => 'nnnwnnwnw',
		'8' => 'wnnwnnwnn', '9' => 'nnwwnnwnn', 'A' => 'wnnnnwnnw', 'B' => 'nnwnnwnnw',
		'C' => 'wnwnnwnnn', 'D' => 'nnnnwwnnw', 'E' => 'wnnnwwnnn', 'F' => 'nnwnwwnnn',
		'G' => 'nnnnnwwnw', 'H' => 'wnnnnwwnn', 'I' => 'nnwnnwwnn', 'J' => 'nnnnwwwnn',
		'K' => 'wnnnnnnww', 'L' => 'nnwnnnnww', 'M' => 'wnwnnnnwn', 'N' => 'nnnnwnnww',
		'O' => 'wnnnwnnwn', 'P' => 'nnwnwnnwn', 'Q' => 'nnnnnnwww', 'R' => 'wnnnnnwwn',
		'S' => 'nnwnnnwwn', 'T' => 'nnnnwnwwn', 'U' => 'wwnnnnnnw', 'V' => 'nwwnnnnnw',
		'W' => 'wwwnnnnnn', 'X' => 'nwnnwnnnw', 'Y' => 'wwnnwnnnn', 'Z' => 'nwwnwnnnn',
		'-' => 'nwnnnnwnw', '.' => 'wwnnnnwnn', ' ' => 'nwwnnnwnn', '*' => 'nwnnwnwnn',
		'$' => 'nwnwnwnnn', '/' => 'nwnwnnnwn', '+' => 'nwnnnwnwn', '%' => 'nnnwnwnwn',
	);

	public function render_svg( string $value, int $height = 54 ): string {
		$value = strtoupper( trim( $value ) );
		if ( '' === $value || ! preg_match( '/^[0-9A-Z.\- $\/+%]+$/', $value ) ) {
			throw new \InvalidArgumentException( 'Barcode contains unsupported Code 39 characters.' );
		}

		$height = min( 160, max( 24, $height ) );
		$encoded = '*' . $value . '*';
		$x = 10;
		$bars = '';
		foreach ( str_split( $encoded ) as $character ) {
			$pattern = self::PATTERNS[ $character ];
			foreach ( str_split( $pattern ) as $index => $width ) {
				$unit = 'w' === $width ? 3 : 1;
				if ( 0 === $index % 2 ) {
					$bars .= sprintf( '<rect x="%d" y="4" width="%d" height="%d"/>', $x, $unit, $height );
				}
				$x += $unit;
			}
			$x += 1;
		}

		$label = htmlspecialchars( $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
		return sprintf(
			'<svg class="mhx-barcode" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %1$d %2$d" role="img" aria-label="Barcode %3$s"><g fill="#050505">%4$s</g><text x="%5$.1f" y="%6$d" text-anchor="middle" font-family="monospace" font-size="11">%3$s</text></svg>',
			$x + 10,
			$height + 24,
			$label,
			$bars,
			( $x + 10 ) / 2,
			$height + 19
		);
	}
}
