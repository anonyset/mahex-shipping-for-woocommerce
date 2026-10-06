<?php

namespace HoseinMomeni\MahexWoo\Barcode;

interface SequenceReservation {
	/** Atomically reserves and returns the next positive sequence number. */
	public function reserve_next(): int;
}
