<?php

namespace HoseinMomeni\MahexWoo\Documents;

use HoseinMomeni\MahexWoo\Barcode\BarcodeRenderer;
use HoseinMomeni\MahexWoo\Barcode\QrCodeSvgRenderer;
use HoseinMomeni\MahexWoo\Pro\FeatureSettings;
use HoseinMomeni\MahexWoo\V1\Config as V1Config;

final class WaybillRenderer {
	public function __construct(
		private readonly BarcodeRenderer $barcode,
		private readonly LocalTrackingUrl $tracking_url = new LocalTrackingUrl(),
		private readonly QrCodeSvgRenderer $qr = new QrCodeSvgRenderer(),
	) {}

	public function render(WaybillData $data): string {return $this->render_document($data,'combined');}
	public function render_waybill(WaybillData $data): string {return $this->render_document($data,'waybill');}
	public function render_label(WaybillData $data): string {return $this->render_document($data,'label');}

	private function render_document(WaybillData $data,string $mode): string {
		$e=static fn(string $v):string=>htmlspecialchars($v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
		$barcodeValue=$data->barcode_number!==''?$data->barcode_number:$data->waybill_number;
		$barcode=$this->barcode->render_svg($barcodeValue);$qr=$this->qr->render($data->qr_payload(),3);
		$template=(string)FeatureSettings::get('label_template','classic');$font=$this->font();$watermark=$this->watermark();
		$logoUrl=esc_url((string)FeatureSettings::get('store_logo_url',''));
		$logo=$logoUrl!==''?'<img class="store-logo" src="'.$e($logoUrl).'" alt="Logo">':'<div class="mhx-word">MAHEX</div>';
		$fields=array('فرستنده'=>$data->sender_name,'گیرنده'=>$data->recipient_name,'نشانی گیرنده'=>$data->recipient_address,'تماس'=>$data->recipient_phone_masked,'محتویات'=>$data->contents,'تعداد'=>(string)$data->item_count,'وزن'=>$data->weight,'ابعاد'=>$data->dimensions);
		$rows='';foreach($fields as $label=>$value)$rows.='<div class="field"><b>'.$e($label).'</b><span>'.$e($value).'</span></div>';
		$labelHtml=$this->label_html($data,$barcodeValue,$barcode,$watermark);
		if('label'===$mode){$thermal='80x100'===(string)FeatureSettings::get('thermal_size','100x150')?'80mm 100mm':'100mm 150mm';return '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><title>تگ مرسوله '.$e($data->waybill_number).'</title><style>@page{size:'.$thermal.';margin:5mm}*{box-sizing:border-box}body{margin:0;font-family:'.$font.',Tahoma,Arial,sans-serif;color:#101828}.label{position:relative;overflow:hidden;border:2px solid #082e63;border-radius:10px;padding:14px}.label h2{margin:0 0 12px;color:#082e63}.label p{margin:7px 0}.label--compact p{margin:3px 0;font-size:11px}.label--minimal{border-width:1px;border-radius:0}.label-code{direction:ltr;border-top:1px dashed #94a3b8;margin-top:14px;padding-top:10px}.mhx-barcode{width:100%;height:86px}.wm{position:absolute;inset:20% 10%;opacity:.07;object-fit:contain;width:80%;height:60%;z-index:0}.label>*:not(.wm){position:relative;z-index:1}@media print{body{print-color-adjust:exact;-webkit-print-color-adjust:exact}}</style></head><body>'.$labelHtml.'</body></html>';}
		$cod=(string)$data->cod_amount;$codHtml=(!V1Config::bool('label_conditional_cod',true)||!in_array(trim($cod),array('','0','۰'),true))?'<div><b>پس‌کرایه:</b> '.$e($cod).'</div>':'';
		return '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><title>بارنامه '.$e($data->waybill_number).'</title><style>@page{size:A5;margin:7mm}*{box-sizing:border-box}body{margin:0;color:#101828;font-family:'.$font.',Tahoma,Arial,sans-serif;background:#fff}.waybill{position:relative;border:2px solid #0b2f65;border-radius:12px;overflow:hidden}.head{display:flex;justify-content:space-between;align-items:center;padding:12px 16px;color:#fff;background:#082e63}.mhx-word{font:900 28px Arial,sans-serif}.store-logo{max-width:170px;max-height:55px;object-fit:contain}.route{display:grid;grid-template-columns:1fr auto 1fr;gap:12px;align-items:center;padding:14px 16px;background:#eff6ff}.route strong{font-size:18px}.arrow{color:#ef233c;font-size:24px}.meta,.grid,.fees{display:grid;grid-template-columns:1fr 1fr;border-top:1px solid #cbd5e1}.meta>div,.field,.fees>div{padding:8px 12px;border-left:1px solid #cbd5e1;border-bottom:1px solid #cbd5e1}.field{display:flex;justify-content:space-between;gap:16px}.codes{display:grid;grid-template-columns:2fr 1fr;gap:14px;align-items:center;padding:14px}.mhx-barcode{width:100%;height:82px}.qr{text-align:center}.mhx-qr-code{width:130px;height:130px;max-width:100%}.label{border-top:2px dashed #64748b;padding:12px 16px;position:relative}.label-code{display:none}.notice{padding:8px 14px;background:#f8fafc;font-size:11px}.wm{position:absolute;inset:25% 10%;opacity:.05;object-fit:contain;width:80%;height:50%}@media print{body{print-color-adjust:exact;-webkit-print-color-adjust:exact}}</style></head><body><main class="waybill waybill--'.$e($template).'">'.$watermark.'<header class="head">'.$logo.'<div>بارنامه فروشگاهی ماهکس</div></header><section class="route"><strong>'.$e($data->origin).'</strong><span class="arrow">←</span><strong>'.$e($data->destination).'</strong></section><section class="meta"><div><b>شماره بارنامه:</b> '.$e($data->waybill_number).'</div><div><b>تاریخ:</b> '.$e($data->created_at).'</div></section><section class="grid">'.$rows.'</section><section class="fees"><div><b>حمل:</b> '.$e($data->shipping_cost).'</div><div><b>بسته‌بندی:</b> '.$e($data->packaging_cost).'</div>'.$codHtml.'<div><b>جمع خدمات:</b> '.$e($data->total_label()).'</div></section><section class="codes"><div>'.$barcode.'</div><div class="qr">'.$qr.'</div></section>'.('combined'===$mode?$labelHtml:'').'<footer class="notice">این سند از اطلاعات سفارش و عملیات محلی فروشگاه تولید شده است.</footer></main></body></html>';
	}

	private function label_html(WaybillData $data,string $barcodeValue,string $barcode,string $watermark): string {
		$e=static fn(string $v):string=>htmlspecialchars($v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');$map=array('recipient'=>array('گیرنده',$data->recipient_name),'destination'=>array('مقصد',$data->destination),'address'=>array('نشانی',$data->recipient_address),'phone'=>array('شماره گیرنده',$data->recipient_phone_masked),'barcode'=>array('شماره بارکد',$barcodeValue),'items'=>array('اقلام',$data->contents),'weight'=>array('وزن',$data->weight),'order'=>array('بارنامه',$data->waybill_number));$wanted=array_filter(explode(',',(string)V1Config::get('label_fields','recipient,destination,address,phone,barcode,items,weight,order')));$body='';foreach($wanted as $key){if(!isset($map[$key]))continue;$body.='<p><b>'.$e($map[$key][0]).':</b> <span'.(in_array($key,array('phone','barcode'),true)?' dir="ltr"':'').'>'.$e($map[$key][1]).'</span></p>';}
		return '<section class="label label--'.esc_attr((string)FeatureSettings::get('label_template','classic')).'">'.$watermark.'<h2>تگ مقصد: '.$e($data->destination).'</h2>'.$body.'<div class="label-code">'.$barcode.'</div></section>';
	}
	private function font(): string {$f=(string)V1Config::get('label_font','Tahoma');return preg_replace('/[^A-Za-z0-9 _-]/','',$f)?:'Tahoma';}
	private function watermark(): string {$u=esc_url((string)V1Config::get('label_watermark',''));return $u!==''?'<img class="wm" src="'.esc_attr($u).'" alt="">':'';}
}
