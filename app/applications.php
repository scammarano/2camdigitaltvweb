<?php
declare(strict_types=1);
require_once __DIR__.'/domain.php';
function ds_apps():array{return ['turnos'=>'2Cam Turnos','sports'=>'2Cam Sports'];}
/** Configuration catalog, not a queue or scoring engine. Authorities keep their own rules. */
function ds_app_kinds(string $app):array {
 return match($app){
 'turnos'=>[
  'room'=>['label'=>'Salas de espera','parent'=>null,'fields'=>['prefix'=>'Prefijo del ticket']],
  'service'=>['label'=>'Servicios','parent'=>'room','fields'=>['prefix'=>'Prefijo del servicio']],
  'counter'=>['label'=>'Ventanillas','parent'=>'room','fields'=>['code'=>'Código visible']],
  'operator'=>['label'=>'Operadores','parent'=>'room','user'=>true,'fields'=>['counter_id'=>'Ventanilla asignada']],
  'kiosk'=>['label'=>'Tablets de solicitud','parent'=>'room','fields'=>['welcome'=>'Mensaje de bienvenida']],
  'control'=>['label'=>'Controles de llamada','parent'=>'room','user'=>true,'fields'=>['counter_id'=>'Ventanilla asignada']],
  'screen'=>['label'=>'Pantallas','parent'=>'room','device'=>true,'fields'=>[]]],
 'sports'=>[
  'venue'=>['label'=>'Sedes / clubes','parent'=>null,'fields'=>[]],
  'court'=>['label'=>'Pistas','parent'=>'venue','fields'=>['discipline'=>'Disciplina']],
  'tournament'=>['label'=>'Torneos','parent'=>'venue','fields'=>['discipline'=>'Disciplina','rules_ref'=>'Referencia del reglamento en Sports']],
  'team'=>['label'=>'Equipos','parent'=>'venue','fields'=>[]],
  'match'=>['label'=>'Encuentros','parent'=>'court','fields'=>['tournament_id'=>'Torneo','home_team_id'=>'Equipo local','away_team_id'=>'Equipo visitante','starts_at'=>'Inicio (UTC: AAAA-MM-DD HH:MM)']],
  'operator'=>['label'=>'Administradores deportivos','parent'=>'venue','user'=>true,'fields'=>[]],
  'control'=>['label'=>'Controles de marcador','parent'=>'court','user'=>true,'fields'=>[]],
  'screen'=>['label'=>'Pantallas','parent'=>'court','device'=>true,'fields'=>[]]],
 default=>throw new RuntimeException('Aplicación inválida')};
}
function ds_app_config(int $tid,string $app):array {
 ds_app_kinds($app);$r=dbone('SELECT revision,settings_json,updated_at FROM application_configs WHERE tenant_id=? AND app=?',[$tid,$app]);
 return ['revision'=>(int)($r['revision']??0),'updated_at'=>$r['updated_at']??null,'settings'=>array_merge(['title'=>'','logo_media_id'=>0,'request_whatsapp'=>false,'whatsapp_notifications_requested'=>false],$r?json_decode($r['settings_json'],true,16,JSON_THROW_ON_ERROR):[])];
}
function ds_app_lock(int $tid,string $app,int $revision):void {
 // Lock the tenant first: serializes first insert as well as subsequent config/entity edits.
 if(!dbone('SELECT id FROM tenants WHERE id=? FOR UPDATE',[$tid]))throw new RuntimeException('Empresa inválida');
 $c=ds_app_config($tid,$app);if($c['revision']!==$revision)throw new RuntimeException('La configuración cambió; recarga antes de guardar');
}
function ds_app_bump(int $tid,string $app):void {
 execsql("INSERT INTO application_configs(tenant_id,app,revision,settings_json,updated_at) VALUES(?,?,1,'{}',UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE revision=revision+1,updated_at=UTC_TIMESTAMP()",[$tid,$app]);
}
function ds_app_save_settings(int $tid,string $app,int $revision,array $input):void {
 require_permission($app.'.manage');if(!is_super()&&$tid!==tenant())throw new RuntimeException('Empresa inválida');
 $logo=(int)($input['logo_media_id']??0);$m=$logo?ds_readable_media($logo,$tid):null;if($logo&&(!$m||!str_starts_with($m['mime'],'image/')))throw new RuntimeException('El logo debe ser una imagen accesible a la empresa');
 $title=trim((string)($input['title']??''));if(strlen($title)>160)throw new RuntimeException('Título demasiado largo');
 $settings=['title'=>$title,'logo_media_id'=>$logo,'request_whatsapp'=>isset($input['request_whatsapp']),'whatsapp_notifications_requested'=>isset($input['whatsapp_notifications_requested'])];
 db()->beginTransaction();try{ds_app_lock($tid,$app,$revision);ds_app_bump($tid,$app);execsql('UPDATE application_configs SET settings_json=? WHERE tenant_id=? AND app=?',[json_encode($settings,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),$tid,$app]);db()->commit();}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
 log_event('application_settings_saved',['company'=>$tid,'app'=>$app]);
}
function ds_app_entity(int $tid,string $app,int $revision,array $input):int {
 require_permission($app.'.manage');if(!is_super()&&$tid!==tenant())throw new RuntimeException('Empresa inválida');
 $kind=(string)($input['kind']??'');$spec=ds_app_kinds($app)[$kind]??null;if(!$spec)throw new RuntimeException('Tipo inválido');
 $id=(int)($input['id']??0);$name=trim((string)($input['name']??''));$parent=(int)($input['parent_id']??0);$uid=(int)($input['user_id']??0);$did=(int)($input['device_id']??0);
 db()->beginTransaction();try{
  ds_app_lock($tid,$app,$revision);$old=$id?dbone('SELECT * FROM application_entities WHERE id=? AND tenant_id=? AND app=? AND kind=?',[$id,$tid,$app,$kind]):null;
  if($id&&!$old)throw new RuntimeException('Registro de otra empresa o aplicación');
  if(($input['action']??'')==='delete'){
   if(dbone('SELECT id FROM application_entities WHERE parent_id=? LIMIT 1',[$id]))throw new RuntimeException('Primero elimina o reasigna los elementos dependientes');
   if(dbone("SELECT id FROM application_entities WHERE tenant_id=? AND app=? AND (JSON_UNQUOTE(JSON_EXTRACT(config_json,'$.counter_id'))=? OR JSON_UNQUOTE(JSON_EXTRACT(config_json,'$.tournament_id'))=? OR JSON_UNQUOTE(JSON_EXTRACT(config_json,'$.home_team_id'))=? OR JSON_UNQUOTE(JSON_EXTRACT(config_json,'$.away_team_id'))=?) LIMIT 1",[$tid,$app,(string)$id,(string)$id,(string)$id,(string)$id]))throw new RuntimeException('Otro elemento utiliza este registro; reasígnalo antes de eliminar');
   execsql('DELETE FROM application_entities WHERE id=? AND tenant_id=? AND app=?',[$id,$tid,$app]);
  }else{
   if($name===''||strlen($name)>160)throw new RuntimeException('Nombre requerido (máximo 160 bytes)');
   if($spec['parent']){if(!$parent||!dbone('SELECT id FROM application_entities WHERE id=? AND tenant_id=? AND app=? AND kind=?',[$parent,$tid,$app,$spec['parent']]))throw new RuntimeException('Selecciona un destino de la misma empresa y aplicación');}elseif($parent)throw new RuntimeException('Este elemento no lleva destino superior');
   if(!empty($spec['user'])){if(!$uid||!dbone('SELECT id FROM users WHERE id=? AND tenant_id=? AND is_active=1',[$uid,$tid]))throw new RuntimeException('Selecciona un usuario activo de esta empresa');}elseif($uid)throw new RuntimeException('Usuario no permitido para este tipo');
   if(!empty($spec['device'])){if(!$did||!dbone('SELECT id FROM devices WHERE id=? AND tenant_id=?',[$did,$tid]))throw new RuntimeException('Selecciona una pantalla de esta empresa');}elseif($did)throw new RuntimeException('Pantalla no permitida para este tipo');
   // Do not move parent entities with dependents: prevents silently crossing rooms or venues.
   if($old&&(int)$old['parent_id']!==$parent&&dbone('SELECT id FROM application_entities WHERE parent_id=? LIMIT 1',[$id]))throw new RuntimeException('Reasigna primero sus elementos dependientes antes de moverlo');
   $data=[];foreach($spec['fields'] as $key=>$label){$v=trim((string)($input[$key]??''));if(strlen($v)>160)throw new RuntimeException('Valor demasiado largo: '.$label);$data[$key]=$v;}
   $scopeId=$parent;$scopeKind=$spec['parent'];if($scopeKind==='court'){$court=dbone('SELECT parent_id FROM application_entities WHERE id=?',[$parent]);$venue=(int)$court['parent_id'];}else $venue=$parent;
   foreach(['counter_id'=>'counter','tournament_id'=>'tournament','home_team_id'=>'team','away_team_id'=>'team'] as $key=>$refKind)if(!empty($data[$key])){
    if(!ctype_digit($data[$key]))throw new RuntimeException('Referencia inválida: '.$key);
    $ref=dbone('SELECT parent_id FROM application_entities WHERE id=? AND tenant_id=? AND app=? AND kind=?',[(int)$data[$key],$tid,$app,$refKind]);
    if(!$ref||(int)$ref['parent_id']!==($key==='counter_id'?$scopeId:$venue))throw new RuntimeException('Referencia fuera de la sala o sede: '.$key);
   }
   if(!empty($data['starts_at'])&&!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/D',$data['starts_at']))throw new RuntimeException('Inicio debe indicarse en UTC: AAAA-MM-DD HH:MM');
   $values=[$name,$parent?:null,$uid?:null,$did?:null,json_encode($data,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),isset($input['enabled'])?1:0];
   if($id)execsql('UPDATE application_entities SET name=?,parent_id=?,user_id=?,device_id=?,config_json=?,enabled=? WHERE id=? AND tenant_id=? AND app=?',[...$values,$id,$tid,$app]);
   else{execsql('INSERT INTO application_entities(name,parent_id,user_id,device_id,config_json,enabled,tenant_id,app,kind) VALUES(?,?,?,?,?,?,?,?,?)',[...$values,$tid,$app,$kind]);$id=(int)db()->lastInsertId();}
  }ds_app_bump($tid,$app);db()->commit();
 }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
 log_event('application_entity_changed',['company'=>$tid,'app'=>$app,'entity'=>$id,'action'=>$input['action']??'save']);return $id;
}
function ds_app_snapshot(int $tid,string $app,?int $deviceId=null):array {
 $c=ds_app_config($tid,$app);$all=dball('SELECT id,kind,name,parent_id,user_id,device_id,config_json,enabled FROM application_entities WHERE tenant_id=? AND app=? ORDER BY id',[$tid,$app]);
 if($deviceId!==null){$binding=null;foreach($all as $e)if((int)$e['device_id']===$deviceId&&$e['kind']==='screen'&&(int)$e['enabled'])$binding=$e;
  if(!$binding)throw new RuntimeException('Pantalla sin asignación activa a esta aplicación');
  $parent=(int)$binding['parent_id'];$ids=[$parent=>true,(int)$binding['id']=>true];foreach($all as $e)if((int)$e['id']===$parent&&!empty($e['parent_id']))$ids[(int)$e['parent_id']]=true;
  $all=array_values(array_filter($all,fn($e)=>(int)$e['enabled']===1&&((!in_array($e['kind'],['screen','operator','control','kiosk'],true)&&((int)$e['parent_id']===$parent||isset($ids[(int)$e['id']])||($app==='sports'&&in_array($e['kind'],['team','tournament'],true)&&isset($ids[(int)$e['parent_id']]))))||(int)$e['id']===(int)$binding['id'])));
 }
 foreach($all as &$e){$e['id']=(int)$e['id'];$e['parent_id']=$e['parent_id']?(int)$e['parent_id']:null;$e['enabled']=(bool)$e['enabled'];$e['config']=json_decode($e['config_json'],true,16,JSON_THROW_ON_ERROR);unset($e['config_json'],$e['user_id']);}unset($e);
 return ['schema_version'=>1,'tenant_id'=>$tid,'application'=>$app,'revision'=>$c['revision'],'updated_at'=>$c['updated_at'],'settings'=>$c['settings'],'entities'=>$all,'runtime'=>['screen_configuration_consumer'=>'pending','remote_configuration_consumer'=>'pending','whatsapp_delivery'=>'not_connected']];
}
