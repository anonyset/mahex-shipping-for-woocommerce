<?php

namespace HoseinMomeni\MahexWoo\Enterprise;

final class Roles {
	public const MANAGE='hm_mahex_manage_shipping';
	public const PACK='hm_mahex_pack_shipments';
	public const REPORT='hm_mahex_view_reports';
	public const SETTINGS='hm_mahex_manage_settings';
	public const INVENTORY='hm_mahex_manage_inventory';
	public const PRINT_DOCS='hm_mahex_print_documents';
	public const FINANCE='hm_mahex_view_finance';

	public static function install(): void {
		$all=array(self::MANAGE,self::PACK,self::REPORT,self::SETTINGS,self::INVENTORY,self::PRINT_DOCS,self::FINANCE);
		foreach(array('administrator','shop_manager') as $slug){$role=get_role($slug);if($role)foreach($all as $cap)$role->add_cap($cap);}
		if(!Config::bool('operator_role_enabled',true)){foreach(array('mahex_operator','mahex_packer','mahex_accountant') as $slug)if(get_role($slug))remove_role($slug);return;}
		self::role('mahex_operator','اپراتور ماهکس',array(self::MANAGE,self::PACK,self::REPORT,self::PRINT_DOCS));
		self::role('mahex_packer','اپراتور بسته‌بندی ماهکس',array(self::PACK,self::PRINT_DOCS,self::INVENTORY));
		self::role('mahex_accountant','حسابدار ارسال ماهکس',array(self::REPORT,self::FINANCE));
	}

	private static function role(string $slug,string $name,array $caps): void {
		$base=array('read'=>true,'edit_shop_orders'=>true,'edit_others_shop_orders'=>true,'edit_published_shop_orders'=>true,'read_private_shop_orders'=>true);
		if(!get_role($slug))add_role($slug,$name,$base+array_fill_keys($caps,true));
		$role=get_role($slug);if($role){foreach(array_keys($base) as $cap)$role->add_cap($cap);foreach($caps as $cap)$role->add_cap($cap);}
	}

	public static function canManage(): bool {return current_user_can(self::MANAGE)||current_user_can('manage_woocommerce');}
	public static function canSettings(): bool {return current_user_can(self::SETTINGS)||current_user_can('manage_woocommerce');}
	public static function canPack(): bool {return current_user_can(self::PACK)||current_user_can('manage_woocommerce');}
	public static function canPrint(): bool {return current_user_can(self::PRINT_DOCS)||self::canPack();}
	public static function canInventory(): bool {return current_user_can(self::INVENTORY)||current_user_can('manage_woocommerce');}
	public static function canReport(): bool {return current_user_can(self::REPORT)||current_user_can(self::FINANCE)||current_user_can('manage_woocommerce');}
}
