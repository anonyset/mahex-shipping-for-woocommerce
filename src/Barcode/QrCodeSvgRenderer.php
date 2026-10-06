<?php

namespace HoseinMomeni\MahexWoo\Barcode;

/** Dependency-free QR Code version 10-L byte-mode encoder (ISO/IEC 18004). */
final class QrCodeSvgRenderer {
	private const VERSION = 10;
	private const SIZE = 57;

	public function render( string $payload, int $scale = 4 ): string {
		$bytes = array_values( unpack( 'C*', $payload ) ?: array() );
		if ( count( $bytes ) > 271 ) {
			throw new \LengthException( 'QR payload exceeds version 10-L capacity.' );
		}
		$codewords = $this->codewords( $bytes );
		$modules = array_fill( 0, self::SIZE, array_fill( 0, self::SIZE, null ) );
		$this->function_patterns( $modules );
		$this->place_data( $modules, $codewords );
		$path = '';
		for ( $y = 0; $y < self::SIZE; $y++ ) {
			for ( $x = 0; $x < self::SIZE; $x++ ) {
				if ( true === $modules[ $y ][ $x ] ) {
					$path .= 'M' . ( $x + 4 ) . ',' . ( $y + 4 ) . 'h1v1h-1z';
				}
			}
		}
		$view = self::SIZE + 8;
		return sprintf( '<svg class="mhx-qr-code" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %1$d %1$d" width="%2$d" height="%2$d" role="img" aria-label="QR Code"><rect width="100%%" height="100%%" fill="#fff"/><path d="%3$s" fill="#050505" shape-rendering="crispEdges"/></svg>', $view, $view * max( 1, $scale ), $path );
	}

	/** @param int[] $bytes @return int[] */
	private function codewords( array $bytes ): array {
		$bits = array( 0, 1, 0, 0 );
		$this->append_bits( $bits, count( $bytes ), 16 );
		foreach ( $bytes as $byte ) {
			$this->append_bits( $bits, $byte, 8 );
		}
		$capacity = 274 * 8;
		for ( $i = 0; $i < 4 && count( $bits ) < $capacity; $i++ ) { $bits[] = 0; }
		while ( count( $bits ) % 8 ) { $bits[] = 0; }
		$data = array();
		for ( $i = 0; $i < count( $bits ); $i += 8 ) {
			$value = 0;
			for ( $j = 0; $j < 8; $j++ ) { $value = ( $value << 1 ) | $bits[ $i + $j ]; }
			$data[] = $value;
		}
		$pad = 0;
		while ( count( $data ) < 274 ) { $data[] = 0 === $pad++ % 2 ? 0xec : 0x11; }
		$blocks = array();
		$offset = 0;
		foreach ( array( 68, 68, 69, 69 ) as $length ) {
			$chunk = array_slice( $data, $offset, $length );
			$blocks[] = array( $chunk, $this->reed_solomon( $chunk, 18 ) );
			$offset += $length;
		}
		$result = array();
		for ( $i = 0; $i < 69; $i++ ) foreach ( $blocks as $block ) if ( isset( $block[0][ $i ] ) ) $result[] = $block[0][ $i ];
		for ( $i = 0; $i < 18; $i++ ) foreach ( $blocks as $block ) $result[] = $block[1][ $i ];
		return $result;
	}

	private function reed_solomon( array $data, int $degree ): array {
		$generator = array_fill( 0, $degree, 0 );
		$generator[ $degree - 1 ] = 1;
		$root = 1;
		for ( $i = 0; $i < $degree; $i++ ) {
			for ( $j = 0; $j < $degree; $j++ ) {
				$generator[ $j ] = $this->gf_multiply( $generator[ $j ], $root );
				if ( $j + 1 < $degree ) $generator[ $j ] ^= $generator[ $j + 1 ];
			}
			$root = $this->gf_multiply( $root, 2 );
		}
		$result = array_fill( 0, $degree, 0 );
		foreach ( $data as $byte ) {
			$factor = $byte ^ $result[0];
			array_shift( $result ); $result[] = 0;
			foreach ( $result as $i => $unused ) { $result[ $i ] ^= $this->gf_multiply( $generator[ $i ], $factor ); }
		}
		return $result;
	}

