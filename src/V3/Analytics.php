<?php
namespace HoseinMomeni\MahexWoo\V3;
final class Analytics {
 private static function t(string $n):string{global $wpdb;return $wpdb->prefix.'hm_mahex_v3_'.$n;}
 public static function snapshotDate(string $date=''): void {global $wpdb;$date=$date?:wp_date('Y-m-d');foreach(BranchManager::profitability(1) as $r){$b=(int)$r['branch']['id'];foreach(array('orders'=>(float)$r['orders'],'revenue'=>(float)$r['revenue'],'cost'=>(float)$r['cost'],'profit'=>(float)$r['profit']) as $k=>$v)$wpdb->replace(self::t('snapshots'),array('snapshot_date'=>$date,'branch_id'=>$b,'metric_key'=>$k,'value_num'=>$v,'context'=>'{}','created_at'=>current_time('mysql')));}}
 public static function trend(string $metric='orders',int $days=30,int $branch=0): array {global $wpdb;$sql='SELECT snapshot_date,value_num FROM '.self::t('snapshots').' WHERE metric_key=%s AND snapshot_date>=%s';$args=[$metric,wp_date('Y-m-d',time()-$days*DAY_IN_SECONDS)];if($branch){$sql.=' AND branch_id=%d';$args[]=$branch;}$sql.=' ORDER BY snapshot_date';return (array)$wpdb->get_results($wpdb->prepare($sql,...$args),ARRAY_A);}
 public static function forecastCapacity(int $branch,int $days=7): array {$rows=self::trend('orders',28,$branch);$vals=array_map(fn($r)=>(float)$r['value_num'],$rows);$avg=$vals?array_sum($vals)/count($vals):0;$peak=$vals?max($vals):0;return array('daily_average'=>round($avg,1),'predicted_peak'=>(int)ceil(max($avg*1.25,$peak)),'recommended_capacity'=>(int)ceil(max($avg*1.4,$peak*1.1)),'days'=>$days);}
}
