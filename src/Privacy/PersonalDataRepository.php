<?php

namespace HoseinMomeni\MahexWoo\Privacy;

interface PersonalDataRepository {
	/** @return list<array{name:string, value:string}> */
	public function export_for_email( string $email, int $page, int $page_size ): array;

	/** @return array{items_removed:int, items_retained:int, messages:list<string>, done:bool} */
	public function erase_for_email( string $email, int $page, int $page_size ): array;
}
