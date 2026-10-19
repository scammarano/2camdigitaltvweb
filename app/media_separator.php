<?php
declare(strict_types=1);
require_once __DIR__.'/media_geometry.php';
/** Global opaque PNG assets: one record per color and current format, no company copies. */
function ds_color_png(int $width,int $height,string $color):string {
 if($width<1||$height<1||$width>1920||$height>1920||!preg_match('/^#[0-9a-fA-F]{6}$/D',$color))throw new RuntimeException('Color o dimensiones inválidos');
 $chunk=static fn(string $type,string $data):string=>pack('N',strlen($data)).$type.$data.pack('N',crc32($type.$data));
 $pixel=hex2bin(substr($color,1));
 return "\x89PNG\r\n\x1a\n".$chunk('IHDR',pack('NNCCCCC',$width,$height,8,2,0,0,0)).$chunk('IDAT',gzcompress(str_repeat("\0".str_repeat($pixel,$width),$height),9)).$chunk('IEND','');
}
function ds_white_png(int $width,int $height):string{return ds_color_png($width,$height,'#ffffff');}
function ds_global_separator(string $orientation,string $layout,string $color='#ffffff'):int {
 $color=strtolower($color);$g=ds_media_geometry($orientation,$layout);if(!$g)throw new RuntimeException('Selecciona orientación y formato válidos');
 $png=ds_color_png($g['width'],$g['height'],$color);$hash=hash('sha256',$png);$key=$orientation.':'.$layout.':'.$g['width'].'x'.$g['height'].':'.$color;
 $dir=ROOT.'/storage/media/0';if(!is_dir($dir)&&!mkdir($dir,0700,true)&&!is_dir($dir))throw new RuntimeException('No se pudo preparar el almacenamiento global');
 $filename=substr($hash,0,40);$path=$dir.'/'.$filename;
 if(!is_file($path)||hash_file('sha256',$path)!==$hash){if(file_put_contents($path,$png,LOCK_EX)!==strlen($png))throw new RuntimeException('No se pudo guardar el separador global');chmod($path,0600);}
 $name='Separador '.match($color){'#ffffff'=>'blanco','#000000'=>'negro',default=>$color}.' · '.$g['width'].' × '.$g['height'].' px';
 execsql('INSERT INTO media(tenant_id,name,filename,mime,size_bytes,sha256,orientation,layout_type,width_px,height_px,is_shared,separator_key) VALUES(NULL,?,?,?,?,?,?,?,?,?,1,?) ON DUPLICATE KEY UPDATE filename=VALUES(filename),sha256=VALUES(sha256),size_bytes=VALUES(size_bytes)',[$name,$filename,'image/png',strlen($png),$hash,$orientation,$layout,$g['width'],$g['height'],$key]);
 return (int)dbone('SELECT id FROM media WHERE separator_key=?',[$key])['id'];
}
function ds_seed_separators():void {
 foreach(['landscape','portrait'] as $o)foreach(['fullscreen','bottom','side','half_horizontal','half_vertical'] as $l)foreach(['#ffffff','#000000'] as $c)ds_global_separator($o,$l,$c);
}
function ds_media_white_separator(int $tid,string $orientation,string $layout):int {
 require_permission('media.edit');if(!is_super()&&$tid!==tenant())throw new RuntimeException('Empresa inválida');
 return ds_global_separator($orientation,$layout);
}
