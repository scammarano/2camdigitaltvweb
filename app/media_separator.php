<?php
declare(strict_types=1);
require_once __DIR__.'/media_geometry.php';
/** A real opaque PNG: usable offline and by all existing players, no GD dependency. */
function ds_white_png(int $width,int $height):string {
    if($width<1||$height<1||$width>1920||$height>1920)throw new RuntimeException('Dimensiones de separador inválidas');
    $chunk=static fn(string $type,string $data):string=>pack('N',strlen($data)).$type.$data.pack('N',crc32($type.$data));
    return "\x89PNG\r\n\x1a\n".$chunk('IHDR',pack('NNCCCCC',$width,$height,8,0,0,0,0)).$chunk('IDAT',gzcompress(str_repeat("\0".str_repeat("\xff",$width),$height),9)).$chunk('IEND','');
}
function ds_media_white_separator(int $tid,string $orientation,string $layout):int {
    require_permission('media.edit');
    if(!is_super()&&$tid!==tenant())throw new RuntimeException('Empresa inválida');
    if(!dbone('SELECT id FROM tenants WHERE id=?',[$tid]))throw new RuntimeException('Empresa inválida');
    $path=null;db()->beginTransaction();
    try{
        ds_formats_current(true);$g=ds_media_geometry($orientation,$layout);if(!$g)throw new RuntimeException('Selecciona pantalla horizontal o vertical y un formato válido');
        $png=ds_white_png($g['width'],$g['height']);$hash=hash('sha256',$png);
        $existing=dbone('SELECT id,filename FROM media WHERE tenant_id=? AND orientation=? AND layout_type=? AND mime=? AND sha256=? LIMIT 1',[$tid,$orientation,$layout,'image/png',$hash]);
        if($existing&&is_file(ROOT.'/storage/media/'.$tid.'/'.$existing['filename'])&&hash_file('sha256',ROOT.'/storage/media/'.$tid.'/'.$existing['filename'])===$hash){db()->commit();return (int)$existing['id'];}
        $dir=ROOT.'/storage/media/'.$tid;if(!is_dir($dir)&&!mkdir($dir,0700,true)&&!is_dir($dir))throw new RuntimeException('No se pudo preparar el almacenamiento');
        $filename=bin2hex(random_bytes(20));$path=$dir.'/'.$filename;
        if(file_put_contents($path,$png,LOCK_EX)!==strlen($png))throw new RuntimeException('No se pudo guardar el separador');
        execsql('INSERT INTO media(tenant_id,name,filename,mime,size_bytes,sha256,orientation,layout_type,width_px,height_px) VALUES(?,?,?,?,?,?,?,?,?,?)',[$tid,'Separador blanco · '.$g['width'].' × '.$g['height'].' px',$filename,'image/png',strlen($png),$hash,$orientation,$layout,$g['width'],$g['height']]);
        $id=(int)db()->lastInsertId();db()->commit();return $id;
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();if($path&&is_file($path))unlink($path);throw $e;}
}
