<?php
require dirname(__DIR__).'/app/applications.php';require_once ROOT.'/app/playback.php';require_once ROOT.'/app/device_service.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
function app_out(array $data,int $status=200):never{http_response_code($status);echo json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);exit;}
if($_SERVER['REQUEST_METHOD']!=='GET'){header('Allow: GET');app_out(['error'=>'Método no permitido'],405);}
try{
 $app=(string)($_GET['app']??'');ds_app_kinds($app);$token=device_bearer_token();if(!$token)app_out(['error'=>'Bearer requerido'],401);$hash=hash('sha256',$token);
 $d=dbone('SELECT id,tenant_id FROM devices WHERE device_token_hash=?',[$hash]);
 if($d){$tid=(int)$d['tenant_id'];if(ds_service_state($tid,(int)$d['id'])['suspended'])app_out(['error'=>'Servicio de pantalla suspendido'],403);$snapshot=ds_app_snapshot($tid,$app,(int)$d['id']);}
 else{
  $t=dbone('SELECT * FROM application_tokens WHERE token_hash=? AND app=? AND revoked_at IS NULL',[$hash,$app]);if(!$t)app_out(['error'=>'Clave no autorizada'],401);
  $owner=dbone('SELECT u.role,u.role_id,u.is_active,u.denied_permissions_json,r.permissions_json FROM users u LEFT JOIN roles r ON r.id=u.role_id AND r.tenant_id=u.tenant_id WHERE u.id=?',[(int)$t['created_by']]);
  $denials=json_decode($owner['denied_permissions_json']??'[]',true)?:[];$perms=json_decode($owner['permissions_json']??'[]',true)?:[];if($owner&&$owner['role']==='admin'&&empty($owner['role_id']))$perms=array_keys(permission_names());
  if(!$owner||!(int)$owner['is_active']||($owner['role']!=='superadmin'&&(!in_array($app.'.manage',$perms,true)||!in_array('integrations.manage',$perms,true)||array_intersect([$app.'.manage','integrations.manage'],$denials))))app_out(['error'=>'Conexión revocada por permisos del propietario'],403);
  $snapshot=ds_app_snapshot((int)$t['tenant_id'],$app);
 }
 // No secrets or user directory in configuration exports.
 app_out($snapshot);
}catch(PDOException $e){log_event('application_api_error',['type'=>get_class($e)]);app_out(['error'=>'No se pudo consultar la configuración'],500);}catch(RuntimeException $e){app_out(['error'=>$e->getMessage()],422);}catch(Throwable $e){log_event('application_api_error',['type'=>get_class($e)]);app_out(['error'=>'No se pudo consultar la configuración'],500);}
