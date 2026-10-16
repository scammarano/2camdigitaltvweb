<?php
/**
 * 2Cam Digital Signage: an authenticated, single-asset wizard upload endpoint.
 * Each request is atomic; the browser tracks multiple independent uploads and errors.
 * All media geometry is checked again here, never trusted from JavaScript.
 */
declare(strict_types=1);
require __DIR__.'/_init.php';
require_once ROOT.'/app/domain.php';
require_once ROOT.'/app/media_geometry.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function ds_wizard_json(int $status,array $payload):never {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
try {
    if($_SERVER['REQUEST_METHOD']!=='POST')ds_wizard_json(405,['ok'=>false,'message'=>'Método no permitido']);
    check_csrf();
    require_permission('media.edit');
    $target=post_tenant();
    $orientation=(string)($_POST['orientation']??'');
    $layout=(string)($_POST['layout_type']??'');
    $geometry=ds_media_geometry($orientation,$layout);
    if(!$geometry)throw new RuntimeException('Selecciona la pantalla y una de las cinco zonas de presentación.');
    $original=trim((string)($_POST['name']??''));
    if($original===''||strlen($original)>190)
        throw new RuntimeException('El nombre del medio debe tener entre 1 y 190 caracteres.');
    $item=$_FILES['media']??null;
    if(!is_array($item)||is_array($item['name']??null))
        throw new RuntimeException('No se recibió un archivo individual. Intenta nuevamente.');
    $err=(int)($item['error']??UPLOAD_ERR_NO_FILE);
    if($err!==UPLOAD_ERR_OK){
        $messages=[UPLOAD_ERR_INI_SIZE=>'El archivo supera el límite de tamaño del servidor.',
          UPLOAD_ERR_FORM_SIZE=>'El archivo supera el tamaño permitido.',
          UPLOAD_ERR_PARTIAL=>'La carga del archivo se interrumpió.',
          UPLOAD_ERR_NO_FILE=>'No se recibió ningún archivo.',
          UPLOAD_ERR_NO_TMP_DIR=>'El directorio temporal de PHP no está configurado.',
          UPLOAD_ERR_CANT_WRITE=>'PHP no pudo escribir el archivo temporal.',
          UPLOAD_ERR_EXTENSION=>'Una extensión PHP bloqueó la carga.'];
        throw new RuntimeException(($messages[$err]??'Error al recibir el archivo').' (código '.$err.').');
    }
    $temp=(string)($item['tmp_name']??'');
    if($temp===''||!is_uploaded_file($temp))throw new RuntimeException('Archivo temporal no válido.');
    $size=(int)($item['size']??0);
    if($size<1||$size>40*1048576)throw new RuntimeException('El medio debe pesar entre 1 byte y 40 MB.');
    $mime=(string)(new finfo(FILEINFO_MIME_TYPE))->file($temp);
    $allowed=['image/jpeg','image/png','image/webp','image/gif','video/mp4','video/webm','video/quicktime'];
    if(!in_array($mime,$allowed,true))throw new RuntimeException('Formato de archivo no admitido: '.$mime);
    $w=null;$h=null;
    if(str_starts_with($mime,'image/')){
        $dimensions=@getimagesize($temp);
        if(!$dimensions)throw new RuntimeException('El archivo no contiene una imagen válida.');
        $w=(int)$dimensions[0];$h=(int)$dimensions[1];
        if(!ds_media_geometry_check($w,$h,$geometry))
            throw new RuntimeException(ds_media_geometry_error($original,$w,$h,$geometry));
    }
    $dir=ROOT.'/storage/media/'.$target;
    if(!is_dir($dir)&&!mkdir($dir,0700,true)&&!is_dir($dir))
        throw new RuntimeException('No se pudo crear la carpeta de medios de la empresa.');
    $stored=bin2hex(random_bytes(20));
    $dest=$dir.'/'.$stored;
    if(file_exists($dest)||is_link($dest))throw new RuntimeException('Conflicto al guardar el archivo. Vuelve a intentar.');
    $moved=false;
    db()->beginTransaction();
    try {
        ds_formats_lock_geometry($orientation,$layout,$w,$h);
        if(!move_uploaded_file($temp,$dest))throw new RuntimeException('No fue posible guardar este archivo.');
        $moved=true;
        @chmod($dest,0600);
        execsql('INSERT INTO media(tenant_id,name,filename,mime,size_bytes,sha256,orientation,layout_type,width_px,height_px) VALUES(?,?,?,?,?,?,?,?,?,?)',
          [$target,$original,$stored,$mime,$size,hash_file('sha256',$dest),$orientation,$layout,$w,$h]);
        $mediaId=(int)db()->lastInsertId();
        db()->commit();
    } catch(Throwable $e) {
        if(db()->inTransaction())db()->rollBack();
        if($moved&&is_file($dest))@unlink($dest);
        throw $e;
    }
    log_event('media_wizard_uploaded',['id'=>$mediaId,'company'=>$target,'screen_orientation'=>$orientation,'layout'=>$layout]);
    ds_wizard_json(200,['ok'=>true,'id'=>$mediaId,'name'=>$original,'message'=>'Medio guardado correctamente']);
} catch(Throwable $e) {
    $message=$e instanceof PDOException?fault($e,'media_wizard_upload'):$e->getMessage();
    if(!($e instanceof PDOException))log_event('media_wizard_rejected',['reason'=>substr($message,0,300)]);
    ds_wizard_json($e instanceof PDOException?500:422,['ok'=>false,'message'=>$message]);
}
