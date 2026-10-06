<?php
define('ABSPATH',__DIR__);
require_once __DIR__.'/../src/V31/OperationsValidation.php';
require_once __DIR__.'/../src/Shipments/Shipment.php';
require_once __DIR__.'/../src/Shipments/OrderShipmentStore.php';
require_once __DIR__.'/../src/V31/OperationsImport.php';
use HoseinMomeni\MahexWoo\V31\OperationsImport;
use HoseinMomeni\MahexWoo\V31\OperationsValidation;
function wc_get_order($id){return false;}
function current_user_can(...$args){return true;}
function check($ok,$message){if(!$ok)throw new RuntimeException($message);}
check(OperationsValidation::digits('۰۹۱۲٣٤٥٦٧٨٩')==='09123456789','Mixed Persian/Arabic digits normalize');
$path=tempnam(sys_get_temp_dir(),'mahex-csv');
try {
 file_put_contents($path,"\xEF\xBB\xBForder_id,waybill,tracking,status\n12,123456,123456,in_transit\n12,123456,123456,bogus\n13,=cmd,123456,created\n");
 $rows=OperationsImport::parse($path);
 check(count($rows)===3,'Three rows parsed');
 check(in_array('سفارش تکراری است.',$rows[1]['errors'],true),'Duplicate IDs rejected');
 check(in_array('وضعیت نامعتبر است.',$rows[1]['errors'],true),'Invalid status rejected');
 check(count($rows[2]['errors'])===2,'Formula-like reference rejected');
 file_put_contents($path,"order_id,tracking,status\n");$thrown=false;
 try{OperationsImport::parse($path);}catch(RuntimeException $e){$thrown=true;}
 check($thrown,'Header mismatch rejects full file');
} finally {unlink($path);}
echo "Operations parser tests passed\n";

class WC_Order {
 public array $meta=[];
 public function get_billing_phone(){return '۰۹۱۲۳۴۵۶۷۸۹';}
 public function get_billing_postcode(){return '۱۲۳۴۵۶۷۸۹۰';}
 public function get_shipping_address_1(){return '';}
 public function get_billing_address_1(){return 'نشانی';}
 public function get_billing_city(){return 'قم';}
 public function get_billing_state(){return 'QHM';}
 public function get_items(){return [];}
 public function get_customer_id(){return 42;}
 public function get_billing_email(){return 'customer@example.test';}
 public function get_view_order_url(){return 'https://store.test/my-account/view-order/1/';}
 public function get_order_number(){return '100';}
 public function get_meta($key,$single=true){return $this->meta[$key]??[];}
 public function update_meta_data($key,$value){$this->meta[$key]=$value;}
 public function save_meta_data(){}
}
require_once __DIR__.'/../src/V31/Operations.php';
use HoseinMomeni\MahexWoo\V31\Operations;
use HoseinMomeni\MahexWoo\Shipments\Shipment;
$GLOBALS['options']=[];$GLOBALS['mail']=[];$GLOBALS['logged']=false;$GLOBALS['user']=43;
function get_option($key,$default=[]){return $GLOBALS['options'][$key]??$default;}
function is_email($value){return filter_var($value,FILTER_VALIDATE_EMAIL);}
function wp_mail(...$args){$GLOBALS['mail'][]=$args;return true;}
function is_user_logged_in(){return $GLOBALS['logged'];}
function get_current_user_id(){return $GLOBALS['user'];}
$order=new WC_Order();check(OperationsValidation::errors($order)===[],'Persian contact fields and billing fallback accepted');
$shipment=new Shipment('one','TRACK123','WAY123','created',[],'now','now');
Operations::notify($order,$shipment,'manual_csv_import');check(count($GLOBALS['mail'])===0,'Notifications default off');
$GLOBALS['options'][Operations::OPTION]=['notifications'=>true,'subject'=>'سفارش {order}','message'=>'{status} {waybill}'];
Operations::notify($order,$shipment,'manual_csv_import');Operations::notify($order,$shipment,'manual_csv_import');
check(count($GLOBALS['mail'])===1,'Repeated identical saved event sends once');
check(strpos($GLOBALS['mail'][0][2],'بارنامه رسمی ماهکس نیست')!==false,'Local identifiers explicitly labelled in email');
ob_start();Operations::tracking($order);$output=ob_get_clean();check($output==='','Logged out cannot see tracking');
$GLOBALS['logged']=true;ob_start();Operations::tracking($order);$output=ob_get_clean();check($output==='','Different customer cannot see tracking');
echo "Operations access and notification tests passed\n";
if(class_exists('ZipArchive')&&function_exists('simplexml_load_string')) {
 require_once __DIR__.'/../src/V31/FinanceXlsx.php';
 $xlsx=HoseinMomeni\MahexWoo\V31\FinanceXlsx::create([['order_id','waybill','tracking','status'],['12','00123456','00123456','created']]);
 try{$rows=OperationsImport::parse($xlsx,'xlsx');check($rows[0]['data']===['12','00123456','00123456','created'],'Native Excel import preserves leading zero carrier references');}finally{unlink($xlsx);}
 echo "Native XLSX operations import test passed\n";
}
