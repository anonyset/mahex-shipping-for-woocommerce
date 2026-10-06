<?php

namespace HoseinMomeni\MahexWoo\Enterprise;

final class HealthMonitor {
	public const HOOK='hm_mahex_health_check';
	public static function register(): void {add_action(self::HOOK,array(self::class,'run'));add_action('init',array(self::class,'schedule'));add_action('admin_notices',array(self::class,'notice'));}
	public static function schedule(): void {if(!wp_next_scheduled(self::HOOK))wp_schedule_event(time()+300,'hourly',self::HOOK);}
	public static function issues(): array {$issues=array();if(defined('DISABLE_WP_CRON')&&DISABLE_WP_CRON)$issues[]='WP-Cron غیرفعال است؛ باید cron واقعی سرور جایگزین شود.';$q=QueueStore::counts();if(($q['dead']??0)>0)$issues[]='صف ماهکس '.(int)$q['dead'].' کار Dead Letter دارد.';if(\HoseinMomeni\MahexWoo\V1\SafeMode::active())$issues[]='Safe Mode نسخه 1 فعال است.';return $issues;}
	public static function run(): void {$issues=self::issues();if(!$issues||!Config::bool('health_email_enabled'))return;$hash=md5(implode('|',$issues));if(get_transient('hm_mahex_health_mail_hash')===$hash)return;$email=(string)Config::get('health_email','');if(''===$email)$email=(string)get_option('admin_email');if(is_email($email))wp_mail($email,'هشدار سلامت Mahex Shipping',implode("\n",$issues));set_transient('hm_mahex_health_mail_hash',$hash,6*HOUR_IN_SECONDS);}
	public static function notice(): void {if(!Roles::canManage())return;$issues=self::issues();if(!$issues)return;echo '<div class="notice notice-warning"><p><strong>ماهکس:</strong> '.esc_html(implode(' | ',$issues)).' <a href="'.esc_url(admin_url('admin.php?page=hm-mahex-enterprise&tab=health')).'">جزئیات</a></p></div>';}
}
