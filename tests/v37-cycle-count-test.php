<?php
namespace HoseinMomeni\MahexWoo\V33 {
	final class Access {
		public static function can( string $area, int $orderId = 0 ): bool { return $GLOBALS['allowed'] ?? true; }
		public static function cap( string $area ): string { return 'manage_woocommerce'; }
	}
}
namespace {
	define( 'ABSPATH', __DIR__ );
	$options = array(); $actor = 1; $allowed = true; $failSessionSave = false;
	function get_option( $key, $default = false ) { return array_key_exists( $key, $GLOBALS['options'] ) ? $GLOBALS['options'][ $key ] : $default; }
	function update_option( $key, $value, ...$rest ) { if ( $key === 'hm_mahex_v37_cycle_counts' && $GLOBALS['failSessionSave'] ) { $GLOBALS['failSessionSave'] = false; return false; } $GLOBALS['options'][ $key ] = $value; return true; }
	function add_option( $key, $value, ...$rest ) { if ( array_key_exists( $key, $GLOBALS['options'] ) ) return false; $GLOBALS['options'][ $key ] = $value; return true; }
	function delete_option( $key ) { unset( $GLOBALS['options'][ $key ] ); return true; }
	function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
	function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ); }
	function wp_json_encode( $value, ...$rest ) { return json_encode( $value, ...$rest ); }
	function get_current_user_id() { return $GLOBALS['actor']; }
	function current_user_can() { return $GLOBALS['allowed']; }
	require __DIR__ . '/../src/Shipments/OptionOperationLock.php';
	require __DIR__ . '/../src/V37/InventoryCycleCounts.php';
	use HoseinMomeni\MahexWoo\V37\InventoryCycleCounts as C;
	function ok( $condition, string $message ): void { if ( ! $condition ) throw new \RuntimeException( 'FAIL ' . $message ); }
	function reject( callable $callback, string $message ): void { try { $callback(); } catch ( \Throwable $e ) { return; } throw new \RuntimeException( 'ACCEPTED ' . $message ); }
	function member( array $session, string $id ): array { return $session['members'][ $id ]; }

	$options[C::STOCK] = array(
		'box_small' => array( 'name' => 'کارتن کوچک', 'stock' => 10, 'active' => true ),
		'tape' => array( 'name' => 'چسب', 'stock' => 100, 'active' => true ),
		'bubble' => array( 'name' => 'نایلون حباب‌دار', 'stock' => 30, 'active' => false ),
		'__commits' => array(),
	);
	reject( fn() => C::create( array( 'title' => 'نشست تست', 'materials' => array( 'bubble' ) ) ), 'inactive material' );
	$s = C::create( array( 'title' => 'شمارش صبح انبار', 'description' => 'شمارش دستی دوره‌ای', 'materials' => array( 'box_small', 'tape' ), 'locations' => array( 'box_small' => 'قفسه A', 'tape' => 'قفسه B' ), 'threshold_abs' => 2, 'threshold_pct' => 10, 'critical_abs' => 20 ) );
	ok( strlen( $s['id'] ) === 24 && str_starts_with( $s['code'], 'CC37-' ), 'stable session identity' );
	ok( $s['state'] === 'draft' && count( $s['members'] ) === 2 && $s['members']['box_small']['book'] === 10, 'snapshot members' );
	reject( fn() => C::create( array( 'title' => 'هم‌پوشان ولی پیش‌نویس', 'materials' => array( 'missing' ) ) ), 'unknown material' );
	$s = C::mutate( $s['id'], $s['revision'], 'start', array( 'assignees' => array( 'box_small' => 2, 'tape' => 2 ) ) );
	ok( $s['state'] === 'counting', 'start and assignment' );
	reject( fn() => C::create( array( 'title' => 'نشست هم‌پوشان', 'materials' => array( 'box_small' ) ) ), 'active overlap' );
	$actor = 2;
	$blind = C::get( $s['id'], true );
	ok( ! isset( $blind['members']['box_small']['book'] ), 'first count blind book' );
	reject( fn() => C::recordCount( $s['id'], $s['revision'], 'box_small', member( $s, 'box_small' )['revision'], 1, '' ), 'blank is not zero' );
	C::recordCount( $s['id'], $s['revision'], 'box_small', member( $s, 'box_small' )['revision'], 1, 7 ); $s = C::get( $s['id'] );
	ok( member( $s, 'box_small' )['first']['value'] === 7 && member( $s, 'box_small' )['lane'] === 'recount', 'variance triggers recount' );
	reject( fn() => C::recordCount( $s['id'], $s['revision'], 'box_small', member( $s, 'box_small' )['revision'], 1, 8 ), 'immutable first count' );
	C::recordCount( $s['id'], $s['revision'], 'tape', member( $s, 'tape' )['revision'], 1, 0 ); $s = C::get( $s['id'] );
	ok( member( $s, 'tape' )['first']['value'] === 0, 'explicit zero accepted' );
	$actor = 1;
	reject( fn() => C::mutate( $s['id'], $s['revision'], 'assign_second', array( 'material' => 'box_small', 'member_revision' => member( $s, 'box_small' )['revision'], 'user' => 2 ) ), 'second counter separation' );
	$s = C::mutate( $s['id'], $s['revision'], 'assign_second', array( 'material' => 'box_small', 'member_revision' => member( $s, 'box_small' )['revision'], 'user' => 3 ) );
	$actor = 3; $blind = C::get( $s['id'], true );
	ok( ! isset( $blind['members']['box_small']['book'] ) && ! isset( $blind['members']['box_small']['first'] ), 'second count independent blind' );
	C::recordCount( $s['id'], $s['revision'], 'box_small', member( $s, 'box_small' )['revision'], 2, 8 ); $s = C::get( $s['id'] );
	ok( member( $s, 'box_small' )['second']['value'] === 8 && member( $s, 'box_small' )['lane'] === 'reconciliation', 'second count recorded' );
	$actor = 1; $s = C::mutate( $s['id'], $s['revision'], 'assign_second', array( 'material' => 'tape', 'member_revision' => member( $s, 'tape' )['revision'], 'user' => 3 ) );
	$actor = 3; C::recordCount( $s['id'], $s['revision'], 'tape', member( $s, 'tape' )['revision'], 2, 0 ); $s = C::get( $s['id'] );
	$actor = 2;
	reject( fn() => C::mutate( $s['id'], $s['revision'], 'reconcile', array( 'material' => 'box_small', 'member_revision' => member( $s, 'box_small' )['revision'], 'choice' => 'second', 'reason' => 'count_error', 'note' => 'اختلاف شمارش بررسی شد' ) ), 'counter cannot reconcile disagreement' );
	$actor = 4;
	$s = C::mutate( $s['id'], $s['revision'], 'reconcile', array( 'material' => 'box_small', 'member_revision' => member( $s, 'box_small' )['revision'], 'choice' => 'second', 'reason' => 'count_error', 'note' => 'قفسه دوباره به صورت دستی شمارش شد' ) );
	$s = C::mutate( $s['id'], $s['revision'], 'reconcile', array( 'material' => 'tape', 'member_revision' => member( $s, 'tape' )['revision'], 'choice' => 'first', 'reason' => 'damaged', 'note' => 'موجودی فیزیکی صفر مشاهده شد' ) );
	$actor = 4;
	reject( fn() => C::mutate( $s['id'], $s['revision'], 'approve', array( 'material' => 'box_small', 'member_revision' => member( $s, 'box_small' )['revision'], 'note' => 'تأیید مستقل انجام شد' ) ), 'reconciler cannot approve' );
	$actor = 5;
	$s = C::mutate( $s['id'], $s['revision'], 'approve', array( 'material' => 'box_small', 'member_revision' => member( $s, 'box_small' )['revision'], 'note' => 'شواهد شمارش بررسی و تأیید شد' ) );
	$s = C::mutate( $s['id'], $s['revision'], 'approve', array( 'material' => 'tape', 'member_revision' => member( $s, 'tape' )['revision'], 'note' => 'مغایرت بزرگ بررسی اولیه شد' ) );
	$actor = 6;
	$s = C::mutate( $s['id'], $s['revision'], 'approve', array( 'material' => 'tape', 'member_revision' => member( $s, 'tape' )['revision'], 'note' => 'تأیید دوم مستقل ثبت شد' ) );
	ok( count( member( $s, 'tape' )['approvals'] ) === 2, 'critical variance dual approval' );
	$preview = C::summary( $s ); ok( $preview['variance'] === -102 && $preview['approved'] === 2, 'reconciliation summary' );
	$options[C::STOCK]['box_small']['stock'] = 9;
	ok( C::drift( $s )['box_small'] === 'changed', 'ledger drift detected' );
	$actor = 1; reject( fn() => C::post( $s['id'], $s['revision'], array( 'box_small' ) ), 'stale posting blocked' );
	$s = C::rebase( $s['id'], $s['revision'], 'box_small', member( $s, 'box_small' )['revision'], 'مصرف همزمان ایستگاه ثبت شد' );
	ok( member( $s, 'box_small' )['first'] === null && member( $s, 'box_small' )['book'] === 9, 'controlled rebase resets counts' );
	// Finish rebased box without a second count.
	$actor = 2; C::recordCount( $s['id'], $s['revision'], 'box_small', member( $s, 'box_small' )['revision'], 1, 9 ); $s = C::get( $s['id'] );
	$actor = 4; $s = C::mutate( $s['id'], $s['revision'], 'reconcile', array( 'material' => 'box_small', 'member_revision' => member( $s, 'box_small' )['revision'], 'choice' => 'first', 'reason' => 'verified', 'note' => 'شمارش با دفتر تازه برابر است' ) );
	$actor = 5; $s = C::mutate( $s['id'], $s['revision'], 'approve', array( 'material' => 'box_small', 'member_revision' => member( $s, 'box_small' )['revision'], 'note' => 'مقدار برابر بازبینی شد' ) );
	$actor = 1; $before = count( $options[C::STOCK]['__cycle_count_ledger'] ?? array() ); $s = C::post( $s['id'], $s['revision'], array( 'box_small' ) );
	ok( $options[C::STOCK]['box_small']['stock'] === 9 && count( $options[C::STOCK]['__cycle_count_ledger'] ) === $before + 1, 'atomic zero adjustment journal' );
	// Simulate stock/journal success followed by session persistence failure.
	$oldSession = $options[C::OPTION][ $s['id'] ]; $failSessionSave = true;
	reject( fn() => C::post( $s['id'], $s['revision'], array( 'tape' ) ), 'session save failure after atomic stock marker' );
	ok( $options[C::STOCK]['tape']['stock'] === 0, 'stock write persisted before recoverable session failure' );
	$options[C::OPTION][ $s['id'] ] = $oldSession;
	$s = C::recover( $s['id'] );
	ok( member( $s, 'tape' )['lane'] === 'posted', 'posting recovered from idempotency journal' );
	$countJournal = count( $options[C::STOCK]['__cycle_count_ledger'] );
	$s = C::recover( $s['id'] );
	ok( count( $options[C::STOCK]['__cycle_count_ledger'] ) === $countJournal, 'recovery does not duplicate adjustment' );
	$csv = C::csv( $s ); ok( str_contains( $csv, 'variance' ) && ! str_contains( $csv, 'phone' ) && ! str_contains( $csv, 'address' ), 'low-data reconciliation CSV' );
	$template = C::csv( $s, true ); ok( str_contains( $template, 'manual_count' ) && ! str_contains( $template, ',9,' ), 'blind CSV template' );
	$preview = C::previewCsv( $template, $s ); ok( $preview['confirmed'] === false && $preview['valid'] === 0, 'CSV import requires values and confirmation' );
	$private = C::privacyForUser( 2 ); ok( count( $private ) === 1, 'actor privacy export' );
	ok( C::eraseUser( 2 ) > 0 && ! str_contains( json_encode( $options[C::OPTION] ), '"actor":2' ), 'privacy actor anonymization' );
	$js = file_get_contents( __DIR__ . '/../assets/admin/v37-cycle-count.js' ); $css = file_get_contents( __DIR__ . '/../assets/admin/v37-cycle-count.css' );
	ok( substr_count( $js, 'drag' ) >= 3 && str_contains( $js, 'data-cycle-move' ) && str_contains( $js, 'aria' ) === false, 'drag and keyboard alternative' );
	ok( str_contains( $css, 'direction:rtl' ) && str_contains( $css, '@media(max-width:520px)' ) && str_contains( $css, 'max-width:100%' ), 'responsive RTL interface' );
	echo "V37 cycle count: snapshots, blind counts, independent recount, reconciliation, approvals, drift, atomic idempotent posting, recovery, CSV, privacy and RTL board passed\n";
}
