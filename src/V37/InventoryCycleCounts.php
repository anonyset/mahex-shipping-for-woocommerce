<?php
namespace HoseinMomeni\MahexWoo\V37;

use HoseinMomeni\MahexWoo\Shipments\OptionOperationLock;
use HoseinMomeni\MahexWoo\V33\Access;

/**
 * Manual, local packaging-supply cycle counts.
 * This records human observations only; it does not claim device or external verification.
 */
final class InventoryCycleCounts {
	public const OPTION = 'hm_mahex_v37_cycle_counts';
	public const STOCK = 'hm_mahex_v33_materials';
	public const LEDGER = 'hm_mahex_v33_material_ledger';
	public const NONCE = 'hm_mahex_v37_cycle_count';
	public const SLUG = 'hm-mahex-v37-cycle-count';
	public const LANES = array( 'assigned', 'counted', 'recount', 'reconciliation', 'approval', 'posted' );
	public const REASONS = array( 'count_error' => 'خطای شمارش', 'damaged' => 'آسیب/ضایعات', 'verified' => 'تأیید بدون اختلاف', 'unrecorded_use' => 'مصرف ثبت‌نشده', 'unrecorded_receipt' => 'ورودی ثبت‌نشده', 'location_move' => 'جابجایی محل', 'other' => 'سایر' );
	private const MAX_SESSIONS = 100;
	private const MAX_MEMBERS = 200;
	private const MAX_EVENTS = 500;
	private const MAX_COUNT = 1000000;

