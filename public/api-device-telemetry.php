<?php
/** Device diagnostics and fast sync request polling, token authenticated. */
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
function dt_out(array $payload,int $status=200):never {
 http_response_code($status);
 echo json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE|JSON_THROW_ON_ERROR);
 exit;
}
function dt_body(int $limit=65536):array {
 if((int)($_SERVER['CONTENT_LENGTH']??0)>$limit)dt_out(['error'=>'Carga demasiado grande'],413);
 $str=file_get_contents('php://input',false,null,0,$limit+1);
 if(strlen($str)>$limit)dt_out(['error'=>'Carga demasiado grande'],413);
 $data=json_decode($str,true,6);
 if(!is_array($data))dt_out(['error'=>'JSON inválido'],400);
 return $data;
}
function dt_scrub(string $value,int $max):string {
 $value=preg_replace('/Bearer\s+[A-Za-z0-9._-]+/i','Bearer [OCULTO]',$value);
 $value=preg_replace('/\b[a-f0-9]{64}\b/i','[OCULTO]',$value);
 $value=preg_replace('/(?:token|secret|password|pin)[=:]\s*[^\s;,]+/i','credencial=[OCULTO]',$value);
 return substr($value,0,$max);
}
try {
 $bearer=device_bearer_token();
 if($bearer===null)dt_out(['error'=>'Token requerido'],401);
 $d=dbone('SELECT id,tenant_id FROM devices WHERE device_token_hash=?',[hash('sha256',$bearer)]);
 if(!$d)dt_out(['error'=>'Dispositivo no autorizado'],401);
 $device=(int)$d['id'];$company=(int)$d['tenant_id'];$op=(string)($_GET['op']??'');
 if($op==='control') {
  if($_SERVER['REQUEST_METHOD']!=='GET')dt_out(['error'=>'Método no permitido'],405);
  $r=dbone('SELECT requested_version,applied_version FROM device_sync_state WHERE tenant_id=? AND device_id=?',[$company,$device]);
  dt_out(['requested_version'=>(int)($r['requested_version']??0),'applied_version'=>(int)($r['applied_version']??0),'server_time'=>gmdate('c')]);
 }
 if($op==='ack') {
  if($_SERVER['REQUEST_METHOD']!=='POST')dt_out(['error'=>'Método no permitido'],405);
  $p=dt_body(4096);$rev=$p['revision']??null;
  if(!is_int($rev)||$rev<0)dt_out(['error'=>'Revisión inválida'],400);
  $row=dbone('SELECT requested_version FROM device_sync_state WHERE tenant_id=? AND device_id=?',[$company,$device]);
  if(!$row || $rev>(int)$row['requested_version'])dt_out(['error'=>'Solicitud desconocida'],409);
  execsql('UPDATE device_sync_state SET applied_version=GREATEST(applied_version,?),applied_at=UTC_TIMESTAMP() WHERE tenant_id=? AND device_id=? AND requested_version>=?',[$rev,$company,$device,$rev]);
  execsql('UPDATE devices SET last_seen=UTC_TIMESTAMP() WHERE id=? AND tenant_id=?',[$device,$company]);
  dt_out(['ok'=>true,'applied_version'=>$rev]);
 }
 if($op==='logs') {
  if($_SERVER['REQUEST_METHOD']!=='POST')dt_out(['error'=>'Método no permitido'],405);
  $p=dt_body();$events=$p['events']??null;
  if(!is_array($events)||count($events)>25)dt_out(['error'=>'Lote de logs inválido'],400);
  $accepted=[];$stmt=db()->prepare('INSERT IGNORE INTO device_diag_events(tenant_id,device_id,event_id,level,area,message,details,event_at,received_at) VALUES(?,?,?,?,?,?,?,?,UTC_TIMESTAMP())');
  foreach($events as $e){
   if(!is_array($e))continue;
   $id=(string)($e['id']??'');if(!preg_match('/^[a-f0-9]{32}$/D',$id))continue;
   $level=(string)($e['level']??'info');if(!in_array($level,['debug','info','warn','error','fatal'],true))$level='info';
   $area=dt_scrub((string)($e['area']??'android'),40);
   $msg=dt_scrub((string)($e['message']??''),360);
   $details=dt_scrub((string)($e['details']??''),1500);
   $when=$e['at']??null;
   $epoch=is_numeric($when)?(int)floor(((float)$when)/1000):time();
   if($epoch<strtotime('-30 days')||$epoch>time()+300)$epoch=time();
   $stmt->execute([$company,$device,$id,$level,$area,$msg,$details,gmdate("Y-m-d H:i:s",$epoch)]);
   $accepted[]=$id;
  }
  execsql('UPDATE devices SET last_seen=UTC_TIMESTAMP() WHERE id=? AND tenant_id=?',[$device,$company]);
  dt_out(['ok'=>true,'accepted'=>$accepted]);
 }
 dt_out(['error'=>'Operación desconocida'],404);
}catch(Throwable $e){
 $incident=strtoupper(bin2hex(random_bytes(6)));
 log_event('device_telemetry_error',['incident'=>$incident,'class'=>get_class($e),'message'=>dt_scrub($e->getMessage(),200)]);
 dt_out(['error'=>'Error interno','incident'=>$incident],500);
}
