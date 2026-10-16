<?php
/** Native Android + browser public device API, authorization via per-device bearer token. */
require dirname(__DIR__).'/app/bootstrap.php';
require_once ROOT.'/app/domain.php';
require_once ROOT.'/app/playback.php';
require_once ROOT.'/app/mode_runtime.php';
require_once ROOT.'/app/device_config.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
function api_out(array $data,int $code=200):never {http_response_code($code);echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE|JSON_THROW_ON_ERROR);exit;}
function req_payload():array {$raw=file_get_contents('php://input');if(strlen($raw)>16384)api_out(['error'=>'Solicitud demasiado grande'],413);$p=json_decode($raw?:'{}',true);return is_array($p)?$p:[];}
function device_context():array {
  $token=device_bearer_token();if($token===null)api_out(['error'=>'Token de dispositivo requerido'],401);
  $d=dbone('SELECT * FROM devices WHERE device_token_hash=?',[hash('sha256',$token)]);
  if(!$d)api_out(['error'=>'Dispositivo desvinculado o no autorizado'],401);
  return $d;
}
function ip_rate_check(string $scope,int $max,int $period):void {
  $dir=ROOT.'/storage/rate';if(!is_dir($dir))mkdir($dir,0700,true);
  $key=hash('sha256',$scope.'|'.($_SERVER['REMOTE_ADDR']??'unknown'));
  $file=$dir.'/'.$key.'.json';$now=time();
  $f=fopen($file,'c+');if(!$f)api_out(['error'=>'Servicio de emparejamiento temporalmente no disponible'],503);
  try {flock($f,LOCK_EX);$events=json_decode(stream_get_contents($f),true);$events=is_array($events)?array_values(array_filter($events,fn($t)=>is_int($t)&&$t>$now-$period)):[];
    if(count($events)>=$max)api_out(['error'=>'Demasiados intentos. Espera antes de reintentar.'],429);
    $events[]=$now;rewind($f);ftruncate($f,0);fwrite($f,json_encode($events));fflush($f);
  }finally{flock($f,LOCK_UN);fclose($f);}
}
try {
  $op=(string)($_GET['op']??'');
  if($op==='pair'){
    if($_SERVER['REQUEST_METHOD']!=='POST')api_out(['error'=>'Método inválido'],405);
    ip_rate_check('pair',12,600);
    $code=(string)(req_payload()['code']??'');
    if(!preg_match('/^[0-9]{6}$/D',$code))api_out(['error'=>'Código inválido'],400);
    $hash=hash_hmac('sha256',$code,cfg()['app_key']);
    $d=dbone('SELECT id,tenant_id FROM devices WHERE pairing_code_hash=? AND pairing_expires>UTC_TIMESTAMP()',[$hash]);
    if(!$d)api_out(['error'=>'Código expirado o no válido'],401);
    $token=bin2hex(random_bytes(32));
    $count=execsql('UPDATE devices SET pairing_code_hash=NULL,pairing_expires=NULL,device_token_hash=?,last_seen=UTC_TIMESTAMP() WHERE id=? AND tenant_id=? AND pairing_code_hash=?',[hash('sha256',$token),(int)$d['id'],(int)$d['tenant_id'],$hash]);
    if($count!==1)api_out(['error'=>'Código utilizado por otra pantalla'],409);
    log_event('device_paired',['device_id'=>(int)$d['id'],'tenant_id'=>(int)$d['tenant_id']]);
    api_out(['token'=>$token,'device_id'=>(int)$d['id']]);
  }
  $d=device_context();$tid=(int)$d['tenant_id'];$did=(int)$d['id'];
  if($op==='heartbeat'){
    if($_SERVER['REQUEST_METHOD']!=='POST')api_out(['error'=>'Método inválido'],405);
    $p=req_payload();execsql('UPDATE devices SET last_seen=UTC_TIMESTAMP() WHERE id=? AND tenant_id=?',[$did,$tid]);
    $app=substr((string)($p['app_version']??''),0,32);$mode=(int)($p['active_mode']??0);$cache=max(0,min(100000,(int)($p['cached_media']??0)));
    $free=isset($p['free_storage_bytes'])?max(0,(int)$p['free_storage_bytes']):null;
    $last=substr((string)($p['last_error']??''),0,250);
    execsql('INSERT INTO device_health(device_id,tenant_id,app_version,active_mode,cached_media,free_storage_bytes,last_error,last_report) VALUES(?,?,?,?,?,?,?,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE app_version=VALUES(app_version),active_mode=VALUES(active_mode),cached_media=VALUES(cached_media),free_storage_bytes=VALUES(free_storage_bytes),last_error=VALUES(last_error),last_report=VALUES(last_report)',[$did,$tid,$app,$mode,$cache,$free,$last]);
    api_out(['ok'=>true,'server_time'=>gmdate('c')]);
  }
  if($op==='live'){
    if($_SERVER['REQUEST_METHOD']!=='GET')api_out(['error'=>'Método inválido'],405);
    api_out(['device_id'=>$did,'live'=>live_panel($tid,$did)]);
  }
  if($op==='manifest'){
    if($_SERVER['REQUEST_METHOD']!=='GET')api_out(['error'=>'Método inválido'],405);
    $zone=(string)(cfg()['timezone']??'UTC');
    if(!empty($d['site_id'])){$site=dbone('SELECT timezone FROM sites WHERE id=? AND tenant_id=?',[(int)$d['site_id'],$tid]);if(!empty($site['timezone']))$zone=$site['timezone'];}
    $now=new DateTimeImmutable('now',playback_site_timezone($zone));$opts=runtime_opts($tid,$did);
    $modeBlocks=mode_blocks_for_device($tid,$did);
    $all=[];$active=null;
    foreach(dball('SELECT * FROM schedules WHERE device_id=? AND tenant_id=? ORDER BY priority DESC,id DESC',[$did,$tid]) as $sch){
      $seq=dball('SELECT playlist_id FROM schedule_items WHERE tenant_id=? AND schedule_id=? ORDER BY position,id',[$tid,(int)$sch['id']]);
      if(!$seq)$seq=[['playlist_id'=>$sch['playlist_id']]];
      $items=[];foreach($seq as $part)$items=array_merge($items,expanded($tid,(int)$part['playlist_id']));
      foreach($items as &$item)$item['url']=url('device-media.php?id='.$item['media_id']);unset($item);
      $block=['id'=>(int)$sch['id'],'days_mask'=>(int)$sch['days_mask'],'start'=>$sch['start_time'],'end'=>$sch['end_time'],
       'start_time'=>$sch['start_time'],'end_time'=>$sch['end_time'],'valid_from'=>$sch['valid_from'],'valid_until'=>$sch['valid_until'],
       'priority'=>(int)$sch['priority'],'items'=>$items];
      $all[]=$block;
      if($active===null&&playback_schedule_matches($sch,$now))$active=['source'=>'schedule','schedule_id'=>(int)$sch['id'],'items'=>$items];
    }
    $fallback=null;
    if(!empty($d['active_playlist_id'])){
      $list=dbone('SELECT id,name,orientation,layout_type FROM playlists WHERE id=? AND tenant_id=?',[(int)$d['active_playlist_id'],$tid]);
      if($list&&($list['orientation']==='any'||$list['orientation']===$d['orientation'])){
        $entries=expanded($tid,(int)$list['id']);foreach($entries as &$item)$item['url']=url('device-media.php?id='.$item['media_id']);unset($item);
        $fallback=['playlist_id'=>(int)$list['id'],'playlist_name'=>$list['name'],'orientation'=>$list['orientation'],'layout'=>$list['layout_type'],'items'=>$entries];
      }
    }
    $selected=$active;
    if($selected===null||count($selected['items'])===0){if($fallback!==null)$selected=['source'=>'default','playlist_id'=>$fallback['playlist_id'],'playlist_name'=>$fallback['playlist_name'],'items'=>$fallback['items']];}
    $selected??=['source'=>'none','items'=>[]];
    $wanted=ds_config_for_device($did,$tid)['settings'];$ids=[];
    $reported=dbone('SELECT config_json FROM device_config_reported WHERE device_id=? AND tenant_id=?',[$did,$tid]);
    $actual=$reported?json_decode($reported['config_json'],true):[];
    foreach([$wanted,is_array($actual)?$actual:[]] as $config)foreach($config as $key=>$id)if(str_contains($key,'playlist_')&&is_int($id)&&$id>0)$ids[$id]=true;
    $catalog=[];
    foreach(dball('SELECT id,name,orientation,layout_type FROM playlists WHERE tenant_id=? AND (orientation=? OR orientation=?) ORDER BY id',[$tid,$d['orientation'],'any']) as $list){
      $items=[];if(isset($ids[(int)$list['id']])){$items=expanded($tid,(int)$list['id']);foreach($items as &$item)$item['url']=url('device-media.php?id='.$item['media_id']);unset($item);}
      $catalog[(string)$list['id']]=['id'=>(int)$list['id'],'name'=>$list['name'],'layout'=>$list['layout_type'],'orientation'=>$list['orientation'],'items'=>$items];
    }
    $currentMode=resolve_mode($d,$modeBlocks,$now,(int)$opts['modes_enabled']);
    api_out(['schema_version'=>3,'device_id'=>$did,'device_name'=>$d['name'],'orientation'=>$d['orientation'],
      'mode'=>(int)$d['mode'],'active_mode'=>$currentMode,'timezone'=>$now->getTimezone()->getName(),
      'server_local_time'=>$now->format('Y-m-d H:i:s'),'server_epoch_ms'=>(int)round(microtime(true)*1000),
      'mode_blocks'=>$modeBlocks,'device_options'=>$opts,'schedules'=>$all,'fallback'=>$fallback,'playback'=>$selected,
      'playlist_catalog'=>(object)$catalog,'live'=>live_panel($tid,$did)]);
  }
  api_out(['error'=>'Operación no encontrada'],404);
} catch(Throwable $e){$error=fault($e,'api_device');api_out(['error'=>$error],500);}