	private function gf_multiply( int $x, int $y ): int {
		$result = 0;
		for ( $i = 0; $i < 8; $i++ ) { if ( $y & 1 ) $result ^= $x; $y >>= 1; $x = ( $x << 1 ) ^ ( ( $x >> 7 ) * 0x11d ); }
		return $result;
	}

	private function function_patterns( array &$m ): void {
		foreach ( array( array( 3, 3 ), array( self::SIZE - 4, 3 ), array( 3, self::SIZE - 4 ) ) as $p ) $this->finder( $m, $p[0], $p[1] );
		foreach ( array( 6, 28, 50 ) as $cy ) foreach ( array( 6, 28, 50 ) as $cx ) {
			if ( null !== $m[ $cy ][ $cx ] ) continue;
			for ( $dy = -2; $dy <= 2; $dy++ ) for ( $dx = -2; $dx <= 2; $dx++ ) $m[ $cy + $dy ][ $cx + $dx ] = max( abs( $dx ), abs( $dy ) ) !== 1;
		}
		for ( $i = 8; $i < self::SIZE - 8; $i++ ) { if ( null === $m[6][$i] ) $m[6][$i] = 0 === $i % 2; if ( null === $m[$i][6] ) $m[$i][6] = 0 === $i % 2; }
		$this->version_bits( $m );
		$this->format_bits( $m );
		$m[ self::SIZE - 8 ][8] = true;
	}

	private function finder( array &$m, int $cx, int $cy ): void {
		for ( $dy = -4; $dy <= 4; $dy++ ) for ( $dx = -4; $dx <= 4; $dx++ ) {
			$x=$cx+$dx; $y=$cy+$dy; if($x<0||$y<0||$x>=self::SIZE||$y>=self::SIZE) continue;
			$d=max(abs($dx),abs($dy)); $m[$y][$x]=$d!==2&&$d!==4;
		}
	}

	private function version_bits( array &$m ): void {
		$bits = self::VERSION << 12;
		for ( $i = 17; $i >= 12; $i-- ) if ( ( $bits >> $i ) & 1 ) $bits ^= 0x1f25 << ( $i - 12 );
		$bits |= self::VERSION << 12;
		for ( $i=0;$i<18;$i++ ) { $bit=(bool)(($bits>>$i)&1); $a=self::SIZE-11+($i%3); $b=intdiv($i,3); $m[$b][$a]=$bit; $m[$a][$b]=$bit; }
	}

	private function format_bits( array &$m ): void {
		// Error correction L (01), mask 0.
		$data = 8; $rem = $data << 10;
		for($i=14;$i>=10;$i--) if(($rem>>$i)&1) $rem ^= 0x537 << ($i-10);
		$bits=(($data<<10)|$rem)^0x5412;
		for($i=0;$i<=5;$i++)$m[$i][8]=(bool)(($bits>>$i)&1);
		$m[7][8]=(bool)(($bits>>6)&1); $m[8][8]=(bool)(($bits>>7)&1); $m[8][7]=(bool)(($bits>>8)&1);
		for($i=9;$i<15;$i++)$m[8][14-$i]=(bool)(($bits>>$i)&1);
		for($i=0;$i<8;$i++)$m[8][self::SIZE-1-$i]=(bool)(($bits>>$i)&1);
		for($i=8;$i<15;$i++)$m[self::SIZE-15+$i][8]=(bool)(($bits>>$i)&1);
	}

	private function place_data( array &$m, array $bytes ): void {
		$bits=array(); foreach($bytes as $byte)$this->append_bits($bits,$byte,8); $index=0; $up=true;
		for($right=self::SIZE-1;$right>=1;$right-=2){ if($right===6)$right--; for($v=0;$v<self::SIZE;$v++){ $y=$up?self::SIZE-1-$v:$v; for($j=0;$j<2;$j++){ $x=$right-$j; if(null!==$m[$y][$x])continue; $bit=$index<count($bits)?(bool)$bits[$index++]:false; if(0===(($x+$y)%2))$bit=!$bit; $m[$y][$x]=$bit; }} $up=!$up; }
	}

	private function append_bits( array &$bits, int $value, int $length ): void { for($i=$length-1;$i>=0;$i--)$bits[]=($value>>$i)&1; }
}
