<?php
if(!defined('ABSPATH'))require getenv('MAHEX_WP_ROOT').'/wp-load.php';wp_set_current_user(1);$f=json_decode(file_get_contents(getenv('MAHEX_FIXTURES')),true);$order=wc_get_order($f['orders'][0]);$order->read_meta_data(true);$state=HoseinMomeni\MahexWoo\V33\Station::data($order);$stock=HoseinMomeni\MahexWoo\V33\Station::catalog();$plan=HoseinMomeni\MahexWoo\V32\Packing::snapshot($order);
if(count($state['scans'])!==2||(float)$state['parcels'][0]['weight_g']!==900.0||(float)$stock['carton_e2e-box']['stock']!==9.0||!hash_equals($state['committed_plan_hash'],hash('sha256',wp_json_encode($plan['boxes']))))throw new RuntimeException('Browser mutation did not persist exactly one material consumption');
if(HoseinMomeni\MahexWoo\V33\BoardTools::lease($order->get_id()))throw new RuntimeException('Browser failed to release its editing lease');
echo 'Post-browser real database scan/weight, plan fingerprint and idempotent stock checks passed'.PHP_EOL;
