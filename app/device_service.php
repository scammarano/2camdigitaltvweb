<?php
declare(strict_types=1);
require_once __DIR__.'/bootstrap.php';
function ds_service_state(int $tid,int $did):array {
 $r=dbone('SELECT * FROM device_service_state WHERE tenant_id=? AND device_id=?',[$tid,$did]);
 return ['suspended'=>(bool)($r['suspended']??false),'revision'=>(int)($r['revision']??0),'reason'=>(string)($r['reason']??''),'requested_at'=>$r['requested_at']??null,'reported_revision'=>isset($r['reported_revision'])?(int)$r['reported_revision']:null,'reported_suspended'=>isset($r['reported_suspended'])?(bool)$r['reported_suspended']:null,'reported_at'=>$r['reported_at']??null];
}
function ds_service_change(int $tid,int $did,bool $suspended,string $reason,int $revision):void {
 require_permission('devices.suspend');if(!is_super()&&tenant()!==$tid)throw new RuntimeException('Empresa inválida');
 $reason=trim($reason);if(strlen($reason)>190)throw new RuntimeException('Motivo: máximo 190 bytes');
 db()->beginTransaction();try{
  if(!dbone('SELECT id FROM devices WHERE tenant_id=? AND id=? FOR UPDATE',[$tid,$did]))throw new RuntimeException('Pantalla no encontrada');
  $current=ds_service_state($tid,$did);if($current['revision']!==$revision)throw new RuntimeException('El servicio cambió. Recarga antes de continuar');
  execsql("INSERT INTO device_service_state(device_id,tenant_id,suspended,revision,reason,requested_by,requested_at) VALUES(?,?,?,?,?,?,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE suspended=VALUES(suspended),revision=VALUES(revision),reason=VALUES(reason),requested_by=VALUES(requested_by),requested_at=UTC_TIMESTAMP()",[$did,$tid,$suspended?1:0,$revision+1,$reason,(int)auth()['id']]);db()->commit();
  log_event('device_service_requested',['device_id'=>$did,'company'=>$tid,'suspended'=>$suspended,'revision'=>$revision+1]);
 }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
}
function ds_service_report(int $tid,int $did,int $revision,bool $suspended):void {
 db()->beginTransaction();try{
  if(!dbone('SELECT id FROM devices WHERE tenant_id=? AND id=? FOR UPDATE',[$tid,$did]))throw new RuntimeException('Pantalla inválida');
  $wanted=ds_service_state($tid,$did);if($wanted['revision']!==$revision||$wanted['suspended']!==$suspended)throw new RuntimeException('La confirmación no corresponde al estado solicitado');
  execsql('INSERT INTO device_service_state(device_id,tenant_id,reported_revision,reported_suspended,reported_at) VALUES(?,?,?,?,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE reported_revision=VALUES(reported_revision),reported_suspended=VALUES(reported_suspended),reported_at=UTC_TIMESTAMP()',[$did,$tid,$revision,$suspended?1:0]);
  db()->commit();log_event('device_service_confirmed',['device_id'=>$did,'company'=>$tid,'revision'=>$revision,'suspended'=>$suspended]);
 }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
}
