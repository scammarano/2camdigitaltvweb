<?php
/** Published Android APK catalog, per-device token authenticated. */
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
require_once ROOT.'/app/apk_releases.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
function upd_out(array $data,int $status=200):never {
 http_response_code($status);echo json_encode($data,JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE|JSON_THROW_ON_ERROR);exit;
}
try {
 if($_SERVER['REQUEST_METHOD']!=='GET')upd_out(['error'=>'Método no permitido'],405);
 $token=device_bearer_token();if($token===null)upd_out(['error'=>'Autenticación requerida'],401);
 $d=dbone('SELECT id,tenant_id FROM devices WHERE device_token_hash=?',[hash('sha256',$token)]);
 if(!$d)upd_out(['error'=>'Equipo no autorizado'],401);
 $channel=(string)($_GET['channel']??'stable');
 $options=['stable'=>['stable'],'beta'=>['stable','beta'],'debug'=>['stable','beta','debug']]; // beta retained only for legacy clients.
 if(!isset($options[$channel]))upd_out(['error'=>'Canal inválido'],400);
 $sdk=(int)($_GET['sdk']??19);if($sdk<19 || $sdk>999)upd_out(['error'=>'SDK inválido'],400);
 $rows=dball("SELECT id,version_name,version_code,channel,sha256,file_size,min_sdk,release_notes,storage_key,original_filename FROM app_releases WHERE platform='android-tv' AND status='published' AND min_sdk<=? ORDER BY version_code DESC,id DESC LIMIT 50",[$sdk]);
 $latest=null;
 foreach($rows as $row){if(!in_array($row['channel'],$options[$channel],true))continue;
   $src=ds_apk_static_path($row);
   if(!is_file($src) || is_link($src) || filesize($src)!==(int)$row['file_size'])continue;
   $latest=$row;break;
 }
 if($latest===null)upd_out(['available'=>false,'server_time'=>gmdate('c')]);
 upd_out(['available'=>true,'package'=>'com.digitalsignage.tv','version'=>(string)$latest['version_name'],
   'version_code'=>(int)$latest['version_code'],'min_sdk'=>(int)$latest['min_sdk'],
   'size'=>(int)$latest['file_size'],'sha256'=>(string)$latest['sha256'],
   'url'=>ds_apk_public_url($latest),'channel'=>(string)$latest['channel'],
   'notes'=>substr((string)$latest['release_notes'],0,2500),'server_time'=>gmdate('c')]);
}catch(Throwable $e){
 $incident=bin2hex(random_bytes(6));log_event('apk_update_query_error',['incident'=>$incident,'type'=>get_class($e)]);
 upd_out(['error'=>'Servicio temporalmente no disponible','incident'=>$incident],500);
}