	public static function register(): void {
		add_action( 'admin_menu', array( self::class, 'menu' ), 47 );
		add_action( 'admin_enqueue_scripts', array( self::class, 'assets' ) );
		add_action( 'wp_ajax_hm_mahex_v37_cycle_count', array( self::class, 'ajax' ) );
		add_action( 'admin_post_hm_mahex_v37_cycle_count_csv', array( self::class, 'download' ) );
	}
	public static function menu(): void {
		add_submenu_page( 'hm-mahex', 'شمارش دوره‌ای ملزومات', 'شمارش دوره‌ای', Access::cap( 'settings' ), self::SLUG, array( self::class, 'page' ) );
	}
	public static function assets( string $hook ): void {
		if ( strpos( $hook, self::SLUG ) === false ) return;
		wp_enqueue_style( 'hm-mahex-v37-cycle-count', plugins_url( 'assets/admin/v37-cycle-count.css', HM_MAHEX_FILE ), array(), HM_MAHEX_VERSION );
		wp_enqueue_script( 'hm-mahex-v37-cycle-count', plugins_url( 'assets/admin/v37-cycle-count.js', HM_MAHEX_FILE ), array(), HM_MAHEX_VERSION, true );
		wp_localize_script( 'hm-mahex-v37-cycle-count', 'hmCycleCount', array( 'url' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( self::NONCE ) ) );
	}
	private static function canView(): bool { return Access::can( 'settings' ) || Access::can( 'packing' ); }
	private static function canManage(): bool { return Access::can( 'settings' ); }
	private static function id( string $id ): string {
		$id = strtolower( trim( $id ) );
		if ( ! preg_match( '/^[a-f0-9]{24}$/', $id ) ) throw new \InvalidArgumentException( 'شناسه نشست معتبر نیست.' );
		return $id;
	}
	private static function text( $value, int $min = 0, int $max = 500 ): string {
		$value = sanitize_text_field( (string) $value );
		$length = function_exists( 'mb_strlen' ) ? mb_strlen( $value ) : strlen( $value );
		if ( $length < $min || $length > $max ) throw new \InvalidArgumentException( 'طول متن معتبر نیست.' );
		return $value;
	}
	private static function qty( $value ): int {
		if ( is_string( $value ) && ! preg_match( '/^\d+$/', $value ) ) throw new \InvalidArgumentException( 'شمارش باید عدد صحیح نامنفی باشد.' );
		if ( ! is_int( $value ) && ! is_string( $value ) && ! is_float( $value ) ) throw new \InvalidArgumentException( 'شمارش معتبر نیست.' );
		if ( (float) $value !== (float) (int) $value || (int) $value < 0 || (int) $value > self::MAX_COUNT ) throw new \InvalidArgumentException( 'شمارش خارج از بازه مجاز است.' );
		return (int) $value;
	}
	private static function catalog(): array {
		$value = get_option( self::OPTION, array() );
		return is_array( $value ) ? $value : array();
	}
	private static function stock(): array {
		$value = get_option( self::STOCK, array() );
		return is_array( $value ) ? $value : array();
	}
	private static function materialRows( array $stock ): array {
		$out = array();
		foreach ( $stock as $id => $row ) {
			if ( strpos( (string) $id, '__' ) === 0 || ! is_array( $row ) ) continue;
			$out[ (string) $id ] = $row;
		}
		return $out;
	}
	private static function materialHash( string $id, array $row ): string {
		return hash( 'sha256', wp_json_encode( array( $id, (string) ( $row['name'] ?? $id ), (float) ( $row['stock'] ?? 0 ), (bool) ( $row['active'] ?? true ) ) ) );
	}
	private static function now(): string { return gmdate( 'c' ); }
	private static function lock( callable $callback ) {
		$lock = new OptionOperationLock();
		$held = array();
		try {
			foreach ( array( -3701, -3301 ) as $key ) {
				if ( ! $lock->acquire( $key, 120 ) ) throw new \RuntimeException( 'موجودی یا شمارش در حال ویرایش است.' );
				$held[] = $key;
			}
			return $callback();
		} finally {
			foreach ( array_reverse( $held ) as $key ) $lock->release( $key );
		}
	}
	private static function save( array $all, array $session ): void {
		$all[ $session['id'] ] = $session;
		if ( count( $all ) > self::MAX_SESSIONS ) {
			uasort( $all, static fn( $a, $b ) => strcmp( $a['updated_at'] ?? '', $b['updated_at'] ?? '' ) );
			foreach ( array_keys( $all ) as $id ) {
				if ( count( $all ) <= self::MAX_SESSIONS ) break;
				if ( in_array( $all[ $id ]['state'] ?? '', array( 'posted', 'cancelled' ), true ) ) unset( $all[ $id ] );
			}
		}
		if ( ! update_option( self::OPTION, $all, false ) ) throw new \RuntimeException( 'ذخیره نشست انجام نشد.' );
	}
	private static function event( array &$session, string $type, array $detail = array() ): void {
		$session['history'][] = array( 'id' => bin2hex( random_bytes( 6 ) ), 'type' => $type, 'actor' => get_current_user_id(), 'at' => self::now(), 'detail' => $detail );
		$session['history'] = array_slice( $session['history'], -self::MAX_EVENTS );
	}
	public static function create( array $input ): array {
		if ( ! self::canManage() ) throw new \RuntimeException( 'دسترسی ایجاد نشست مجاز نیست.' );
		return self::lock( function () use ( $input ) {
			$all = self::catalog();
			$stock = self::stock();
			$rows = self::materialRows( $stock );
			$ids = array_values( array_unique( array_map( 'sanitize_key', (array) ( $input['materials'] ?? array() ) ) ) );
			if ( ! $ids || count( $ids ) > self::MAX_MEMBERS ) throw new \InvalidArgumentException( 'بین ۱ تا ۲۰۰ ماده انتخاب کنید.' );
			foreach ( $all as $existing ) if ( in_array( $existing['state'] ?? '', array( 'counting', 'reconciliation', 'approval' ), true ) && array_intersect( $ids, array_keys( $existing['members'] ?? array() ) ) ) throw new \RuntimeException( 'یکی از مواد در نشست فعال دیگری است.' );
			$members = array();
			foreach ( $ids as $material ) {
				if ( ! isset( $rows[ $material ] ) || isset( $rows[ $material ]['active'] ) && ! $rows[ $material ]['active'] ) throw new \InvalidArgumentException( 'ماده فعال پیدا نشد: ' . $material );
				$location = self::text( $input['locations'][ $material ] ?? '', 0, 80 );
				$members[ $material ] = array( 'id' => $material, 'name' => self::text( $rows[ $material ]['name'] ?? $material, 1, 120 ), 'location' => $location, 'book' => self::qty( $rows[ $material ]['stock'] ?? 0 ), 'snapshot_hash' => self::materialHash( $material, $rows[ $material ] ), 'assigned_first' => 0, 'assigned_second' => 0, 'first' => null, 'second' => null, 'recount_required' => false, 'reconcile' => null, 'approvals' => array(), 'lane' => 'assigned', 'posted' => null, 'revision' => 1 );
			}
			do { $id = bin2hex( random_bytes( 12 ) ); } while ( isset( $all[ $id ] ) );
			$session = array(
				'schema' => 1, 'id' => $id, 'code' => 'CC37-' . strtoupper( substr( $id, 0, 8 ) ),
				'title' => self::text( $input['title'] ?? '', 3, 120 ), 'description' => self::text( $input['description'] ?? '', 0, 500 ),
				'state' => 'draft', 'revision' => 1, 'threshold_abs' => self::qty( $input['threshold_abs'] ?? 1 ),
				'threshold_pct' => max( 0, min( 100, (float) ( $input['threshold_pct'] ?? 5 ) ) ),
				'critical_abs' => max( 1, self::qty( $input['critical_abs'] ?? 20 ) ),
				'reason_taxonomy' => array( 'version' => 1, 'items' => self::REASONS ), 'members' => $members, 'history' => array(), 'created_at' => self::now(), 'updated_at' => self::now(),
				'manual_human_count' => true, 'device_verified' => false, 'external_verified' => false,
			);
			self::event( $session, 'create', array( 'members' => count( $members ) ) );
			self::save( $all, $session );
			return self::present( $session );
		} );
	}
	public static function get( string $id, bool $blind = false ): array {
		$session = self::catalog()[ self::id( $id ) ] ?? null;
		if ( ! is_array( $session ) ) throw new \RuntimeException( 'نشست پیدا نشد.' );
		return $blind ? self::blind( $session, get_current_user_id() ) : self::present( $session );
	}
	public static function listing(): array {
		$out = array();
		foreach ( self::catalog() as $session ) $out[] = array( 'id' => $session['id'], 'code' => $session['code'], 'title' => $session['title'], 'state' => $session['state'], 'revision' => $session['revision'], 'members' => count( $session['members'] ), 'updated_at' => $session['updated_at'] );
		usort( $out, static fn( $a, $b ) => strcmp( $b['updated_at'], $a['updated_at'] ) );
		return $out;
	}
	public static function present( array $session ): array {
		$session['summary'] = self::summary( $session );
		return $session;
	}
	public static function blind( array $session, int $actor ): array {
		$session['summary'] = self::summary( $session );
		foreach ( $session['members'] as &$member ) {
			$firstBlind = $session['state'] === 'counting' && ! $member['first'] && (int) $member['assigned_first'] === $actor;
			$secondBlind = ! $member['second'] && (int) $member['assigned_second'] === $actor;
			if ( $firstBlind || $secondBlind ) {
				unset( $member['book'], $member['snapshot_hash'], $member['reconcile'], $member['approvals'], $member['posted'] );
				if ( $firstBlind ) unset( $member['second'] );
				if ( $secondBlind ) unset( $member['first'] );
			}
		}
		unset( $member );
		return $session;
	}
	public static function mutate( string $id, int $revision, string $intent, array $data = array() ): array {
		if ( ! self::canView() ) throw new \RuntimeException( 'دسترسی مجاز نیست.' );
		return self::lock( function () use ( $id, $revision, $intent, $data ) {
			$all = self::catalog();
			$id = self::id( $id );
			$session = $all[ $id ] ?? null;
			if ( ! $session || (int) $session['revision'] !== $revision ) throw new \RuntimeException( 'نسخه نشست تغییر کرده است.' );
			$actor = get_current_user_id();
			$detail = array();
			if ( $intent === 'start' ) {
				if ( ! self::canManage() || $session['state'] !== 'draft' ) throw new \RuntimeException( 'شروع نشست مجاز نیست.' );
				foreach ( $session['members'] as &$member ) {
					$assignee = (int) ( $data['assignees'][ $member['id'] ] ?? $data['assignee'] ?? 0 );
					if ( $assignee < 1 ) throw new \InvalidArgumentException( 'شمارشگر اول همه مواد لازم است.' );
					$member['assigned_first'] = $assignee;
				}
				unset( $member );
				$session['state'] = 'counting';
			} elseif ( $intent === 'assign_second' ) {
				$member =& self::memberRef( $session, $data, true );
				if ( ! $member['recount_required'] || $member['second'] ) throw new \RuntimeException( 'شمارش دوم لازم یا قابل تخصیص نیست.' );
				$user = (int) ( $data['user'] ?? 0 );
				if ( $user < 1 || $user === (int) $member['first']['actor'] ) throw new \InvalidArgumentException( 'شمارشگر دوم باید کاربر متفاوت باشد.' );
				$member['assigned_second'] = $user; $member['lane'] = 'recount'; $member['revision']++;
			} elseif ( $intent === 'request_recount' ) {
				$member =& self::memberRef( $session, $data, true );
				$reason = self::text( $data['reason'] ?? '', 3, 200 );
				$member['recount_required'] = true; $member['lane'] = 'recount'; $member['revision']++; $detail['reason'] = $reason;
			} elseif ( $intent === 'reconcile' ) {
				$member =& self::memberRef( $session, $data, true );
				if ( ! $member['first'] || $member['recount_required'] && ! $member['second'] ) throw new \RuntimeException( 'شمارش‌های لازم کامل نیست.' );
				$counters = array_filter( array( (int) $member['first']['actor'], (int) ( $member['second']['actor'] ?? 0 ) ) );
				if ( in_array( $actor, $counters, true ) && $member['second'] && (int) $member['first']['value'] !== (int) $member['second']['value'] ) throw new \RuntimeException( 'مغایرت‌گیر باید از شمارشگران متفاوت باشد.' );
				$choice = sanitize_key( (string) ( $data['choice'] ?? '' ) );
				$reason = sanitize_key( (string) ( $data['reason'] ?? '' ) ); if ( ! isset( $session['reason_taxonomy']['items'][ $reason ] ) ) throw new \InvalidArgumentException( 'علت مغایرت فعال و معتبر نیست.' );
				$note = self::text( $data['note'] ?? '', 3, 500 );
				if ( $choice === 'first' ) $value = (int) $member['first']['value'];
				elseif ( $choice === 'second' && $member['second'] ) $value = (int) $member['second']['value'];
				elseif ( $choice === 'manual' && self::canManage() ) $value = self::qty( $data['value'] ?? null );
				elseif ( $choice === 'recount' ) { $member['recount_required'] = true; $member['assigned_second'] = 0; $member['second'] = null; $member['lane'] = 'recount'; $member['revision']++; $value = null; }
				else throw new \InvalidArgumentException( 'تصمیم مغایرت‌گیری معتبر نیست.' );
				if ( $value !== null ) { $member['reconcile'] = array( 'choice' => $choice, 'value' => $value, 'reason' => $reason, 'note' => $note, 'actor' => $actor, 'at' => self::now() ); $member['approvals'] = array(); $member['lane'] = 'approval'; $member['revision']++; $session['state'] = 'approval'; }
			} elseif ( $intent === 'correct_count' ) {
				$member =& self::memberRef( $session, $data, true ); if ( ! self::canManage() || $member['posted'] ) throw new \RuntimeException( 'اصلاح شمارش مجاز نیست.' ); $round = (int) ( $data['round'] ?? 0 ); $key = $round === 1 ? 'first' : ( $round === 2 ? 'second' : '' ); if ( ! $key || ! $member[ $key ] ) throw new \InvalidArgumentException( 'شمارش قابل اصلاح پیدا نشد.' ); $reason = self::text( $data['reason'] ?? '', 3, 300 ); $member['corrections'][] = array( 'round' => $round, 'previous' => $member[ $key ], 'reason' => $reason, 'actor' => $actor, 'at' => self::now() ); $member[ $key ] = null; if ( $round === 1 ) { $member['second'] = null; $member['assigned_second'] = 0; $member['lane'] = 'assigned'; } else $member['lane'] = 'recount'; $member['reconcile'] = null; $member['approvals'] = array(); $member['revision']++;
			} elseif ( $intent === 'approve' ) {
				$member =& self::memberRef( $session, $data, true );
				if ( ! self::canManage() || ! $member['reconcile'] ) throw new \RuntimeException( 'تصویب مجاز نیست.' );
				$actors = array( (int) $member['first']['actor'], (int) ( $member['second']['actor'] ?? 0 ), (int) $member['reconcile']['actor'] );
				foreach ( $member['approvals'] as $approval ) $actors[] = (int) $approval['actor'];
				if ( in_array( $actor, array_filter( $actors ), true ) ) throw new \RuntimeException( 'تأییدکننده باید مستقل باشد.' );
				$member['approvals'][] = array( 'actor' => $actor, 'at' => self::now(), 'note' => self::text( $data['note'] ?? '', 3, 300 ) );
				$member['revision']++;
			} elseif ( $intent === 'reject' ) {
				$member =& self::memberRef( $session, $data, true );
				if ( ! self::canManage() || ! $member['reconcile'] ) throw new \RuntimeException( 'رد مجاز نیست.' );
				$detail['reason'] = self::text( $data['reason'] ?? '', 3, 300 );
				$member['reconcile'] = null; $member['approvals'] = array(); $member['lane'] = 'reconciliation'; $member['revision']++; $session['state'] = 'reconciliation';
			} elseif ( $intent === 'move' ) {
				$member =& self::memberRef( $session, $data, true );
				$to = sanitize_key( (string) ( $data['lane'] ?? '' ) );
				$allowed = array( 'assigned' => array( 'counted' ), 'counted' => array( 'recount', 'reconciliation' ), 'recount' => array( 'reconciliation' ), 'reconciliation' => array( 'recount', 'approval' ), 'approval' => array( 'reconciliation' ), 'posted' => array() );
				if ( ! in_array( $to, $allowed[ $member['lane'] ] ?? array(), true ) ) throw new \RuntimeException( 'گذار برد مجاز نیست.' );
				if ( $to === 'counted' && ! $member['first'] || $to === 'reconciliation' && $member['recount_required'] && ! $member['second'] || $to === 'approval' && ! $member['reconcile'] ) throw new \RuntimeException( 'پیش‌نیاز مرحله کامل نیست.' );
				$member['lane'] = $to; $member['revision']++;
			} elseif ( $intent === 'cancel' ) {
				if ( ! self::canManage() || ! in_array( $session['state'], array( 'draft', 'counting', 'reconciliation', 'approval' ), true ) ) throw new \RuntimeException( 'لغو مجاز نیست.' );
				foreach ( $session['members'] as $member ) if ( $member['posted'] ) throw new \RuntimeException( 'نشست دارای ثبت موجودی قابل لغو نیست.' );
				$detail['reason'] = self::text( $data['reason'] ?? '', 3, 300 ); $session['state'] = 'cancelled';
			} else throw new \InvalidArgumentException( 'عملیات نشست معتبر نیست.' );
			$session['revision']++; $session['updated_at'] = self::now(); self::event( $session, $intent, $detail ); self::save( $all, $session );
			return self::present( $session );
		} );
	}
	private static function &memberRef( array &$session, array $data, bool $revision ): array {
		$id = sanitize_key( (string) ( $data['material'] ?? '' ) );
		if ( ! isset( $session['members'][ $id ] ) ) throw new \InvalidArgumentException( 'عضو نشست پیدا نشد.' );
		if ( $revision && (int) ( $data['member_revision'] ?? 0 ) !== (int) $session['members'][ $id ]['revision'] ) throw new \RuntimeException( 'نسخه کارت تغییر کرده است.' );
		return $session['members'][ $id ];
	}
	public static function recordCount( string $id, int $revision, string $material, int $memberRevision, int $round, $value ): array {
		return self::lock( function () use ( $id, $revision, $material, $memberRevision, $round, $value ) {
			$all = self::catalog(); $session = $all[ self::id( $id ) ] ?? null;
			if ( ! $session || $session['state'] !== 'counting' && $session['state'] !== 'reconciliation' || (int) $session['revision'] !== $revision ) throw new \RuntimeException( 'نشست شمارش فعال یا هم‌نسخه نیست.' );
			$member =& self::memberRef( $session, array( 'material' => $material, 'member_revision' => $memberRevision ), true );
			$actor = get_current_user_id(); $value = self::qty( $value ); $record = array( 'value' => $value, 'actor' => $actor, 'at' => self::now(), 'source' => 'manual-human', 'revision' => $member['revision'] );
			if ( $round === 1 ) {
				if ( $member['first'] || (int) $member['assigned_first'] !== $actor ) throw new \RuntimeException( 'شمارش اول تخصیص‌یافته یا قابل ثبت نیست.' );
				$member['first'] = $record;
				$absolute = abs( $value - (int) $member['book'] ); $percent = $member['book'] > 0 ? 100 * $absolute / $member['book'] : ( $absolute ? 100 : 0 );
				$member['recount_required'] = $absolute >= $session['threshold_abs'] || $percent >= $session['threshold_pct'];
				$member['lane'] = $member['recount_required'] ? 'recount' : 'reconciliation';
			} elseif ( $round === 2 ) {
				if ( ! $member['first'] || ! $member['recount_required'] || $member['second'] || (int) $member['assigned_second'] !== $actor || $actor === (int) $member['first']['actor'] ) throw new \RuntimeException( 'شمارش دوم مستقل و تخصیص‌یافته نیست.' );
				$member['second'] = $record; $member['agreement'] = (int) $member['first']['value'] === $value; $member['lane'] = 'reconciliation'; $session['state'] = 'reconciliation';
			} else throw new \InvalidArgumentException( 'مرحله شمارش معتبر نیست.' );
			$member['revision']++; $session['revision']++; $session['updated_at'] = self::now(); self::event( $session, 'count_' . $round, array( 'material' => $material, 'manual' => true ) ); self::save( $all, $session );
			return self::blind( $session, $actor );
		} );
	}
	/** Each row commits under its own lock/revision; failures never roll back successful sibling rows. */
	public static function recordBatch( string $id, int $round, array $rows ): array {
		if ( count( $rows ) > self::MAX_MEMBERS ) throw new \InvalidArgumentException( 'تعداد ردیف‌های گروهی بیش از حد است.' ); $results = array();
		foreach ( $rows as $row ) { try { $current = self::get( $id ); $material = sanitize_key( (string) ( $row['material'] ?? '' ) ); $results[] = array( 'material' => $material, 'ok' => true, 'session' => self::recordCount( $id, $current['revision'], $material, (int) ( $current['members'][ $material ]['revision'] ?? 0 ), $round, $row['value'] ?? null ) ); } catch ( \Throwable $e ) { $results[] = array( 'material' => sanitize_key( (string) ( $row['material'] ?? '' ) ), 'ok' => false, 'error' => $e->getMessage() ); } }
		return $results;
	}
	public static function drift( array $session, ?array $stock = null ): array {
		$stock = $stock ?? self::stock(); $out = array();
		foreach ( $session['members'] as $id => $member ) {
			if ( $member['posted'] ) continue;
			if ( ! isset( $stock[ $id ] ) ) $out[ $id ] = 'missing';
			elseif ( ! hash_equals( (string) $member['snapshot_hash'], self::materialHash( $id, $stock[ $id ] ) ) ) $out[ $id ] = 'changed';
		}
		return $out;
	}
	public static function rebase( string $id, int $revision, string $material, int $memberRevision, string $reason ): array {
		if ( ! self::canManage() ) throw new \RuntimeException( 'بازمبناگذاری مجاز نیست.' );
		return self::lock( function () use ( $id, $revision, $material, $memberRevision, $reason ) {
			$all = self::catalog(); $session = $all[ self::id( $id ) ] ?? null;
			if ( ! $session || (int) $session['revision'] !== $revision ) throw new \RuntimeException( 'نسخه نشست تغییر کرده است.' );
			$member =& self::memberRef( $session, array( 'material' => $material, 'member_revision' => $memberRevision ), true ); $stock = self::stock();
			if ( ! isset( $stock[ $material ] ) || ! isset( self::drift( $session, $stock )[ $material ] ) ) throw new \RuntimeException( 'عضو رانش‌دار نیست.' );
			$previous = array( 'book' => $member['book'], 'snapshot_hash' => $member['snapshot_hash'], 'first' => $member['first'], 'second' => $member['second'] );
			$member['book'] = self::qty( $stock[ $material ]['stock'] ?? 0 ); $member['snapshot_hash'] = self::materialHash( $material, $stock[ $material ] ); $member['first'] = null; $member['second'] = null; $member['reconcile'] = null; $member['approvals'] = array(); $member['assigned_second'] = 0; $member['lane'] = 'assigned'; $member['revision']++;
			$session['revision']++; $session['state'] = 'counting'; self::event( $session, 'rebase', array( 'material' => $material, 'reason' => self::text( $reason, 3, 300 ), 'previous' => $previous ) ); self::save( $all, $session ); return self::present( $session );
		} );
	}
	private static function approvalsNeeded( array $session, array $member ): int {
		return abs( (int) $member['reconcile']['value'] - (int) $member['book'] ) >= (int) $session['critical_abs'] ? 2 : 1;
	}
	public static function postingPreview( array $session, ?array $stock = null ): array {
		$stock = $stock ?? self::stock(); $rows = array(); foreach ( $session['members'] as $id => $member ) { $current = isset( $stock[ $id ] ) ? self::qty( $stock[ $id ]['stock'] ?? 0 ) : null; $approved = $member['reconcile']['value'] ?? null; $rows[] = array( 'material' => $id, 'current' => $current, 'approved' => $approved, 'adjustment' => $current === null || $approved === null ? null : (int) $approved - $current, 'after' => $approved, 'stale' => $current === null || ! hash_equals( (string) $member['snapshot_hash'], self::materialHash( $id, $stock[ $id ] ?? array() ) ) ); } return $rows;
	}
	public static function cleanup( int $days = 365 ): int {
		$days = max( 30, min( 3650, $days ) ); $cutoff = time() - $days * DAY_IN_SECONDS; $all = self::catalog(); $removed = 0; foreach ( $all as $id => $session ) if ( in_array( $session['state'] ?? '', array( 'posted', 'cancelled' ), true ) && strtotime( $session['updated_at'] ?? 'now' ) < $cutoff ) { unset( $all[ $id ] ); $removed++; } if ( $removed && ! update_option( self::OPTION, $all, false ) ) throw new \RuntimeException( 'پاک‌سازی نشست‌های قدیمی انجام نشد.' ); return $removed;
	}
	public static function post( string $id, int $revision, array $materials ): array {
		if ( ! self::canManage() ) throw new \RuntimeException( 'ثبت موجودی مجاز نیست.' );
		return self::lock( function () use ( $id, $revision, $materials ) {
			$all = self::catalog(); $id = self::id( $id ); $session = $all[ $id ] ?? null;
			if ( ! $session || (int) $session['revision'] !== $revision ) throw new \RuntimeException( 'نسخه نشست تغییر کرده است.' );
			$stock = self::stock(); $markers = is_array( $stock['__cycle_counts'] ?? null ) ? $stock['__cycle_counts'] : array(); $journal = is_array( $stock['__cycle_count_ledger'] ?? null ) ? $stock['__cycle_count_ledger'] : array();
			foreach ( array_values( array_unique( array_map( 'sanitize_key', $materials ) ) ) as $material ) {
				if ( ! isset( $session['members'][ $material ] ) ) throw new \InvalidArgumentException( 'عضو ثبت پیدا نشد.' );
				$member =& $session['members'][ $material ]; $key = $id . ':' . $material . ':' . $member['revision'];
				if ( isset( $markers[ $key ] ) ) { $member['posted'] = $markers[ $key ]; $member['lane'] = 'posted'; continue; }
				if ( ! $member['reconcile'] || count( $member['approvals'] ) < self::approvalsNeeded( $session, $member ) ) throw new \RuntimeException( 'تصمیم و تصویب کافی نیست.' );
				if ( ! isset( $stock[ $material ] ) || ! hash_equals( $member['snapshot_hash'], self::materialHash( $material, $stock[ $material ] ) ) ) throw new \RuntimeException( 'دفتر موجودی پس از snapshot تغییر کرده است؛ بازمبناگذاری لازم است.' );
				$old = self::qty( $stock[ $material ]['stock'] ?? 0 ); $new = (int) $member['reconcile']['value']; $adjustment = $new - $old;
				$entry = array( 'key' => $key, 'session' => $id, 'material' => $material, 'old' => $old, 'new' => $new, 'adjustment' => $adjustment, 'reason' => $member['reconcile']['reason'], 'decision_actor' => $member['reconcile']['actor'], 'approvers' => array_column( $member['approvals'], 'actor' ), 'actor' => get_current_user_id(), 'at' => self::now(), 'snapshot_hash' => $member['snapshot_hash'] );
				$stock[ $material ]['stock'] = $new; $stock[ $material ]['cycle_counted_at'] = $entry['at']; $markers[ $key ] = $entry; $journal[] = $entry; $journal = array_slice( $journal, -1000 ); $member['posted'] = $entry; $member['lane'] = 'posted'; $member['revision']++;
			}
			$stock['__cycle_counts'] = $markers; $stock['__cycle_count_ledger'] = $journal;
			if ( ! update_option( self::STOCK, $stock, false ) ) throw new \RuntimeException( 'ثبت اتمیک موجودی و journal انجام نشد.' );
			$allPosted = true; foreach ( $session['members'] as $member ) if ( ! $member['posted'] ) $allPosted = false;
			$session['state'] = $allPosted ? 'posted' : 'approval'; $session['revision']++; $session['updated_at'] = self::now(); self::event( $session, 'post', array( 'materials' => $materials, 'all_posted' => $allPosted ) );
			self::save( $all, $session ); return self::present( $session );
		} );
	}
	public static function recover( string $id ): array {
		return self::lock( function () use ( $id ) {
			$all = self::catalog(); $id = self::id( $id ); $session = $all[ $id ] ?? null; if ( ! $session ) throw new \RuntimeException( 'نشست پیدا نشد.' );
			$markers = self::stock()['__cycle_counts'] ?? array(); $changed = false;
			foreach ( $session['members'] as &$member ) if ( ! $member['posted'] ) foreach ( $markers as $entry ) if ( ( $entry['session'] ?? '' ) === $id && ( $entry['material'] ?? '' ) === $member['id'] ) { $member['posted'] = $entry; $member['lane'] = 'posted'; $changed = true; break; }
			unset( $member ); if ( $changed ) { $session['revision']++; self::event( $session, 'recover_post_journal' ); self::save( $all, $session ); } return self::present( $session );
		} );
	}
	public static function summary( array $session ): array {
		$out = array( 'members' => count( $session['members'] ), 'counted' => 0, 'recounts' => 0, 'approved' => 0, 'posted' => 0, 'book_total' => 0, 'final_total' => 0, 'variance' => 0, 'lanes' => array_fill_keys( self::LANES, 0 ) );
		foreach ( $session['members'] as $member ) { $out['lanes'][ $member['lane'] ]++; $out['book_total'] += (int) $member['book']; if ( $member['first'] ) $out['counted']++; if ( $member['second'] ) $out['recounts']++; if ( $member['approvals'] ) $out['approved']++; if ( $member['posted'] ) $out['posted']++; $final = (int) ( $member['reconcile']['value'] ?? $member['book'] ); $out['final_total'] += $final; $out['variance'] += $final - (int) $member['book']; }
		return $out;
	}
	public static function csv( array $session, bool $blindTemplate = false ): string {
		$stream = fopen( 'php://temp', 'r+' );
		$headers = $blindTemplate ? array( 'schema', 'session', 'member_id', 'material_id', 'manual_count' ) : array( 'schema', 'session', 'material_id', 'location', 'book', 'first', 'second', 'approved', 'variance', 'state' );
		fputcsv( $stream, $headers );
		foreach ( $session['members'] as $member ) {
			if ( $blindTemplate ) fputcsv( $stream, array( 'mahex-cycle-count/1', $session['code'], $member['id'], $member['id'], '' ) );
			else { $final = $member['reconcile']['value'] ?? ''; fputcsv( $stream, array( 'mahex-cycle-count/1', $session['code'], $member['id'], $member['location'], $member['book'], $member['first']['value'] ?? '', $member['second']['value'] ?? '', $final, $final === '' ? '' : $final - $member['book'], $member['lane'] ) ); }
		}
		rewind( $stream ); $csv = stream_get_contents( $stream ); fclose( $stream ); return $csv;
	}
	public static function previewCsv( string $csv, array $session ): array {
		if ( strlen( $csv ) > 200000 ) throw new \InvalidArgumentException( 'CSV بیش از حد بزرگ است.' );
		$stream = fopen( 'php://temp', 'r+' ); fwrite( $stream, $csv ); rewind( $stream ); $header = fgetcsv( $stream );
		if ( $header !== array( 'schema', 'session', 'member_id', 'material_id', 'manual_count' ) ) throw new \InvalidArgumentException( 'قالب CSV شمارش کور معتبر نیست.' );
		$rows = array(); $line = 1;
		while ( ( $row = fgetcsv( $stream ) ) !== false ) { $line++; $error = ''; if ( count( $row ) !== 5 || $row[0] !== 'mahex-cycle-count/1' || $row[1] !== $session['code'] || ! isset( $session['members'][ sanitize_key( $row[3] ?? '' ) ] ) ) $error = 'ردیف یا عضو معتبر نیست'; else try { self::qty( $row[4] ); } catch ( \Throwable $e ) { $error = $e->getMessage(); } $rows[] = array( 'line' => $line, 'material' => sanitize_key( $row[3] ?? '' ), 'value' => $row[4] ?? '', 'error' => $error ); if ( count( $rows ) > self::MAX_MEMBERS ) throw new \InvalidArgumentException( 'تعداد ردیف‌ها بیش از حد است.' ); }
		fclose( $stream ); return array( 'rows' => $rows, 'valid' => count( array_filter( $rows, static fn( $r ) => $r['error'] === '' ) ), 'confirmed' => false );
	}
	public static function privacyForUser( int $userId ): array {
		$out = array(); foreach ( self::catalog() as $session ) { $events = array_values( array_filter( $session['history'], static fn( $e ) => (int) $e['actor'] === $userId ) ); $roles = array(); foreach ( $session['members'] as $member ) { foreach ( array( 'first', 'second', 'reconcile', 'posted' ) as $key ) if ( (int) ( $member[ $key ]['actor'] ?? 0 ) === $userId || (int) ( $member[ $key ]['decision_actor'] ?? 0 ) === $userId ) $roles[] = array( 'material' => $member['id'], 'role' => $key ); foreach ( $member['approvals'] as $approval ) if ( (int) $approval['actor'] === $userId ) $roles[] = array( 'material' => $member['id'], 'role' => 'approval' ); } if ( $events || $roles ) $out[] = array( 'session' => $session['id'], 'events' => $events, 'roles' => $roles ); } return $out;
	}
	public static function eraseUser( int $userId ): int {
		$all = self::catalog(); $changed = 0; foreach ( $all as &$session ) self::anonymizeActors( $session, $userId, $changed ); unset( $session ); if ( $changed && ! update_option( self::OPTION, $all, false ) ) throw new \RuntimeException( 'ناشناس‌سازی بازیگر انجام نشد.' ); return $changed;
	}
	private static function anonymizeActors( &$value, int $userId, int &$changed, string $parent = '' ): void {
		if ( ! is_array( $value ) ) return;
		$identityKeys = array( 'actor', 'decision_actor', 'assigned_first', 'assigned_second', 'released_by' );
		foreach ( $value as $key => &$child ) {
			if ( in_array( (string) $key, $identityKeys, true ) && (int) $child === $userId ) { $child = 0; $changed++; continue; }
			if ( $parent === 'approvers' && is_numeric( $key ) && (int) $child === $userId ) { $child = 0; $changed++; continue; }
			self::anonymizeActors( $child, $userId, $changed, (string) $key );
		}
		unset( $child );
	}
	public static function ajax(): void {
		if ( ! check_ajax_referer( self::NONCE, 'nonce', false ) || ! self::canView() ) wp_send_json_error( array( 'message' => 'دسترسی مجاز نیست.' ), 403 );
		try { $raw = $_POST['payload'] ?? ''; if ( ! is_string( $raw ) || strlen( $raw ) > 100000 ) throw new \InvalidArgumentException( 'درخواست نامعتبر است.' ); $p = json_decode( wp_unslash( $raw ), true ); if ( ! is_array( $p ) ) throw new \InvalidArgumentException( 'JSON نامعتبر است.' ); $intent = sanitize_key( (string) ( $p['intent'] ?? '' ) );
			if ( $intent === 'count' ) $result = self::recordCount( (string) $p['id'], (int) $p['revision'], (string) $p['material'], (int) $p['member_revision'], (int) $p['round'], $p['value'] );
			elseif ( $intent === 'post' ) $result = self::post( (string) $p['id'], (int) $p['revision'], (array) $p['materials'] );
			elseif ( $intent === 'recover' ) $result = self::recover( (string) $p['id'] );
			else $result = self::mutate( (string) $p['id'], (int) $p['revision'], $intent, (array) ( $p['data'] ?? array() ) );
			wp_send_json_success( array( 'session' => $result, 'reload' => true ) );
		} catch ( \Throwable $e ) { wp_send_json_error( array( 'message' => $e->getMessage() ), 409 ); }
	}
	public static function download(): void {
		if ( ! self::canView() ) wp_die( 'دسترسی مجاز نیست.', '', array( 'response' => 403 ) ); check_admin_referer( self::NONCE ); $session = self::get( (string) ( $_GET['session'] ?? '' ) ); $blind = ! empty( $_GET['blind'] ); nocache_headers(); header( 'Content-Type: text/csv; charset=UTF-8' ); header( 'Content-Disposition: attachment; filename="' . $session['code'] . ( $blind ? '-blind' : '-reconciliation' ) . '.csv"' ); echo "\xEF\xBB\xBF" . self::csv( $session, $blind ); exit;
	}
	public static function page(): void {
		if ( ! self::canView() ) wp_die( 'دسترسی مجاز نیست.', '', array( 'response' => 403 ) ); $id = sanitize_text_field( (string) ( $_GET['session'] ?? '' ) );
		echo '<div class="wrap hm-v37-cycle" dir="rtl"><h1>شمارش دوره‌ای ملزومات بسته‌بندی</h1><p class="notice notice-info">تمام مقادیر، مشاهده و ورود دستی کاربر مجاز هستند؛ این صفحه خوانش دستگاه، API ماهکس، webhook، صف حمل یا حقیقت بیرونی را تأیید نمی‌کند.</p><p data-cycle-live role="status" aria-live="polite"></p>';
		if ( ! $id ) { echo '<div class="hcc-list">'; foreach ( self::listing() as $row ) echo '<a href="' . esc_url( add_query_arg( array( 'page' => self::SLUG, 'session' => $row['id'] ), admin_url( 'admin.php' ) ) ) . '"><b>' . esc_html( $row['title'] ) . '</b><span>' . esc_html( $row['code'] . ' · ' . $row['state'] . ' · ' . $row['members'] . ' ماده' ) . '</span></a>'; echo '</div></div>'; return; }
		$session = self::get( $id, true ); $summary = $session['summary']; echo '<header class="hcc-head"><div><h2>' . esc_html( $session['title'] ) . '</h2><p>' . esc_html( $session['description'] ) . '</p></div><b>' . esc_html( $session['code'] ) . ' · ' . esc_html( $session['state'] ) . '</b></header><nav><a class="button" href="' . esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'hm_mahex_v37_cycle_count_csv', 'session' => $session['id'] ), admin_url( 'admin-post.php' ) ), self::NONCE ) ) . '">CSV مغایرت</a> <a class="button" href="' . esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'hm_mahex_v37_cycle_count_csv', 'session' => $session['id'], 'blind' => 1 ), admin_url( 'admin-post.php' ) ), self::NONCE ) ) . '">قالب CSV کور</a></nav><section class="hcc-metrics"><b>' . (int) $summary['counted'] . ' شمارش اول</b><b>' . (int) $summary['recounts'] . ' شمارش دوم</b><b>' . (int) $summary['approved'] . ' تصویب</b><b>' . (int) $summary['posted'] . ' ثبت‌شده</b></section><div class="hcc-filter"><label>جست‌وجوی ماده/محل <input type="search" data-cycle-search></label><label>مرحله <select data-cycle-filter><option value="">همه</option>'; foreach ( self::LANES as $lane ) echo '<option value="' . esc_attr( $lane ) . '">' . esc_html( $lane ) . '</option>'; echo '</select></label></div><div class="hcc-board" data-session="' . esc_attr( $session['id'] ) . '" data-revision="' . (int) $session['revision'] . '">';
		foreach ( self::LANES as $lane ) { echo '<section class="hcc-lane" data-lane="' . esc_attr( $lane ) . '"><h3>' . esc_html( $lane ) . ' <span>' . (int) $summary['lanes'][ $lane ] . '</span></h3><div class="hcc-drop" data-drop="' . esc_attr( $lane ) . '">'; foreach ( $session['members'] as $member ) if ( $member['lane'] === $lane ) { echo '<article class="hcc-card" draggable="true" tabindex="0" data-material="' . esc_attr( $member['id'] ) . '" data-member-revision="' . (int) $member['revision'] . '" data-lane="' . esc_attr( $lane ) . '"><b>' . esc_html( $member['name'] ) . '</b><small>' . esc_html( $member['id'] . ( $member['location'] ? ' · ' . $member['location'] : '' ) ) . '</small><label>انتقال با صفحه‌کلید<select data-cycle-move><option value="">انتخاب</option>'; foreach ( self::LANES as $to ) echo '<option value="' . esc_attr( $to ) . '">' . esc_html( $to ) . '</option>'; echo '</select></label></article>'; } echo '</div></section>'; }
		echo '</div></div>';
	}
}
