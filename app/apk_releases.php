<?php
/** APK release catalogue: one global catalogue, superadmin-owned, served without device credentials. */
declare(strict_types=1);
require_once __DIR__.'/bootstrap.php';
require_once __DIR__.'/apk_manifest.php';

const DS_APK_MAX_BYTES = 125829120; // 120 MiB; actual PHP / hosting limits can be lower.
function ds_apk_platforms():array {
    return ['android-tv'=>'2Cam Digital Signage','turnos-control'=>'2Cam Turnos','sports-control'=>'2Cam Sports'];
}
function ds_apk_platform_prefix(string $platform):string {
    return match ($platform) {
        'android-tv'=>'DS-TV',
        'turnos-control'=>'DS-TURNOS',
        'sports-control'=>'DS-SPORTS',
        default=>throw new RuntimeException('Aplicación APK desconocida'),
    };
}
function ds_apk_channels():array {return ['stable'=>'Estable','beta'=>'Beta','debug'=>'Pruebas (debug)'];}
function ds_apk_statuses():array {return ['draft'=>'Borrador','published'=>'Publicada','retired'=>'Retirada'];}
function ds_apk_storage_path(string $key):string {
    if(preg_match('/^bundled:([A-Za-z0-9._-]+\.apk)$/D',$key,$m))$relative='app/release-assets/'.$m[1];
    elseif(preg_match('/^upload:([a-f0-9]{40}\.apk)$/D',$key,$m))$relative='storage/apk-releases/'.$m[1];
    else throw new RuntimeException('Referencia de archivo APK inválida');
    $absolute=ROOT.'/'.$relative;
    // Reject symbolic links in the path and on the final file. Do not allow traversal outside ROOT.
    $part=ROOT;
    foreach(explode('/',$relative) as $segment) {$part.='/'.$segment;if(is_link($part))throw new RuntimeException('Ruta de archivo no confiable');}
    return $absolute;
}
function ds_apk_human_size(int $bytes):string {
    return number_format($bytes/1048576,($bytes<10485760?2:1),',','.').' MB';
}
function ds_apk_upload_error(int $code):string {
    return [UPLOAD_ERR_INI_SIZE=>'Archivo superior a upload_max_filesize del servidor',UPLOAD_ERR_FORM_SIZE=>'Archivo superior al límite del formulario',UPLOAD_ERR_PARTIAL=>'Carga interrumpida',UPLOAD_ERR_NO_FILE=>'No seleccionaste ningún archivo APK',UPLOAD_ERR_NO_TMP_DIR=>'No hay directorio temporal de PHP',UPLOAD_ERR_CANT_WRITE=>'PHP no puede escribir el archivo temporal',UPLOAD_ERR_EXTENSION=>'La carga fue bloqueada por una extensión PHP'][$code]??'Error de carga PHP '.$code;
}
function ds_apk_check_package(string $path):void {
    if(!is_file($path) || filesize($path)<1024 || filesize($path)>DS_APK_MAX_BYTES)
        throw new RuntimeException('El APK no existe, es demasiado pequeño o supera 120 MB');
    if(!class_exists(ZipArchive::class))throw new RuntimeException('El servidor no tiene ZipArchive');
    $zip=new ZipArchive();if($zip->open($path)!==true)throw new RuntimeException('El archivo no es un APK/ZIP válido');
    try {
        if($zip->locateName('AndroidManifest.xml')===false || $zip->locateName('classes.dex')===false)
            throw new RuntimeException('El archivo no contiene AndroidManifest.xml y classes.dex');
        // Check basic ZIP integrity. This does NOT verify the Android signing certificate.
        foreach(['AndroidManifest.xml','classes.dex'] as $name) {
            $stat=$zip->statName($name);
            if(!$stat || ($stat['size']??0)<=0)throw new RuntimeException('El APK contiene entradas principales vacías');
        }
    } finally {$zip->close();}
}
function ds_apk_save_upload(array $upload,string $expectedHash=''):array {
    $error=(int)($upload['error']??UPLOAD_ERR_NO_FILE);
    if($error!==UPLOAD_ERR_OK)throw new RuntimeException(ds_apk_upload_error($error));
    $name=(string)($upload['name']??'');
    if(!preg_match('/\.apk$/iD',$name))throw new RuntimeException('Solo se permiten archivos .apk');
    $tmp=(string)($upload['tmp_name']??'');
    if(!is_uploaded_file($tmp))throw new RuntimeException('Archivo temporal no autorizado');
    ds_apk_check_package($tmp);
    // Read actual metadata from the APK: user does NOT type these values.
    $metadata=ds_apk_read_manifest($tmp);
    $hash=hash_file('sha256',$tmp);
    if($hash===false)throw new RuntimeException('PHP no pudo calcular la huella SHA-256');
    // Optional only for automated/pipeline uploads; never required for a human upload.
    if($expectedHash!=='' && (!preg_match('/^[a-f0-9]{64}$/D',$expectedHash) || !hash_equals($expectedHash,$hash)))
        throw new RuntimeException('El SHA-256 no coincide con el valor esperado');
    $dir=ROOT.'/storage/apk-releases';if(!is_dir($dir)&&!mkdir($dir,0750,true)&&!is_dir($dir))throw new RuntimeException('No se pudo crear directorio de APK');
    $file=bin2hex(random_bytes(20)).'.apk';$dest=$dir.'/'.$file;
    if(!move_uploaded_file($tmp,$dest))throw new RuntimeException('No se pudo guardar el APK en almacenamiento privado');
    @chmod($dest,0640);
    return ['key'=>'upload:'.$file,'size'=>(int)filesize($dest),'sha256'=>$hash,'metadata'=>$metadata];
}
function ds_apk_upload_max_text():string {
    $toBytes=static function(string $v):int {
        $v=trim($v);$n=(float)$v;$last=strtolower(substr($v,-1));if($last==='g')$n*=1073741824;elseif($last==='m')$n*=1048576;elseif($last==='k')$n*=1024;
        return (int)$n;
    };
    $limits=array_filter([$toBytes((string)ini_get('upload_max_filesize')),$toBytes((string)ini_get('post_max_size')),DS_APK_MAX_BYTES],static fn($x)=>$x>0);
    return ds_apk_human_size((int)min($limits));
}

