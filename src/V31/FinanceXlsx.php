<?php
namespace HoseinMomeni\MahexWoo\V31;

/** Minimal real XLSX writer; inline strings avoid spreadsheet formula injection. */
final class FinanceXlsx {
 /** Read the first worksheet; rejects oversized archives and parses without external entities. */
 public static function readRows(string $path): array {
  if(!class_exists('ZipArchive') || !function_exists('simplexml_load_string')) throw new \RuntimeException('خواندن XLSX به PHP Zip و SimpleXML نیاز دارد.');
  $zip=new \ZipArchive();if($zip->open($path)!==true)throw new \RuntimeException('فایل XLSX معتبر نیست.');
  try {
   $total=0;for($i=0;$i<$zip->numFiles;$i++){$stat=$zip->statIndex($i);$total+=(int)$stat['size'];if($total>20000000||$zip->numFiles>2000)throw new \RuntimeException('فایل اکسل بیش از اندازه بزرگ است.');}
   $xml=function(string $name)use($zip){$data=$zip->getFromName($name);if($data===false)return null;if(stripos($data,'<!DOCTYPE')!==false||stripos($data,'<!ENTITY')!==false)throw new \RuntimeException('ساختار XML مجاز نیست.');$old=libxml_use_internal_errors(true);$doc=simplexml_load_string($data,\SimpleXMLElement::class,LIBXML_NONET);libxml_clear_errors();libxml_use_internal_errors($old);if($doc===false)throw new \RuntimeException('ساختار XML اکسل نامعتبر است.');return $doc;};
   $workbook=$xml('xl/workbook.xml');$rels=$xml('xl/_rels/workbook.xml.rels');if($workbook===null||$rels===null)throw new \RuntimeException('فایل XLSX معتبر نیست.');
   $ns='http://schemas.openxmlformats.org/spreadsheetml/2006/main';$book=$workbook->children($ns);$first=$book->sheets->sheet[0];if($first===null)throw new \RuntimeException('اکسل بدون برگه است.');$rid=(string)$first->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')->id;$target='';
   foreach($rels->children('http://schemas.openxmlformats.org/package/2006/relationships') as $rel)if((string)$rel->attributes()['Id']===$rid)$target=(string)$rel->attributes()['Target'];
   if(str_contains($target,'..')||str_contains($target,':')||$target==='')throw new \RuntimeException('مسیر برگه نامعتبر است.');$sheetPath=str_starts_with($target,'/')?ltrim($target,'/'):'xl/'.$target;
   $sheet=$xml($sheetPath);if($sheet===null)throw new \RuntimeException('برگه اکسل پیدا نشد.');$shared=$xml('xl/sharedStrings.xml');$strings=[];
   if($shared!==null)foreach($shared->children($ns)->si as $si){$text='';foreach($si->xpath('.//*[local-name()="t"]') as $t)$text.=(string)$t;$strings[]=$text;}
   $rows=[];foreach($sheet->children($ns)->sheetData->row as $row){$values=[];foreach($row->c as $cell){if(count($cell->f)>0)throw new \RuntimeException('فرمول در فایل وارداتی مجاز نیست؛ ابتدا مقادیر را جایگزین فرمول کنید.');$ref=(string)$cell->attributes()['r'];if(!preg_match('/^([A-Z]+)[0-9]+$/',$ref,$match))throw new \RuntimeException('نشانی سلول نامعتبر است.');$index=0;foreach(str_split($match[1]) as $letter)$index=$index*26+ord($letter)-64;$index--;if($index>99)throw new \RuntimeException('حداکثر ۱۰۰ ستون مجاز است.');$type=(string)$cell->attributes()['t'];if($type==='s')$value=$strings[(int)$cell->v]??'';elseif($type==='inlineStr'){$value='';foreach($cell->is->xpath('.//*[local-name()="t"]') as $t)$value.=(string)$t;}else $value=(string)$cell->v;$values[$index]=$value;}
    if($values){$line=array_fill(0,max(array_keys($values))+1,'');foreach($values as $i=>$value)$line[$i]=$value;$rows[]=$line;}if(count($rows)>1001)throw new \RuntimeException('حداکثر ۱۰۰۰ ردیف داده مجاز است.');
   }return $rows;
  }finally{$zip->close();}
 }
 public static function create(array $rows): string {
  if(!class_exists('ZipArchive')) throw new \RuntimeException('برای خروجی اکسل افزونه PHP Zip لازم است.');
  $path=tempnam(sys_get_temp_dir(),'mahex-xlsx-'); $zip=new \ZipArchive();
  if($zip->open($path,\ZipArchive::OVERWRITE)!==true) { unlink($path); throw new \RuntimeException('ساخت اکسل انجام نشد.'); }
  $zip->addFromString('[Content_Types].xml','<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
  $zip->addFromString('_rels/.rels','<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
  $zip->addFromString('xl/workbook.xml','<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="صورتحساب ماهکس" sheetId="1" r:id="rId1"/></sheets></workbook>');
  $zip->addFromString('xl/_rels/workbook.xml.rels','<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
  $xml='<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0" rightToLeft="1"/></sheetViews><sheetData>';
  foreach($rows as $i=>$row) { $xml.='<row r="'.($i+1).'">'; foreach(array_values($row) as $j=>$cell) {
   $n=$j+1; $col=''; while($n>0){$n--; $col=chr(65+$n%26).$col;$n=intdiv($n,26);} $ref=$col.($i+1);
   $xml.='<c r="'.$ref.'"'.(is_int($cell)||is_float($cell)?'><v>'.(float)$cell.'</v>':' t="inlineStr"><is><t xml:space="preserve">'.htmlspecialchars(preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}]/u','',(string)$cell)??'',ENT_XML1|ENT_QUOTES,'UTF-8').'</t></is>').'</c>';
  } $xml.='</row>'; }
  $zip->addFromString('xl/worksheets/sheet1.xml',$xml.'</sheetData></worksheet>');$zip->close();return $path;
 }
}
