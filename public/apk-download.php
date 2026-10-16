<?php
/** Streaming direct download, including Range/HEAD required by common device downloaders. */
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
require_once ROOT.'/app/apk_releases.php';
if(!in_array($_SERVER['REQUEST_METHOD']??'GET',['GET','HEAD'],true)){header('Allow: GET, HEAD');http_response_code(405);exit;}
$id=filter_var($_GET['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
if(!$id){http_response_code(404);exit('Versión no encontrada');}
try {
    $r=dbone("SELECT id,storage_key,sha256,file_size,original_filename FROM app_releases WHERE id=? AND status='published' AND platform IN ('android-tv','turnos-control','sports-control')",[(int)$id]);
    if(!$r){http_response_code(404);exit('Versión no disponible');}
    $path=ds_apk_storage_path((string)$r['storage_key']);
    if(!is_file($path)||!is_readable($path)||filesize($path)!==(int)$r['file_size'])throw new RuntimeException('No coincide el archivo publicado');
} catch(Throwable $e){http_response_code(503);log_event('apk_download_error',['release_id'=>(int)$id,'error'=>$e->getMessage()]);exit('APK temporalmente no disponible. Revise Sistema → Logs.');}
$size=(int)$r['file_size'];$start=0;$end=$size-1;$partial=false;
$range=(string)($_SERVER['HTTP_RANGE']??'');
if($range!=='') {
    if(!preg_match('/^bytes=(\d*)-(\d*)$/D',$range,$m) || ($m[1]==='' && $m[2]==='')) {
        header('Content-Range: bytes */'.$size);http_response_code(416);exit;
    }
    if($m[1]===''){$count=(int)$m[2];if($count<=0){header('Content-Range: bytes */'.$size);http_response_code(416);exit;}$start=max(0,$size-$count);}
    else{$start=(int)$m[1];$end=($m[2]==='')?$size-1:min($size-1,(int)$m[2]);}
    if($start>=$size||$start>$end){header('Content-Range: bytes */'.$size);http_response_code(416);exit;}
    $partial=true;
}
// Filename is admin-validated, but normalize again before use in headers.
$filename=preg_replace('/[^a-zA-Z0-9._-]/','_',basename((string)$r['original_filename']));
if(!is_string($filename)||!str_ends_with(strtolower($filename),'.apk'))$filename='DigitalSignage.apk';
while(ob_get_level()>0)ob_end_clean();
header('Content-Type: application/vnd.android.package-archive');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=120');
header('Accept-Ranges: bytes');
header('X-APK-SHA256: '.$r['sha256']);
if($partial){http_response_code(206);header('Content-Range: bytes '.$start.'-'.$end.'/'.$size);}
header('Content-Length: '.($end-$start+1));
if(($_SERVER['REQUEST_METHOD']??'GET')==='HEAD')exit;
if(!$partial){try{execsql('UPDATE app_releases SET downloads=downloads+1 WHERE id=?',[(int)$id]);}catch(Throwable $ignored){}}
if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
$fh=fopen($path,'rb');if(!$fh){http_response_code(503);exit;}
try{
    if($start>0)fseek($fh,$start);
    $remaining=$end-$start+1;
    while($remaining>0&&!connection_aborted()){
        $block=fread($fh,min(1048576,$remaining));if($block===false||$block==='')break;
        echo $block;$remaining-=strlen($block);flush();
    }
}finally{fclose($fh);}
