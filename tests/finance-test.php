<?php
/** Standalone financial regression checks; no WordPress database required. */
namespace { $options=[]; function get_option($key,$default=[]){global $options;return $options[$key]??$default;}function wp_date($format){return '2026-10-06';} }
namespace HoseinMomeni\MahexWoo\V31 {
 require __DIR__.'/../src/V31/Finance.php';require __DIR__.'/../src/V31/FinanceRates.php';require __DIR__.'/../src/V31/FinanceXlsx.php';
 function check($condition,$message){if(!$condition)throw new \RuntimeException($message);}
 class ShippingFixture { public $meta;public $total;public function __construct($total,$meta){$this->total=$total;$this->meta=$meta;}public function get_method_id(){return 'hm_mahex';}public function get_meta($key,$single=true){return $this->meta[$key]??'';}public function get_total(){return $this->total;} }
 class OrderFixture {public $items;public $meta=[];public function get_items($type){return $this->items;}public function get_meta($key,$single=true){return $this->meta[$key]??'';}public function get_currency(){return 'IRT';}}
 $order=new OrderFixture();$order->items=[new ShippingFixture(150,['hm_mahex_freight'=>100,'hm_mahex_packaging'=>30,'hm_mahex_insurance'=>20])];
 $a=Finance::allocation($order);check($a['revenue']===150.0,'Packaging counted twice');check($a['profit']===null,'Unknown costs must not become zero');
 foreach(['carrier'=>80,'packaging'=>10,'insurance'=>5,'operational'=>0] as $key=>$value)$order->meta['_hm_mahex_actual_'.$key.'_cost']=$value;
 $a=Finance::allocation($order);check($a['profit']===55.0,'Actual cost profit incorrect');check($a['currency']==='IRT','Currency changed');
 $order->items=[new ShippingFixture(0,['hm_mahex_freight'=>100,'hm_mahex_packaging'=>30,'hm_mahex_insurance'=>20])];check(array_sum(Finance::components($order))===0.0,'Discounted shipping reconciliation incorrect');
 global $options;$options[FinanceRates::OPTION]=[['id'=>'past','date'=>'2026-10-01','settings'=>['manual_base_cost'=>100]],['id'=>'future','date'=>'2026-10-10','settings'=>['manual_base_cost'=>200]]];
 check(FinanceRates::active()['id']==='past','Future tariff applied early');check(FinanceRates::active('2026-10-10')['id']==='future','Tariff boundary incorrect');check(FinanceRates::settings(['other'=>50])===['other'=>50,'manual_base_cost'=>100],'Tariff overwrote unrelated settings');
 if(class_exists('ZipArchive')&&function_exists('simplexml_load_string')){$rows=[['شماره سفارش','بارنامه'],['123','=HYPERLINK("bad")'],['456','010012345678']];$path=FinanceXlsx::create($rows);try{check(FinanceXlsx::readRows($path)===$rows,'XLSX round trip lost text or Persian');$zip=new \ZipArchive();$zip->open($path);$zip->addFromString('xl/worksheets/sheet1.xml','<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row r="1"><c r="A1"><f>HYPERLINK("bad")</f><v>123</v></c></row></sheetData></worksheet>');$zip->close();$formulaRejected=false;try{FinanceXlsx::readRows($path);}catch(\RuntimeException $e){$formulaRejected=true;}check($formulaRejected,'Spreadsheet formula not rejected');$zip->open($path);$zip->addFromString('xl/worksheets/sheet1.xml','<!DOCTYPE worksheet [<!ENTITY bad SYSTEM "file:///etc/passwd">]><worksheet/>');$zip->close();$rejected=false;try{FinanceXlsx::readRows($path);}catch(\RuntimeException $e){$rejected=true;}check($rejected,'DTD was not rejected');}finally{unlink($path);}}
 echo "V31 finance regression checks passed\n";
}
