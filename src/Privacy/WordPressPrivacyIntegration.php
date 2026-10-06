<?php

namespace HoseinMomeni\MahexWoo\Privacy;

/** Registers with WordPress' authenticated privacy-request workflow. */
final class WordPressPrivacyIntegration {
	private const PAGE_SIZE = 50;

	public function __construct( private readonly PersonalDataRepository $repository ) {}

	public function register(): void {
		if ( ! function_exists( 'add_filter' ) ) {
			return;
		}
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_eraser' ) );
		add_action( 'admin_init', array( $this, 'privacy_policy_content' ) );
	}

	/** @param array<string, mixed> $exporters @return array<string, mixed> */
	public function register_exporter( array $exporters ): array {
		$exporters['hm-mahex'] = array( 'exporter_friendly_name' => 'Mahex Shipping', 'callback' => array( $this, 'export' ) );
		return $exporters;
	}

	/** @param array<string, mixed> $erasers @return array<string, mixed> */
	public function register_eraser( array $erasers ): array {
		$erasers['hm-mahex'] = array( 'eraser_friendly_name' => 'Mahex Shipping', 'callback' => array( $this, 'erase' ) );
		return $erasers;
	}

	/** @return array{data:list<array<string, mixed>>, done:bool} */
	public function export( string $email, int $page = 1 ): array {
		$email = self::validated_email( $email );
		if ( '' === $email ) {
			return array( 'data' => array(), 'done' => true );
		}
		$items = $this->repository->export_for_email( $email, max( 1, $page ), self::PAGE_SIZE );
		$data  = array();
		if ( array() !== $items ) {
			$data[] = array( 'group_id' => 'hm-mahex', 'group_label' => 'Mahex Shipping', 'item_id' => 'hm-mahex-' . hash( 'sha256', strtolower( $email ) ) . '-' . max( 1, $page ), 'data' => $items );
		}
		return array( 'data' => $data, 'done' => count( $items ) < self::PAGE_SIZE );
	}

	/** @return array{items_removed:int, items_retained:int, messages:list<string>, done:bool} */
	public function erase( string $email, int $page = 1 ): array {
		$email = self::validated_email( $email );
		if ( '' === $email ) {
			return array( 'items_removed' => 0, 'items_retained' => 0, 'messages' => array(), 'done' => true );
		}
		return $this->repository->erase_for_email( $email, max( 1, $page ), self::PAGE_SIZE );
	}

	public function privacy_policy_content(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) return;
		$content = '<p>این افزونه برای مدیریت ارسال، اطلاعات لازم سفارش مانند مشخصات گیرنده، نشانی، تلفن، کدپستی، اطلاعات بسته، بارنامه و تاریخچه عملیاتی را داخل وردپرس/ووکامرس ذخیره می‌کند. دفترچه آدرس اختیاری مشتری نیز در پروفایل همان کاربر نگهداری می‌شود.</p><p>نسخه 1.0.0 برای عملیات حمل خود هیچ درخواست شبکه‌ای به سرویس حمل خارجی ارسال نمی‌کند. مدیر فروشگاه باید مدت نگهداری اطلاعات و دسترسی اپراتورها را متناسب با سیاست حریم خصوصی فروشگاه تنظیم کند.</p>';
		wp_add_privacy_policy_content( 'Mahex Shipping for WooCommerce', wp_kses_post( $content ) );
	}

	private static function validated_email( string $email ): string {
		$email = trim( strtolower( $email ) );
		return false === filter_var( $email, FILTER_VALIDATE_EMAIL ) ? '' : $email;
	}
}
