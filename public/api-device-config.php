<?php
/** Authenticated machine API; device tokens never appear in URLs or logs. */
require dirname(__DIR__).'/app/bootstrap.php';
require_once ROOT.'/app/device_config.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function dc_out(array $data,int $status=200):never{http_response_code($status);echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);exit;}
function dc_body():array {if((int)($_SERVER['CONTENT_LENGTH']??0)>8192)dc_out(['error'=>'Payload demasiado grande'],413);$str=file_get_contents('php://input',false,null,0,8193);if(strlen($str)>8192)dc_out(['error'=>'Payload demasiado grande'],413);$v=json_decode($str,true,10);if(!is_array($v))dc_out(['error'=>'JSON inválido'],400);return $v;}
function dc_device():array {$authorization=$_SERVER['HTTP_AUTHORIZATION']??$_SERVER['REDIRECT_HTTP_AUTHORIZATION']??'';if(!preg_match('/^Bearer ([a-f0-9]{64})$/D',$authorization,$m))dc_out(['error'=>'Autenticación requerida'],401);$d=dbone('SELECT id,tenant_id FROM devices WHERE device_token_hash=?',[hash('sha256',$m[1])]);if(!$d)dc_out(['error'=>'Dispositivo no autorizado'],401);return $d;}
try {
 $d=dc_device();$did=(int)$d['id'];$tid=(int)$d['tenant_id'];$op=(string)($_GET['op']??'');
 if($op==='desired'){
  if($_SERVER['REQUEST_METHOD']!=='GET')dc_out(['error'=>'Método no permitido'],405);
  $config=ds_config_for_device($did,$tid);
  $report=dbone('SELECT applied_revision,status,updated_at FROM device_config_reported WHERE tenant_id=? AND device_id=?',[$tid,$did]);
  dc_out(['schema'=>2,'revision'=>$config['revision'],'settings'=>$config['settings'],'sha256'=>hash('sha256',ds_config_json($config['settings'])),'reported_revision'=>(int)($report['applied_revision']??0),'reported_status'=>$report['status']??'pending','server_time'=>gmdate('c')]);
 }
 if($op==='report'){
  if($_SERVER['REQUEST_METHOD']!=='POST')dc_out(['error'=>'Método no permitido'],405);
  $p=dc_body();$rev=$p['revision']??null;$status=$p['status']??'';
  if(!is_int($rev)||$rev<0||!in_array($status,['applied','rejected'],true))dc_out(['error'=>'Estado o revisión inválidos'],400);
  $now=ds_config_for_device($did,$tid);
  if($rev>$now['revision'])dc_out(['error'=>'Revisión desconocida'],409);
  if($status==='applied' && $rev===$now['revision'] && !hash_equals(hash('sha256',ds_config_json($now['settings'])),(string)($p['sha256']??'')))dc_out(['error'=>'Hash inconsistente'],409);
  $actual=ds_config_validate((array)($p['settings']??[]));
  $error=substr((string)($p['error']??''),0,200);
  $old=dbone('SELECT applied_revision FROM device_config_reported WHERE tenant_id=? AND device_id=?',[$tid,$did]);
  if($old && $rev<(int)$old['applied_revision'])dc_out(['error'=>'Reporte antiguo'],409);
  execsql('INSERT INTO device_config_reported(device_id,tenant_id,applied_revision,status,config_json,error_detail,updated_at) VALUES(?,?,?,?,?,?,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE applied_revision=VALUES(applied_revision),status=VALUES(status),config_json=VALUES(config_json),error_detail=VALUES(error_detail),updated_at=VALUES(updated_at)',[$did,$tid,$rev,$status,ds_config_json($actual),$error]);
  execsql('UPDATE devices SET last_seen=UTC_TIMESTAMP() WHERE id=? AND tenant_id=?',[$did,$tid]);
  // Never accept tokens, URLs, PINs, local admin credentials or filesystem paths in reports.
  log_event('device_config_report',['device_id'=>$did,'revision'=>$rev,'status'=>$status]);
  dc_out(['ok'=>true,'revision'=>$rev]);
 }
 dc_out(['error'=>'Operación desconocida'],404);
}catch(RuntimeException $e){dc_out(['error'=>$e->getMessage()],400);}catch(Throwable $e){$incident=bin2hex(random_bytes(6));log_event('device_config_api_error',['incident'=>$incident,'type'=>get_class($e)]);dc_out(['error'=>'Error interno','incident'=>$incident],500);}
