<?php
require dirname(__DIR__).'/app/bootstrap.php';require_once ROOT.'/app/domain.php';require_once ROOT.'/app/device_service.php';
$token=device_bearer_token();if($token===null){http_response_code(401);exit;}
$d=dbone('SELECT id,tenant_id FROM devices WHERE device_token_hash=?',[hash('sha256',$token)]);if(!$d){http_response_code(401);exit;}
if(ds_service_state((int)$d['tenant_id'],(int)$d['id'])['suspended']){http_response_code(403);exit('Servicio suspendido');}
$r=ds_readable_media((int)($_GET['id']??0),(int)$d['tenant_id']);if(!$r){http_response_code(404);exit;}
$f=ROOT.'/storage/media/'.(int)$r['tenant_id'].'/'.$r['filename'];if(!is_file($f)){http_response_code(404);exit;}
header('Content-Type: '.$r['mime']);header('Content-Length: '.filesize($f));header('X-Content-Type-Options: nosniff');readfile($f);
