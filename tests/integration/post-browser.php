<?php
if(!defined('ABSPATH'))require getenv('MAHEX_WP_ROOT').'/wp-load.php';wp_set_current_user(1);$f=json_decode(file_get_contents(getenv('MAHEX_FIXTURES')),true);$order=wc_get_order($f['orders'][0]);$order->read_meta_data(true);$state=HoseinMomeni\MahexWoo\V33\Station::data($order);$stock=HoseinMomeni\MahexWoo\V33\Station::catalog();$plan=HoseinMomeni\MahexWoo\V32\Packing::snapshot($order);
if(count($state['scans'])!==2||(float)$state['parcels'][0]['weight_g']!==900.0||(float)$stock['carton_e2e-box']['stock']!==9.0||!hash_equals($state['committed_plan_hash'],hash('sha256',wp_json_encode($plan['boxes']))))throw new RuntimeException('Browser mutation did not persist exactly one material consumption');
if(HoseinMomeni\MahexWoo\V33\BoardTools::lease($order->get_id()))throw new RuntimeException('Browser failed to release its editing lease');
echo 'Post-browser real database scan/weight, plan fingerprint and idempotent stock checks passed'.PHP_EOL;

$dispatchOrder=wc_get_order($f['orders'][1]);$dispatchOrder->read_meta_data(true);$dispatch=HoseinMomeni\MahexWoo\V34\DispatchWorkbench::data($dispatchOrder);$case=$dispatchOrder->get_meta(HoseinMomeni\MahexWoo\V34\ServicePolicies::META,true);
if($dispatchOrder->get_shipping_phone()!=='09123456789'||$dispatch['bin']!=='QOM-A1'||!HoseinMomeni\MahexWoo\V34\DispatchWorkbench::reviewed($dispatch,HoseinMomeni\MahexWoo\V34\DispatchWorkbench::address($dispatchOrder))||empty($case['original_deadline'])||count($case['incidents'])!==1||reset($case['incidents'])['status']!=='closed')throw new RuntimeException('V34 browser mutations did not persist in real order datastore');
echo 'Post-browser V34 address, bin, verification, frozen promise and resolved incident persistence passed'.PHP_EOL;

$policy=HoseinMomeni\MahexWoo\V34\ServicePolicies::policy();if($policy['effort_minutes']!==90||$policy['weekly_shifts'][3]!==['10:00','17:00']||$policy['date_shifts']['2026-10-09']!==['06:00','20:00']||!in_array(['15:00','15:30'],$policy['breaks'],true))throw new RuntimeException('Structured calendar editor did not persist typed intervals');
echo 'Structured calendar editor persisted effort, weekly shift, exception date and added break'.PHP_EOL;
