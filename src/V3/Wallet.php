<?php
namespace HoseinMomeni\MahexWoo\V3;
final class Wallet {
 private static function t(string $n):string{global $wpdb;return $wpdb->prefix.'hm_mahex_v3_'.$n;}
 public static function get(int $customer): array {global $wpdb;$r=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::t('wallet').' WHERE customer_id=%d',$customer),ARRAY_A);return $r?:array('customer_id'=>$customer,'balance_irr'=>0,'credit_limit_irr'=>0);}
 public static function setLimit(int $customer,float $limit): void {global $wpdb;$r=self::get($customer);$wpdb->replace(self::t('wallet'),array('customer_id'=>$customer,'balance_irr'=>(float)$r['balance_irr'],'credit_limit_irr'=>max(0,$limit),'updated_at'=>current_time('mysql')));}
 public static function entry(int $customer,float $amount,string $type,int $order=0,string $note=''): bool {global $wpdb;$r=self::get($customer);$new=(float)$r['balance_irr']+$amount;if($new<-(float)$r['credit_limit_irr'])return false;$wpdb->replace(self::t('wallet'),array('customer_id'=>$customer,'balance_irr'=>$new,'credit_limit_irr'=>(float)$r['credit_limit_irr'],'updated_at'=>current_time('mysql')));$wpdb->insert(self::t('wallet_ledger'),array('customer_id'=>$customer,'order_id'=>$order,'amount_irr'=>$amount,'type'=>sanitize_key($type),'note'=>sanitize_textarea_field($note),'actor_id'=>get_current_user_id(),'created_at'=>current_time('mysql')));return true;}
 public static function aging(int $days=30): array {global $wpdb;return (array)$wpdb->get_results($wpdb->prepare('SELECT customer_id,SUM(amount_irr) net,MIN(created_at) oldest FROM '.self::t('wallet_ledger').' WHERE created_at<%s GROUP BY customer_id HAVING net<0 ORDER BY net ASC',gmdate('Y-m-d H:i:s',time()-$days*DAY_IN_SECONDS)),ARRAY_A);}
}
