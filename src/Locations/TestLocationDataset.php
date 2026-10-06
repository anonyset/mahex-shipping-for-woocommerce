<?php

namespace HoseinMomeni\MahexWoo\Locations;

/**
 * Small, representative dataset for local development and automated tests only.
 *
 * It is neither an official Mahex coverage list nor suitable for production
 * address validation. Production data must come from a documented source.
 */
final class TestLocationDataset {
	public const DISCLAIMER = 'TEST-ONLY mock data; not an official Mahex coverage dataset.';

	public function isTestOnly(): bool {
		return true;
	}

	/** @return list<Location> */
	public function all(): array {
		$rows = array(
			array( 'آذربایجان شرقی', 'تبریز', null ),
			array( 'آذربایجان غربی', 'ارومیه', null ),
			array( 'اردبیل', 'اردبیل', null ),
			array( 'اصفهان', 'اصفهان', null ),
			array( 'اصفهان', 'کاشان', null ),
			array( 'اصفهان', 'کاشان', 'ابیانه' ),
			array( 'البرز', 'کرج', null ),
			array( 'ایلام', 'ایلام', null ),
			array( 'بوشهر', 'بوشهر', null ),
			array( 'تهران', 'تهران', null ),
			array( 'تهران', 'ری', null ),
			array( 'تهران', 'شمیرانات', 'فشم' ),
			array( 'چهارمحال و بختیاری', 'شهرکرد', null ),
			array( 'خراسان جنوبی', 'بیرجند', null ),
			array( 'خراسان رضوی', 'مشهد', null ),
			array( 'خراسان شمالی', 'بجنورد', null ),
			array( 'خوزستان', 'اهواز', null ),
			array( 'زنجان', 'زنجان', null ),
			array( 'سمنان', 'سمنان', null ),
			array( 'سیستان و بلوچستان', 'زاهدان', null ),
			array( 'فارس', 'شیراز', null ),
			array( 'قزوین', 'قزوین', null ),
			array( 'قم', 'قم', null ),
			array( 'کردستان', 'سنندج', null ),
			array( 'کرمان', 'کرمان', null ),
			array( 'کرمانشاه', 'کرمانشاه', null ),
			array( 'کهگیلویه و بویراحمد', 'یاسوج', null ),
			array( 'گلستان', 'گرگان', null ),
			array( 'گیلان', 'رشت', null ),
			array( 'گیلان', 'رودسر', 'جواهردشت' ),
			array( 'لرستان', 'خرم‌آباد', null ),
			array( 'مازندران', 'ساری', null ),
			array( 'مازندران', 'آمل', 'فیلبند' ),
			array( 'مرکزی', 'اراک', null ),
			array( 'هرمزگان', 'بندرعباس', null ),
			array( 'همدان', 'همدان', null ),
			array( 'یزد', 'یزد', null ),
		);

		return array_map(
			static fn ( array $row ): Location => new Location( $row[0], $row[1], $row[2] ),
			$rows
		);
	}
}
