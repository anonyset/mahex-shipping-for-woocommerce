<?php

namespace HoseinMomeni\MahexWoo\V1;

final class MarginEngine {
	/** @return array{freight:float,subsidy:float,breakdown:list<string>} */
	public static function apply( float $freight ): array {
		$base = max( 0, (int) round( $freight ) );
		$amount = $base; $breakdown = array();
		$percent = Config::float( 'margin_percent', 0, -100, 1000 );
		$fixed = Config::int( 'margin_fixed_irr', 0, -1000000000000, 1000000000000 );
		if ( 0.0 !== $percent ) { $delta = (int) round( $amount * $percent / 100, 0, PHP_ROUND_HALF_UP ); $amount += $delta; $breakdown[] = 'margin-percent:' . $percent . '%'; }
		if ( 0 !== $fixed ) { $amount += $fixed; $breakdown[] = 'margin-fixed:' . $fixed; }
		foreach ( self::tiers() as $tier ) {
			if ( $base < $tier['min'] || ( $tier['max'] > 0 && $base > $tier['max'] ) ) continue;
			$amount += (int) round( $amount * $tier['percent'] / 100, 0, PHP_ROUND_HALF_UP ) + $tier['fixed'];
			$breakdown[] = 'margin-tier:' . $tier['percent'] . '%+' . $tier['fixed'];
			break;
		}
		$minimum = Config::int( 'min_shipping_margin_irr', 0 );
		if ( $minimum > 0 && $amount < $base + $minimum ) { $amount = $base + $minimum; $breakdown[] = 'min-margin:' . $minimum; }
		$share = Config::float( 'customer_share_percent', 100, 0, 100 );
		$subsidyPercent = Config::float( 'subsidy_percent', 0, 0, 100 );
		$preSubsidy = max( 0, $amount );
		$amount = (int) round( $amount * $share / 100, 0, PHP_ROUND_HALF_UP );
		if ( $share < 100 ) $breakdown[] = 'customer-share:' . $share . '%';
		if ( $subsidyPercent > 0 ) { $amount = (int) round( $amount * ( 100 - $subsidyPercent ) / 100, 0, PHP_ROUND_HALF_UP ); $breakdown[] = 'subsidy:' . $subsidyPercent . '%'; }
		$cap = Config::int( 'max_customer_shipping_irr', 0 );
		if ( $cap > 0 && $amount > $cap ) { $amount = $cap; $breakdown[] = 'cap:' . $cap; }
		$step = Config::int( 'rounding_step_irr', 1000, 1, 100000000 );
		if ( $amount > 0 && $step > 1 ) { $amount = intdiv( $amount + $step - 1, $step ) * $step; $breakdown[] = 'round:' . $step; }
		$ending = Config::int( 'psychological_ending_irr', 0, 0, 99999999 );
		if ( $ending > 0 && $amount > $ending ) {
			$digits = max( 1, strlen( (string) $ending ) ); $base10 = 10 ** $digits;
			$candidate = intdiv( $amount, $base10 ) * $base10 + $ending;
			if ( $candidate < $amount ) $candidate += $base10;
			$amount = $candidate; $breakdown[] = 'psychological:' . $ending;
		}
		return array( 'freight' => (float) max( 0, $amount ), 'subsidy' => (float) max( 0, $preSubsidy - $amount ), 'breakdown' => $breakdown );
	}

	/** @return list<array{min:int,max:int,percent:float,fixed:int}> */
	private static function tiers(): array {
		$lines = preg_split( '/\r\n|\r|\n/', (string) Config::get( 'margin_tiers', '' ) ) ?: array(); $out = array();
		foreach ( $lines as $line ) {
			$p = array_map( 'trim', explode( '|', $line ) ); if ( count( $p ) < 4 || ! is_numeric( $p[0] ) || ! is_numeric( $p[1] ) || ! is_numeric( $p[2] ) || ! is_numeric( $p[3] ) ) continue;
			$out[] = array( 'min' => max( 0, (int) $p[0] ), 'max' => max( 0, (int) $p[1] ), 'percent' => max( -100, min( 1000, (float) $p[2] ) ), 'fixed' => (int) $p[3] );
		}
		return $out;
	}
}
