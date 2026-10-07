<?php
if ( ! defined( 'ABSPATH' ) ) require getenv( 'MAHEX_WP_ROOT' ) . '/wp-load.php';
wp_set_current_user( 1 );
use HoseinMomeni\MahexWoo\V37\InventoryCycleCounts as C;
use HoseinMomeni\MahexWoo\V37\SupplyReceiving as R;
function checkV37( $ok, $message ) { if ( ! $ok ) throw new RuntimeException( $message ); }

$stock = get_option( C::STOCK, array() );
$stock['v37-box'] = array( 'name' => 'کارتن آزمون دور چهارم', 'stock' => 12, 'active' => true );
update_option( C::STOCK, $stock, false );
$counter = wp_insert_user( array( 'user_login' => 'mahex-v37-counter', 'user_pass' => 'local-test-only', 'user_email' => 'v37-counter@example.test', 'role' => 'administrator' ) );
if ( is_wp_error( $counter ) ) $counter = get_user_by( 'login', 'mahex-v37-counter' )->ID;
$reconciler = wp_insert_user( array( 'user_login' => 'mahex-v37-reconciler', 'user_pass' => 'local-test-only', 'user_email' => 'v37-reconciler@example.test', 'role' => 'administrator' ) );
if ( is_wp_error( $reconciler ) ) $reconciler = get_user_by( 'login', 'mahex-v37-reconciler' )->ID;

$cycle = C::create( array( 'title' => 'شمارش واقعی دور چهارم', 'description' => 'شمارش دستی محیط یکپارچه', 'materials' => array( 'v37-box' ), 'threshold_abs' => 2, 'threshold_pct' => 20 ) );
$cycle = C::mutate( $cycle['id'], $cycle['revision'], 'start', array( 'assignee' => $counter ) );
wp_set_current_user( $counter ); C::recordCount( $cycle['id'], $cycle['revision'], 'v37-box', $cycle['members']['v37-box']['revision'], 1, 12 ); $cycle = C::get( $cycle['id'] );
wp_set_current_user( $reconciler ); $cycle = C::mutate( $cycle['id'], $cycle['revision'], 'reconcile', array( 'material' => 'v37-box', 'member_revision' => $cycle['members']['v37-box']['revision'], 'choice' => 'first', 'reason' => 'verified', 'note' => 'شمارش دستی با موجودی دفتر برابر بود' ) );
wp_set_current_user( 1 ); $cycle = C::mutate( $cycle['id'], $cycle['revision'], 'approve', array( 'material' => 'v37-box', 'member_revision' => $cycle['members']['v37-box']['revision'], 'note' => 'بازبینی مستقل در محیط آزمون انجام شد' ) );
$cycle = C::post( $cycle['id'], $cycle['revision'], array( 'v37-box' ) );
checkV37( $cycle['members']['v37-box']['lane'] === 'posted', 'cycle count posting' );
checkV37( C::privacyForUser( $counter ) !== array(), 'cycle actor privacy' );

$pendingCycle = C::create( array( 'title' => 'کارت مرورگر دور چهارم', 'materials' => array( 'v37-box' ) ) );
$orders = get_option( R::PURCHASES, array() );
$orders['v37-po'] = array( 'id' => 'v37-po', 'status' => 'draft', 'revision' => 1, 'supplier' => 'تأمین‌کننده آزمایشی محلی', 'created_at' => gmdate( 'c' ), 'lines' => array( 'v37-box' => array( 'name' => 'کارتن آزمون دور چهارم', 'qty' => 5, 'unit_cost_irr' => 1000, 'precision' => 0 ) ), '_v37' => true, 'receiving_version' => 37 );
update_option( R::PURCHASES, $orders, false );
$receipt = R::create( 'v37-po', 1, 'integration-v37-complete', array( 'v37-box' ) );
$receipt = R::countLine( $receipt['id'], $receipt['revision'], 'v37-box', $receipt['lines']['v37-box']['revision'], array( 'human' => true, 'counted' => 5, 'damaged' => 1, 'lot' => 'LOT-37', 'expiry' => '2028-01-01', 'observation' => 'یک کارتن در مشاهده دستی له شده بود', 'defects' => array( 'crushed' ) ) );
$receipt = R::decide( $receipt['id'], $receipt['revision'], 'v37-box', $receipt['lines']['v37-box']['revision'], array( 'accepted' => 4, 'quarantined' => 1, 'rejected' => 0, 'reason' => 'آسیب برای بازبینی دوم قرنطینه شد' ) );
wp_set_current_user( $reconciler ); $receipt = R::release( $receipt['id'], $receipt['revision'], 'v37-box', false, 'آسیب در مشاهده دوم تأیید و رد شد' );
wp_set_current_user( 1 ); $before = (float) get_option( R::STOCK )['v37-box']['stock']; $receipt = R::post( $receipt['id'], $receipt['revision'] );
checkV37( (float) get_option( R::STOCK )['v37-box']['stock'] === $before + 4, 'exactly-once receiving stock' );
$again = R::post( $receipt['id'], $receipt['revision'] ); checkV37( (float) get_option( R::STOCK )['v37-box']['stock'] === $before + 4, 'idempotent receiving retry' );
$orders = get_option( R::PURCHASES ); $orders['v37-po-browser'] = array( 'id' => 'v37-po-browser', 'status' => 'draft', 'revision' => 1, 'supplier' => 'مرورگر محلی', 'created_at' => gmdate( 'c' ), 'lines' => array( 'v37-box' => array( 'name' => 'کارتن آزمون دور چهارم', 'qty' => 2, 'unit_cost_irr' => 1000, 'precision' => 0 ) ), '_v37' => true, 'receiving_version' => 37 ); update_option( R::PURCHASES, $orders, false );
$pendingReceipt = R::create( 'v37-po-browser', 1, 'integration-v37-browser', array( 'v37-box' ) );
checkV37( R::privacyForUser( 1 ) !== array(), 'receiving actor privacy' );
$f = json_decode( file_get_contents( getenv( 'MAHEX_FIXTURES' ) ), true ); $f['v37_cycle'] = $pendingCycle['id']; $f['v37_receipt'] = $pendingReceipt['id']; file_put_contents( getenv( 'MAHEX_FIXTURES' ), wp_json_encode( $f ) );
echo 'V37 real WordPress inventory cycle, actor privacy, staged receiving, quarantine and exactly-once stock passed' . PHP_EOL;
