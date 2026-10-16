<?php
require __DIR__.'/_init.php';require_once ROOT.'/app/domain.php';require_once ROOT.'/app/cleanup.php';require_once ROOT.'/app/device_config.php';require_permission('devices.view');
$t=tenant();$error='';$ok='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 check_csrf();try{
  require_permission('devices.edit');$target=post_tenant();$action=$_POST['action']??'create';$id=(int)($_POST['id']??0);
  if($action==='delete'){
   ds_cleanup_confirm();$ok=ds_cleanup_delete('devices',$id,$target);
  }elseif($action==='unpair'){
   ds_cleanup_confirm();$ok=ds_cleanup_unpair_device($id,$target);
  }elseif($action==='pair'){
   $d=dbone('SELECT id FROM devices WHERE id=? AND tenant_id=?',[$id,$target]);if(!$d)throw new RuntimeException('Pantalla no encontrada');
   $code='';$hash='';$valid=false;
   for($n=0;$n<5;$n++){$code=(string)random_int(100000,999999);$hash=hash_hmac('sha256',$code,cfg()['app_key']);if(!dbone('SELECT id FROM devices WHERE pairing_code_hash=? AND pairing_expires>UTC_TIMESTAMP() AND id<>?',[$hash,$id])){$valid=true;break;}}
   if(!$valid)throw new RuntimeException('No se pudo generar un código único. Inténtalo nuevamente.');
   execsql('UPDATE devices SET pairing_code_hash=?,pairing_expires=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 10 MINUTE) WHERE id=? AND tenant_id=?',[$hash,$id,$target]);
   $_SESSION['pair_code']=$code;$ok='Código generado';
  }elseif($action==='assign'){
   $d=dbone('SELECT id,orientation FROM devices WHERE id=? AND tenant_id=?',[$id,$target]);if(!$d)throw new RuntimeException('Pantalla no encontrada');
   $playlist=valid_default_playlist($target,(int)($_POST['active_playlist_id']??0),$d['orientation']);
   execsql('UPDATE devices SET active_playlist_id=? WHERE id=? AND tenant_id=?',[$playlist,$id,$target]);
   $ok=$playlist?'Lista predeterminada asignada. Genera un código y abre el reproductor para probarla.':'Asignación predeterminada eliminada';
  }else{
   if(array_key_exists('mode',$_POST))throw new RuntimeException('Configura el modo y sus playlists en Administración remota APK. Recarga este formulario.');
   $name=trim((string)($_POST['name']??''));$place=trim((string)($_POST['location_label']??''));$ori=(string)($_POST['orientation']??'');$w=(int)($_POST['width_px']??0);$h=(int)($_POST['height_px']??0);
   if($name===''||strlen($name)>160||!in_array($ori,['landscape','portrait'],true)||$w<240||$h<240||$w>16384||$h>16384)throw new RuntimeException('Nombre, orientación o resolución inválidos');
   if(($ori==='landscape'&&$w<$h)||($ori==='portrait'&&$h<$w))throw new RuntimeException('La resolución no coincide con la orientación');
   $playlist=valid_default_playlist($target,(int)($_POST['active_playlist_id']??0),$ori);
   if($action==='create'){
    execsql('INSERT INTO devices(tenant_id,name,orientation,mode,width_px,height_px,location_label,active_playlist_id) VALUES(?,?,?,?,?,?,?,?)',[$target,$name,$ori,1,$w,$h,$place,$playlist]);
   }elseif($action==='edit'){
    $device=owned_edit_record('devices',$id);$old=(int)$device['tenant_id'];
    $schedules=dball('SELECT p.name,p.orientation FROM schedules s JOIN schedule_items si ON si.schedule_id=s.id AND si.tenant_id=s.tenant_id JOIN playlists p ON p.id=si.playlist_id AND p.tenant_id=si.tenant_id WHERE s.device_id=? AND s.tenant_id=?',[$id,$old]);
    foreach($schedules as $s)if($s['orientation']!=='any'&&$s['orientation']!==$ori)throw new RuntimeException('Orientación incompatible con programación de lista '.$s['name']);
    if($old!==$target){
      assert_unlinked_before_transfer('devices',$id,$old);
      // A company transfer must revoke any issued device credentials.
      execsql('UPDATE devices SET tenant_id=?,site_id=NULL,name=?,orientation=?,width_px=?,height_px=?,location_label=?,active_playlist_id=?,pairing_code_hash=NULL,pairing_expires=NULL,device_token_hash=NULL,last_seen=NULL WHERE id=? AND tenant_id=?',[$target,$name,$ori,$w,$h,$place,$playlist,$id,$old]);
    }else execsql('UPDATE devices SET name=?,orientation=?,width_px=?,height_px=?,location_label=?,active_playlist_id=? WHERE id=? AND tenant_id=?',[$name,$ori,$w,$h,$place,$playlist,$id,$old]);
   }else throw new RuntimeException('Acción inválida');
   $ok='Pantalla guardada';
  }
  log_event('device_changed',['action'=>$action,'company'=>$target,'device'=>$id]);
 }catch(Throwable $e){$error=$e instanceof PDOException?fault($e,'devices'):$e->getMessage();}
}
function valid_default_playlist(int $tenant,int $listId,string $orientation):?int {
 if($listId<=0)return null;
 $p=dbone('SELECT id,orientation FROM playlists WHERE id=? AND tenant_id=?',[$listId,$tenant]);
 if(!$p)throw new RuntimeException('La lista no pertenece a la empresa de la pantalla');
 if($p['orientation']!=='any'&&$p['orientation']!==$orientation)throw new RuntimeException('La orientación de la lista no coincide con la pantalla');
 return (int)$p['id'];
}
function device_playlist_options(array $lists,?int $selected=null,?int $tenantId=null):string {
 $out='<option value="">Sin lista predeterminada</option>';
 foreach($lists as $p){if($tenantId!==null && (int)$p['tenant_id']!==$tenantId)continue;$out.='<option value="'.(int)$p['id'].'" data-orientation="'.h($p['orientation']).'" data-tenant="'.(int)$p['tenant_id'].'" '.($selected===(int)$p['id']?'selected':'').'>'.h($p['name']).' · '.h(orientations()[$p['orientation']]??$p['orientation']).' · '.h(layouts()[$p['layout_type']]??$p['layout_type']).'</option>';
 }return $out;
}
$scope=company_scope(isset($_GET['company'])?(string)$_GET['company']:null);
$poolClause=$scope===null?'1=1':'tenant_id=?';$poolParams=$scope===null?[]:[$scope];
$lists=dball('SELECT id,tenant_id,name,orientation,layout_type FROM playlists WHERE '.$poolClause.' ORDER BY name',$poolParams);
$devicesClause=$scope===null?'1=1':'d.tenant_id=?';
$rows=dball('SELECT d.*,p.name playlist_name,t.name company_name FROM devices d JOIN tenants t ON t.id=d.tenant_id LEFT JOIN playlists p ON p.id=d.active_playlist_id AND p.tenant_id=d.tenant_id WHERE '.$devicesClause.' ORDER BY t.name,d.name',$poolParams);
render('Pantallas',function()use($rows,$lists,$error,$ok,$scope){
 if($error)echo '<div class="alert alert-danger">'.h($error).'</div>';if($ok)echo '<div class="alert alert-success">'.h($ok).'</div>';
 if(isset($_SESSION['pair_code'])){echo '<div class="alert alert-success">Código temporal (10 minutos): <strong style="font-size:20px;letter-spacing:3px">'.h($_SESSION['pair_code']).'</strong> · Abre <a href="'.h(url('player.php')).'" target="_blank" rel="noopener">Reproductor web</a> en otra pestaña o pantalla e ingrésalo.</div>';unset($_SESSION['pair_code']);}
 echo '<div class="alert alert-info"><strong>Cómo reproducir:</strong> en cada pantalla pulsa <b>Asignar lista</b>, selecciona una lista compatible y guarda. Después pulsa <b>Generar código</b> y úsalo en <a href="'.h(url('player.php')).'" target="_blank" rel="noopener">Reproductor web</a>. Si existe una programación activa, esta tiene prioridad; fuera de ese horario se reproduce la lista predeterminada.</div>';
 if(can('devices.edit'))create_modal('Nueva pantalla',function()use($lists,$scope){echo '<input type="hidden" name="action" value="create">'.company_select($scope??0);field('name','Nombre');field('location_label','Ubicación');echo '<label>Orientación</label>'.sel('orientation',['landscape'=>'Horizontal','portrait'=>'Vertical'],'landscape').'<p class="ds-hint">Después de crear la pantalla, configura su modo y playlists desde Administración remota APK.</p>';field('width_px','Ancho en píxeles','number','1920');field('height_px','Alto en píxeles','number','1080');echo '<label>Lista predeterminada (opcional)</label><select class="form-select" name="active_playlist_id">'.device_playlist_options($lists,null,is_super()?null:tenant()).'</select><p class="ds-hint">Solo se permite una lista de la misma empresa y orientación compatible.</p>';});
 echo '<form method="get" class="ds-filters" aria-label="Filtrar pantallas">'.company_filter_control($scope).'<div class="ds-filter-actions"><button class="btn btn-primary">Filtrar</button><a href="'.h(url('devices.php')).'" class="btn btn-outline-secondary">Limpiar</a></div></form><div class="card table-responsive"><table class="table"><thead><tr><th>Empresa</th><th>Pantalla</th><th>Ubicación</th><th>Orientación</th><th>Resolución</th><th>Modo</th><th>Lista predeterminada</th><th>Último contacto</th><th>Acciones</th></tr></thead><tbody>';
 $modals='';foreach($rows as $d){$id=(int)$d['id'];$dialog='dev'.$id;$assign='assign'.$id;$selected=$d['active_playlist_id']===null?null:(int)$d['active_playlist_id'];
 echo '<tr><td>'.h($d['company_name']).'</td><td><strong>'.h($d['name']).'</strong></td><td>'.h($d['location_label']??'').'</td><td>'.h(orientations()[$d['orientation']]??'').'</td><td>'.(int)$d['width_px'].' × '.(int)$d['height_px'].'</td><td>'.h(ds_config_schema()['modes'][(int)$d['mode']]??'').'</td><td>'.($d['playlist_name']?'<span class="ds-assignment">'.h($d['playlist_name']).'</span>'.(can('playlists.view')?' <a href="'.h(url('preview.php?id='.(int)$d['active_playlist_id'])).'" target="_blank" rel="noopener">Vista previa</a>':''):'<span class="ds-assignment-none">Sin asignar</span>').'</td><td>'.h($d['last_seen']??'Sin conectar').'</td><td>';
 if(can('devices.edit')){
 echo '<a class="btn btn-sm btn-outline-primary" href="'.h(url('device-manager.php?company='.(int)$d['tenant_id'].'#cfg'.$id)).'">Configurar modo y playlists</a> <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#'.$assign.'">Asignar lista</button> <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#'.$dialog.'">Editar</button> <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#remove-devices-'.$id.'">Eliminar</button> ';
 if(!empty($d['device_token_hash']))echo '<button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#unpair-device-'.$id.'">Desvincular</button> ';
 form_open();echo '<input type="hidden" name="action" value="pair"><input type="hidden" name="id" value="'.$id.'"><input type="hidden" name="tenant_id" value="'.(int)$d['tenant_id'].'"><button class="btn btn-sm btn-outline-secondary" type="submit">Generar código</button></form>';
 }
 echo '</td></tr>';
 if(can('devices.edit')){
 ob_start();echo '<div class="modal fade" id="'.$assign.'" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5>Asignar lista · '.h($d['name']).'</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div><div class="modal-body">';form_open();echo '<input type="hidden" name="action" value="assign"><input type="hidden" name="id" value="'.$id.'"><input type="hidden" name="tenant_id" value="'.(int)$d['tenant_id'].'"><label>Lista predeterminada</label><select name="active_playlist_id" class="form-select mb-3">'.device_playlist_options($lists,$selected,(int)$d['tenant_id']).'</select><div class="ds-hint mb-3">Esta lista se reproduce cuando no existe un bloque horario activo. También puedes dejarla vacía para quitar la asignación.</div><button type="submit" class="btn btn-primary">Guardar asignación</button></form></div></div></div></div>';
 echo '<div class="modal fade" id="'.$dialog.'" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5>Editar pantalla · '.h($d['name']).'</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div><div class="modal-body">';form_open();echo '<input type="hidden" name="action" value="edit"><input type="hidden" name="id" value="'.$id.'">'.company_select((int)$d['tenant_id'],'data-device-company').'<label>Nombre</label><input name="name" class="form-control mb-2" value="'.h($d['name']).'" required><label>Ubicación</label><input name="location_label" class="form-control mb-2" value="'.h($d['location_label']??'').'"><label>Orientación</label>'.sel('orientation',['landscape'=>'Horizontal','portrait'=>'Vertical'],$d['orientation']).'<p class="ds-hint">El modo se configura en Administración remota APK junto con sus playlists.</p>';
 echo '<label>Ancho px</label><input type="number" min="240" max="16384" class="form-control mb-2" name="width_px" value="'.(int)$d['width_px'].'" required><label>Alto px</label><input type="number" min="240" max="16384" class="form-control mb-2" name="height_px" value="'.(int)$d['height_px'].'" required><label>Lista predeterminada</label><select name="active_playlist_id" class="form-select mb-3">'.device_playlist_options($lists,$selected,is_super()?null:(int)$d['tenant_id']).'</select><p class="ds-hint mb-3">Al cambiar la empresa se revoca el emparejamiento del dispositivo y debe vincularse nuevamente. No se permite si tiene programaciones.</p><button type="submit" class="btn btn-primary">Guardar cambios</button></form></div></div></div></div>';
 $modals.=ob_get_clean();
 }
 }
 if(can('devices.edit'))foreach($rows as $d){
    $modals.=ds_cleanup_modal('devices',(int)$d['id'],(int)$d['tenant_id'],(string)$d['name'],
       'Primero desvincula la pantalla y elimina sus bloques de programación. Eliminarla borrará también sus logs, historial y configuración.');
    if(!empty($d['device_token_hash'])){
       $confirm=ds_cleanup_modal('devices',(int)$d['id'],(int)$d['tenant_id'],(string)$d['name'],
          'Se revocará la vinculación actual. El dispositivo necesitará un código nuevo para volver a conectarse.');
       $confirm=str_replace('id="remove-devices-'.$d['id'].'"','id="unpair-device-'.$d['id'].'"',$confirm);
       $confirm=str_replace('name="action" value="delete"','name="action" value="unpair"',$confirm);
       $confirm=str_replace('Eliminar pantalla','Desvincular pantalla',$confirm);
       $confirm=str_replace('Eliminar definitivamente','Desvincular dispositivo',$confirm);
       $modals.=$confirm;
    }
 }
 echo '</tbody></table></div>'.$modals.'<script defer src="'.h(url('assets/js/devices-company.js')).'?v=0.3.8"></script>'; 
});
