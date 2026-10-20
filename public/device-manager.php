<?php
/** Multi-tenant remote config editor, versioned backups and asynchronous delivery. */
require __DIR__.'/_init.php';require_once ROOT.'/app/domain.php';require_once ROOT.'/app/device_config.php';require_once ROOT.'/app/device_pin.php';
require_permission('devices.view');$error='';$ok='';
try{
 if($_SERVER['REQUEST_METHOD']==='POST'){
  check_csrf();require_permission('devices.edit');$did=(int)($_POST['device_id']??0);
  $d=is_super()?dbone('SELECT id,tenant_id,name FROM devices WHERE id=?',[$did]):dbone('SELECT id,tenant_id,name FROM devices WHERE id=? AND tenant_id=?',[$did,tenant()]);
  if(!$d)throw new RuntimeException('Pantalla no encontrada o sin permisos');$tid=(int)$d['tenant_id'];
  $action=(string)($_POST['action']??'');$uid=(int)user()['id'];
  if($action==='set_pin'){
   if(!is_super())throw new RuntimeException('Solo SUPERADMIN puede asignar el PIN administrativo');
   $pin=(string)($_POST['new_pin']??'');$confirm=(string)($_POST['confirm_pin']??'');
   if(!hash_equals($pin,$confirm))throw new RuntimeException('La confirmación del PIN no coincide');
   $pinRevision=ds_pin_issue($tid,$did,$pin,$uid);
   $ok='Solicitud de PIN #'.$pinRevision.' registrada; pendiente de que la APK lo confirme. Nunca se muestra el PIN guardado.';
  }elseif($action==='save'){
   $input=ds_config_form_input($_POST);
   $next=ds_config_publish($did,$tid,$input,(int)($_POST['revision']??-1),$uid);
   log_event('device_config_published',['device_id'=>$did,'tenant_id'=>$tid,'revision'=>$next]);$ok='Versión '.$next.' enviada; pendiente de confirmación de la APK.';
  }elseif($action==='backup'){
   $source=($_POST['source']??'')==='reported'?'reported':'desired';$id=ds_config_backup($did,$tid,$source,'Respaldo '.gmdate('Y-m-d H:i'),$uid);
   log_event('device_config_backup',['device_id'=>$did,'id'=>$id]);$ok='Respaldo #'.$id.' registrado.';
  }elseif($action==='restore'){
   $bid=(int)($_POST['backup_id']??0);$backup=dbone('SELECT * FROM device_config_backups WHERE id=? AND tenant_id=? AND device_id=?',[$bid,$tid,$did]);
   if(!$backup)throw new RuntimeException('Respaldo no encontrado');
   $current=ds_config_for_device($did,$tid);
   ds_config_backup($did,$tid,'desired','Antes de restaurar #'.$bid,$uid);
   $next=ds_config_publish($did,$tid,json_decode($backup['config_json'],true,8,JSON_THROW_ON_ERROR),(int)($_POST['revision']??-1),$uid);
   log_event('device_config_restore_requested',['device_id'=>$did,'backup_id'=>$bid,'revision'=>$next]);$ok='Restauración publicada como revisión '.$next.'; pendiente de recibir confirmación de la APK.';
  }else throw new RuntimeException('Operación inválida');
 }
 if(isset($_GET['export'])){
  $bid=(int)$_GET['export'];$backup=is_super()?dbone('SELECT * FROM device_config_backups WHERE id=?',[$bid]):dbone('SELECT * FROM device_config_backups WHERE id=? AND tenant_id=?',[$bid,tenant()]);
  if(!$backup)throw new RuntimeException('Respaldo inexistente');
  header('Content-Type: application/json; charset=utf-8');header('X-Content-Type-Options: nosniff');header('Content-Disposition: attachment; filename="signage-config-'.$bid.'.json"');
  echo json_encode(['format'=>'digital-signage-settings','schema'=>2,'settings'=>ds_config_validate(json_decode($backup['config_json'],true,8,JSON_THROW_ON_ERROR))],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);exit;
 }
}catch(Throwable $e){$error=$e instanceof PDOException?fault($e,'device_manager'):$e->getMessage();}
$scope=company_scope(isset($_GET['company'])?(string)$_GET['company']:null);
$rows=dball('SELECT d.id,d.name,d.tenant_id,t.name company_name,h.local_ip,h.app_version,h.active_mode,h.last_report,d.mode,cd.revision,cd.config_json,cr.applied_revision,cr.status,cr.config_json actual_json,cr.updated_at reported_at,cr.error_detail,pc.revision pin_revision,pc.status pin_status,pc.expires_at pin_expires_at,pc.applied_at pin_applied_at FROM devices d JOIN tenants t ON t.id=d.tenant_id LEFT JOIN device_health h ON h.device_id=d.id AND h.tenant_id=d.tenant_id LEFT JOIN device_config_desired cd ON cd.device_id=d.id AND cd.tenant_id=d.tenant_id LEFT JOIN device_config_reported cr ON cr.device_id=d.id AND cr.tenant_id=d.tenant_id LEFT JOIN device_pin_commands pc ON pc.device_id=d.id AND pc.tenant_id=d.tenant_id WHERE '.($scope===null?'1=1':'d.tenant_id=?').' ORDER BY t.name,d.name',$scope===null?[]:[$scope]);
render('Administración remota de APK',function()use($rows,$scope,$error,$ok){
 if($error)echo '<div class="alert alert-danger">'.h($error).'</div>';if($ok)echo '<div class="alert alert-success">'.h($ok).'</div>';
 echo '<p>Los cambios se reciben por HTTPS cuando la APK se conecta. «Pendiente» no significa «Aplicado». El modo activo se muestra cuando la APK lo reporta. El PIN administrativo se puede enviar desde SUPERADMIN mediante una orden separada y protegida. Permisos Android y seguridad LAN siguen siendo locales.</p>';
 echo '<form class="ds-filters" method="get">'.company_filter_control($scope).'<button class="btn btn-primary">Filtrar</button></form>';
 echo '<div class="card table-responsive"><table class="table"><thead><tr><th>Empresa</th><th>Pantalla</th><th>APK</th><th>Modo principal</th><th>Modo activo (reportado)</th><th>Deseada</th><th>Aplicada</th><th>Estado</th><th>Último reporte</th><th>Acciones</th></tr></thead><tbody>';
 $modals='';
 foreach($rows as $d){$id=(int)$d['id'];$rev=(int)($d['revision']??0);$reported=(int)($d['applied_revision']??0);$c=$d['config_json']?ds_config_validate(array_merge(['primary_mode'=>(int)$d['mode']],json_decode($d['config_json'],true))):ds_config_validate(array_merge(ds_config_defaults(),['primary_mode'=>(int)$d['mode']]));
  $actual=$d['actual_json']?ds_config_validate(json_decode($d['actual_json'],true)):null;
  $status=$d['status']??'pending';$state=($status==='rejected' && $reported===$rev)?'Rechazada':($reported===$rev && $actual!==null && $actual===$c?'Aplicada':($reported===$rev && $actual!==null?'Diferente / cambios locales':'Pendiente'));
  echo '<tr><td>'.h($d['company_name']).'</td><td>'.h($d['name']).'</td><td>'.h($d['app_version']??'—').'<small class="d-block">IP: '.h($d['local_ip']??'Sin reporte').'</small>'.'</td><td>'.h([1=>'Cartelera',2=>'Overlay',3=>'Turnos',4=>'Marcador deportivo'][(int)$c['primary_mode']]??'—').'</td><td>'.h([1=>'Cartelera',2=>'Overlay',3=>'Turnos',4=>'Marcador deportivo'][(int)($d['active_mode']??0)]??'Sin reporte').'</td><td>v'.$rev.'</td><td>v'.$reported.'</td><td>'.h($state).'</td><td>'.h($d['reported_at']??'Sin reporte').'</td><td>'.(can('devices.edit')?'<button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#cfg'.$id.'">Configurar</button>':'').'</td></tr>';
  if(!can('devices.edit'))continue;
  $backs=dball('SELECT id,label,source,created_at FROM device_config_backups WHERE tenant_id=? AND device_id=? ORDER BY id DESC LIMIT 20',[(int)$d['tenant_id'],$id]);
  ob_start();echo '<div class="modal fade" id="cfg'.$id.'" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Configuración remota · '.h($d['name']).' · '.h($d['company_name']).'</h5><button class="btn-close" data-bs-dismiss="modal" type="button"></button></div><div class="modal-body">';
  form_open();echo '<input type="hidden" name="action" value="save"><input type="hidden" name="device_id" value="'.$id.'"><input type="hidden" name="revision" value="'.$rev.'">';
  echo '<div class="alert alert-secondary mb-3">Cada modo conserva su configuración. El modo principal funciona como respaldo de los bloques horarios. Para los controles de Turnos y Marcador usa <a href="'.h(url('live-panels.php')).'">Operación de pantalla inteligente</a>.</div>';
  ds_config_form($c,(int)$d['tenant_id'],$id);
  echo '<div class="ds-modal-actions"><button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary" type="submit">Enviar configuración</button></div></form>';
  if(is_super()){
    $pinStatus='Sin PIN asignado';
    if(($d['pin_status']??null)==='applied')$pinStatus='Aplicado en APK';
    elseif(($d['pin_status']??null)==='error')$pinStatus='Error';
    elseif(($d['pin_status']??null)==='pending' && !empty($d['pin_expires_at']) && strtotime((string)$d['pin_expires_at'])>time())$pinStatus='Pendiente de recibir';
    elseif(($d['pin_status']??null)!==null)$pinStatus='Expirado / no aplicado';
    echo '<hr><h6>PIN administrativo de la TV</h6><p>Estado: <strong>'.h($pinStatus).'</strong> · Solicitud #'.(int)($d['pin_revision']??0).'. Solo SUPERADMIN. No se guarda en la configuración compartida, ni en las copias de seguridad.</p>';
    form_open();echo '<input type="hidden" name="action" value="set_pin"><input type="hidden" name="device_id" value="'.$id.'">';
    echo '<div class="row g-2"><div class="col-md-6"><label>Nuevo PIN (6–12 dígitos)</label><input class="form-control" name="new_pin" type="password" inputmode="numeric" pattern="[0-9]{6,12}" minlength="6" maxlength="12" autocomplete="new-password" required></div>';
    echo '<div class="col-md-6"><label>Confirmar nuevo PIN</label><input class="form-control" name="confirm_pin" type="password" inputmode="numeric" pattern="[0-9]{6,12}" minlength="6" maxlength="12" autocomplete="new-password" required></div></div>';
    echo '<p class="small text-muted">Se enviará cuando el equipo esté conectado. Expira a las 48 horas. El estado «Pendiente» no significa que el dispositivo cambió el PIN.</p><button type="submit" class="btn btn-outline-primary">Asignar PIN a esta pantalla</button></form>';
  }
  echo '<hr><h6>Respaldo y recuperación</h6><div class="d-flex gap-2 flex-wrap">';
  foreach(['desired'=>'Respaldar configuración deseada','reported'=>'Respaldar configuración reportada'] as $src=>$label){form_open();echo '<input type="hidden" name="device_id" value="'.$id.'"><input type="hidden" name="action" value="backup"><input type="hidden" name="source" value="'.$src.'"><button class="btn btn-outline-secondary btn-sm">'.h($label).'</button></form>';}
  echo '</div><div class="mt-3"><h6>Respaldos disponibles</h6>';foreach($backs as $b){echo '<div class="d-flex align-items-center gap-2 flex-wrap mb-2"><span>'.h($b['label']).' · '.h($b['source']).'</span><a class="btn btn-outline-secondary btn-sm" href="?export='.(int)$b['id'].'">Exportar JSON</a>';form_open();echo '<input type="hidden" name="device_id" value="'.$id.'"><input type="hidden" name="action" value="restore"><input type="hidden" name="revision" value="'.$rev.'"><input type="hidden" name="backup_id" value="'.(int)$b['id'].'"><button class="btn btn-sm btn-outline-primary" onclick="return confirm(&quot;¿Publicar respaldo como configuración nueva?&quot;)">Restaurar / enviar</button></form></div>';}
  if(!$backs)echo '<p class="text-muted">Todavía no hay respaldos.</p>';
  echo '</div></div></div></div></div>';$modals.=ob_get_clean();
 }
 echo '</tbody></table></div>'.$modals.'<style>[data-tab-panel][hidden]{display:none!important}[data-settings-tabs] input.form-control{max-width:32rem;min-height:48px}[data-settings-tabs] .nav-link{white-space:nowrap;min-height:48px}</style><script defer src="'.h(url('assets/js/settings-tabs.js')).'?v=0430"></script>';
});
