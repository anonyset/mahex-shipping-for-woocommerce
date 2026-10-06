<?php
require_once __DIR__ . '/../src/Update/UpdateSafety.php';
require_once __DIR__ . '/../src/Update/GitHubUpdater.php';
use HoseinMomeni\MahexWoo\Update\UpdateSafety;
use HoseinMomeni\MahexWoo\Update\GitHubUpdater;
define('HM_MAHEX_FILE','/plugins/mahex-shipping-for-woocommerce/mahex-shipping-for-woocommerce.php');
define('HM_MAHEX_VERSION','3.1.0'); define('WC_VERSION','11.0');
class WP_Error { public function __construct(public $code, public $message) {} }
function expect($condition, $message) { if (!$condition) throw new RuntimeException($message); }
function is_wp_error($v){return $v instanceof WP_Error;}
function apply_filters($name,$value){return $value;}
function get_site_transient($key){return $GLOBALS['manifest'];}
function plugin_basename($path){return 'mahex-shipping-for-woocommerce/mahex-shipping-for-woocommerce.php';}
function get_option($key,$default=[]){return $GLOBALS['options'][$key]??$default;}
function update_option($key,$value,$autoload=false){$GLOBALS['options'][$key]=$value;return true;}
function download_url($package,$timeout){$p=tempnam(sys_get_temp_dir(),'mhx');file_put_contents($p,'validated package bytes');$GLOBALS['downloaded']=$p;return $p;}
function wp_delete_file($p){unlink($p);}
function esc_url_raw($url){return $url;}
function wp_parse_url($url,$component){return parse_url($url,$component);}
$GLOBALS['wp_version']='7.1';$GLOBALS['options']=[];
$m = ['version'=>'3.1.1','download_url'=>'https://github.com/anonyset/mahex-shipping-for-woocommerce/releases/download/v3.1.1/plugin.zip','requires_php'=>'8.1','requires'=>'7.1','requires_wc'=>'11.0'];
expect(UpdateSafety::compatibility($m,'8.1.0','7.1','11.0.0') === [], 'Exact minimum versions accepted');
expect(count(UpdateSafety::compatibility($m,'8.0','7.0','10.9')) === 3, 'Old runtimes blocked');
expect(count(UpdateSafety::compatibility($m,'8.2','7.2','')) === 1, 'Missing WooCommerce blocked');
expect(UpdateSafety::compatibility([], '8.1','7.1','11.0') === [], 'Legacy missing fields supported');
$GLOBALS['manifest']=$m+['sha256'=>hash('sha256','validated package bytes')];$extra=['plugin'=>plugin_basename(HM_MAHEX_FILE)];
$file=UpdateSafety::download(false,$m['download_url'],null,$extra);expect(is_string($file)&&is_file($file),'Matching digest permits downloaded file');unlink($file);
$GLOBALS['manifest']['sha256']=str_repeat('0',64);$result=UpdateSafety::download(false,$m['download_url'],null,$extra);expect(is_wp_error($result)&&!file_exists($GLOBALS['downloaded']),'Mismatch blocks update and removes file');
expect(UpdateSafety::download(false,$m['download_url'],null,['plugin'=>'other/other.php'])===false,'Other plugins unaffected');
$GLOBALS['manifest']=$m;expect(UpdateSafety::beforeInstall(true,$extra)===true,'Compatible install allowed');expect(count($GLOBALS['options']['hm_mahex_update_backups'])===1,'Settings snapshot recorded');
$GLOBALS['manifest']['requires_wc']='99.0';expect(is_wp_error(UpdateSafety::beforeInstall(true,$extra)),'Incompatible WooCommerce blocked before install');
$method=new ReflectionMethod(GitHubUpdater::class,'safePackageUrl');
expect($method->invoke(null,'http://github.com/anonyset/mahex-shipping-for-woocommerce/file.zip')==='','HTTP rejected');
expect($method->invoke(null,'https://github.com/other/anonyset/mahex-shipping-for-woocommerce/file.zip')==='','Path prefix attack rejected');
expect($method->invoke(null,$m['download_url'])===$m['download_url'],'Own release asset accepted');
echo "Update compatibility, checksum and settings snapshot tests passed\n";
