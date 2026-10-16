<?php
/** Company-safe reusable configuration profiles. No device tokens, PINs or paths. */
require __DIR__.'/_init.php';require_once ROOT.'/app/domain.php';require_once ROOT.'/app/device_config.php';
require_permission('devices.view');$message='';$error='';
try {
 if($_SERVER['REQUEST_METHOD']==='POST') {
  check_csrf();require_permission('devices.edit');$action=(string)($_POST['action']??'');
  $tid=post_tenant();$uid=(int)user()['id'];
  if($action==='create') {
   $name=trim((string)($_POST['name']??''));if(strlen($name)<2||strlen($name)>160)throw new RuntimeException('Nombre: entre 2 y 160 caracteres');
   $source=(string)($_POST['source']??'default');$settings=ds_config_defaults();
   if($source==='backup') {
    $bid=(int)($_POST['backup_id']??0);
    $b=dbone('SELECT config_json FROM device_config_backups WHERE id=? AND tenant_id=?',[$bid,$tid]);
    if(!$b)throw new RuntimeException('Respaldo inexistente o de otra empresa');
    $settings=ds_config_validate(json_decode($b['config_json'],true,8,JSON_THROW_ON_ERROR));
   }
   try{execsql('INSERT INTO device_config_profiles(tenant_id,name,config_json,updated_at) VALUES(?,?,?,UTC_TIMESTAMP())',[$tid,$name,ds_config_json($settings)]);}
   catch(PDOException $e){if(($e->errorInfo[1]??0)==1062)throw new RuntimeException('Ya existe un perfil con ese nombre en esta empresa');throw $e;}
   log_event('device_profile_created',['tenant_id'=>$tid]);$message='Perfil creado correctamente.';
  }elseif($action==='publish') {
   $pid=(int)($_POST['profile_id']??0);
   $profile=dbone('SELECT * FROM device_config_profiles WHERE id=? AND tenant_id=?',[$pid,$tid]);
   if(!$profile)throw new RuntimeException('Perfil no encontrado en esta empresa');
   $settings=ds_config_validate(json_decode($profile['config_json'],true,8,JSON_THROW_ON_ERROR));
   $ids=array_unique(array_map('intval',(array)($_POST['device_ids']??[])));
   if(!$ids||count($ids)>100)throw new RuntimeException('Selecciona entre 1 y 100 pantallas de la misma empresa');
   $count=0;$errors=[];
   foreach($ids as $did) {
    $d=dbone('SELECT id,name FROM devices WHERE id=? AND tenant_id=?',[$did,$tid]);
    if(!$d){$errors[]='Pantalla #'.$did.' fuera de esta empresa';continue;}
    try{
     $current=ds_config_for_device($did,$tid);
     ds_config_backup($did,$tid,'desired','Antes de perfil '.substr((string)$profile['name'],0,80),$uid);
     ds_config_publish($did,$tid,$settings,$current['revision'],$uid);
     $count++;
    }catch(Throwable $e){$errors[]=$d['name'].': '.$e->getMessage();}
   }
   log_event('device_profile_published',['tenant_id'=>$tid,'profile_id'=>$pid,'published'=>$count,'rejected'=>count($errors)]);
   $message='Enviada a '.$count.' pantalla(s); pendiente de confirmación de cada APK.';
   if($errors)$error='No se pudo publicar en: '.implode(' · ',array_slice($errors,0,8));
  }else throw new RuntimeException('Operación no admitida');
 }
}catch(Throwable $e){$error=$e instanceof PDOException?fault($e,'device_profiles'):$e->getMessage();}
$scope=company_scope(isset($_GET['company'])?(string)$_GET['company']:null);
$profiles=dball('SELECT p.*,t.name company_name FROM device_config_profiles p JOIN tenants t ON t.id=p.tenant_id WHERE '.($scope===null?'1=1':'p.tenant_id=?').' ORDER BY t.name,p.name',$scope===null?[]:[$scope]);
$devices=dball('SELECT id,tenant_id,name FROM devices WHERE '.($scope===null?'1=1':'tenant_id=?').' ORDER BY name',$scope===null?[]:[$scope]);
$backs=dball('SELECT b.id,b.tenant_id,b.label,d.name device_name FROM device_config_backups b JOIN devices d ON d.id=b.device_id AND d.tenant_id=b.tenant_id WHERE '.($scope===null?'1=1':'b.tenant_id=?').' ORDER BY b.id DESC LIMIT 75',$scope===null?[]:[$scope]);
render('Perfiles de configuración APK',function()use($scope,$profiles,$devices,$backs,$message,$error){
 if($message)echo '<div class="alert alert-success">'.h($message).'</div>';
 if($error)echo '<div class="alert alert-danger">'.h($error).'</div>';
 echo '<p>Guarda perfiles sin credenciales y aplícalos a varias pantallas de la misma empresa. La entrega queda pendiente hasta que cada APK confirme su aplicación.</p>';
 echo '<form class="ds-filters" method="get">'.company_filter_control($scope).'<button class="btn btn-primary">Filtrar</button></form>';
 if(can('devices.edit')){create_modal('Nuevo perfil',function()use($backs){echo company_select(tenant());field('name','Nombre del perfil');echo '<label>Origen</label><select class="form-select mb-3" name="source"><option value="default">Valores predeterminados</option><option value="backup">Desde respaldo existente</option></select><label>Respaldo de origen (si aplica)</label><select name="backup_id" class="form-select mb-3">';foreach($backs as $b)echo '<option value="'.(int)$b['id'].'">'.h($b['device_name'].' · '.$b['label']).'</option>';echo '</select>';echo '<input type="hidden" name="action" value="create">';});}
 echo '<div class="card table-responsive"><table class="table"><thead><tr><th>Empresa</th><th>Perfil</th><th>Actualizado</th><th>Destino</th></tr></thead><tbody>';
 $modals='';foreach($profiles as $profile){$id=(int)$profile['id'];$tid=(int)$profile['tenant_id'];echo '<tr><td>'.h($profile['company_name']).'</td><td>'.h($profile['name']).'</td><td>'.h($profile['updated_at']).'</td><td>'.(can('devices.edit')?'<button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#send'.$id.'">Enviar a pantallas</button>':'').'</td></tr>';
  if(!can('devices.edit'))continue;
  ob_start();echo '<div class="modal fade" id="send'.$id.'" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Publicar perfil · '.h($profile['name']).'</h5><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div><div class="modal-body">';form_open();echo '<input type="hidden" name="action" value="publish"><input type="hidden" name="tenant_id" value="'.$tid.'"><input type="hidden" name="profile_id" value="'.$id.'">';foreach($devices as $d){if((int)$d['tenant_id']!==$tid)continue;echo '<label class="d-block mb-2"><input type="checkbox" name="device_ids[]" value="'.(int)$d['id'].'"> '.h($d['name']).'</label>';}echo '<p class="text-muted">Se crea un respaldo de la configuración anterior de cada pantalla. No se copian tokens ni PIN.</p><div class="ds-modal-actions"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary" type="submit">Publicar y enviar</button></div></form></div></div></div></div>';$modals.=ob_get_clean();
 }echo '</tbody></table></div>'.$modals;
});