/** Static links are intentionally public: they are only created for approved releases.
 * File names are derived from internal row IDs, not user-controlled paths.
 * This avoids older Fire TV Downloader browsers failing on PHP query-string downloads. */
function ds_apk_static_basename(array $release):string {
    $id=(int)($release['id']??0);
    $version=(string)($release['version_name']??'');
    if($id<1 || !preg_match('/^[A-Za-z0-9.-]{1,40}$/D',$version))
        throw new RuntimeException('Datos de publicación APK incorrectos');
    return ds_apk_platform_prefix((string)($release['platform']??'android-tv')).'-'.$id.'-v'.$version.'.apk';
}
function ds_apk_static_path(array $release):string {
    return ROOT.'/public/downloads/'.ds_apk_static_basename($release);
}
function ds_apk_public_url(array $release):string {
    return url('downloads/'.ds_apk_static_basename($release));
}
function ds_apk_copy_verified(string $source,string $destination,string $sha,int $size):void {
    if(!is_file($source)||is_link($source)||!is_readable($source) || filesize($source)!==$size ||
        !hash_equals($sha,hash_file('sha256',$source)))
        throw new RuntimeException('APK de origen ausente o SHA-256 inválido');
    $folder=dirname($destination);
    if(is_link($folder))throw new RuntimeException('Ruta pública insegura');
    if(!is_dir($folder)&&!mkdir($folder,0755,true)&&!is_dir($folder))
        throw new RuntimeException('No se puede crear la carpeta pública de APK');
    if(is_link($destination))throw new RuntimeException('Destino público inseguro');
    $temp=$destination.'.part-'.bin2hex(random_bytes(6));
    try {
        if(!copy($source,$temp))throw new RuntimeException('No se pudo copiar la APK al directorio público');
        if(filesize($temp)!==$size || !hash_equals($sha,hash_file('sha256',$temp)))
            throw new RuntimeException('La copia pública falló la comprobación SHA-256');
        chmod($temp,0644);
        if(!rename($temp,$destination))throw new RuntimeException('No se pudo publicar la APK');
    }finally{if(is_file($temp))unlink($temp);}
}
function ds_apk_publish_static(array $release):void {
    $src=ds_apk_storage_path((string)$release['storage_key']);
    ds_apk_copy_verified($src,ds_apk_static_path($release),(string)$release['sha256'],(int)$release['file_size']);
}
function ds_apk_remove_static(array $release):void {
    $target=ds_apk_static_path($release);
    if(is_link($target))throw new RuntimeException('Ruta pública sospechosa');
    if(is_file($target)&&!unlink($target))throw new RuntimeException('No se pudo retirar el archivo público');
}
/** Short download address points at the newest published build.
 * Rebuild on publish/retire; never copy a draft APK to this alias. */
function ds_apk_refresh_shortcut(PDO $pdo):void {
    $query=$pdo->query("SELECT * FROM app_releases WHERE platform='android-tv' AND status='published' ORDER BY version_code DESC,id DESC LIMIT 1");
    $latest=$query->fetch(PDO::FETCH_ASSOC);
    $alias=ROOT.'/public/tv.apk';
    if(is_link($alias))throw new RuntimeException('Ruta tv.apk insegura');
    if(!$latest){if(is_file($alias)&&!unlink($alias))throw new RuntimeException('No se pudo retirar tv.apk');return;}
    $src=ds_apk_static_path($latest);
    if(!is_file($src))ds_apk_publish_static($latest);
    ds_apk_copy_verified($src,$alias,(string)$latest['sha256'],(int)$latest['file_size']);
}
