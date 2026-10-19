<?php
/** Per-device diagnostic and on-demand synchronization. Full-width grid, tenant-bound. */
declare(strict_types=1);
require __DIR__.'/_init.php';
require_permission('devices.view');
$error='';$notice='';
$company=is_super()?(int)($_GET['tenant_id']??$_POST['tenant_id']??tenant()):tenant();
if($company<1||(!is_super()&&$company!==tenant())){http_response_code(403);exit('Empresa no permitida');}
if(!dbone('SELECT id FROM tenants WHERE id=?',[$company])){http_response_code(404);exit('Empresa inexistente');}
$devices=dball('SELECT d.id,d.name,d.orientation,d.mode,d.last_seen,s.requested_version,s.applied_version,s.requested_at,s.applied_at FROM devices d LEFT JOIN device_sync_state s ON s.device_id=d.id AND s.tenant_id=d.tenant_id WHERE d.tenant_id=? ORDER BY d.name',[$company]);
$id=(int)($_GET['device_id']??$_POST['device_id']??0);
if($id>0&&!dbone('SELECT id FROM devices WHERE id=? AND tenant_id=?',[$id,$company])){http_response_code(404);exit('Pantalla no encontrada en la empresa');}
if($_SERVER['REQUEST_METHOD']==='POST'){
 check_csrf();
 if(!can('devices.edit')){http_response_code(403);exit('Sin permiso de edición');}
 if($id<=0){$error='Selecciona una pantalla primero.';}
 else {
  try{
   $action=(string)($_POST['action']??'');
   if($action!=='sync')throw new RuntimeException('Acción inválida');
   execsql('INSERT INTO device_sync_state(device_id,tenant_id,requested_version,requested_by,requested_at) VALUES(?,?,1,?,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE requested_version=requested_version+1,requested_by=VALUES(requested_by),requested_at=UTC_TIMESTAMP()',[$id,$company,(int)user()['id']]);
   log_event('device_sync_requested',['device_id'=>$id,'tenant_id'=>$company]);
   $notice='Sincronización solicitada. El equipo la detectará en su siguiente consulta (habitualmente 15 segundos), si está conectado.';
  }catch(Throwable $e){$error=fault($e,'device_sync_request');}
 }
 $devices=dball('SELECT d.id,d.name,d.orientation,d.mode,d.last_seen,s.requested_version,s.applied_version,s.requested_at,s.applied_at FROM devices d LEFT JOIN device_sync_state s ON s.device_id=d.id AND s.tenant_id=d.tenant_id WHERE d.tenant_id=? ORDER BY d.name',[$company]);
}
$level=(string)($_GET['level']??'all');if(!in_array($level,['all','debug','info','warn','error','fatal'],true))$level='all';
$where='WHERE e.tenant_id=?';$args=[$company];
if($id>0){$where.=' AND e.device_id=?';$args[]=$id;}
if($level!=='all'){$where.=' AND e.level=?';$args[]=$level;}
$events=dball("SELECT e.*,d.name AS device_name,t.name AS tenant_name FROM device_diag_events e JOIN devices d ON d.id=e.device_id AND d.tenant_id=e.tenant_id JOIN tenants t ON t.id=e.tenant_id $where ORDER BY e.id DESC LIMIT 300",$args);
if(isset($_GET['export'])){
 $format=(string)$_GET['export'];
 if($format!=='json') {http_response_code(400);exit('Formato inválido');}
 // Stream EVERY event matching the authorized device/company filter. The grid
 // intentionally stays at 300 recent entries; an export must not inherit that cap.
 header('Content-Type: application/json; charset=utf-8');
 header('Content-Disposition: attachment; filename="signage-device-logs.json"');
 header('Cache-Control: no-store, private');
 header('X-Content-Type-Options: nosniff');
 $stmt=db()->prepare("SELECT e.*,d.name AS device_name,t.name AS tenant_name FROM device_diag_events e JOIN devices d ON d.id=e.device_id AND d.tenant_id=e.tenant_id JOIN tenants t ON t.id=e.tenant_id $where ORDER BY e.id ASC");
 $stmt->execute($args);
 $header=['company_id'=>$company,'device_id'=>$id?:null,'level'=>$level,'exported_at'=>gmdate('c')];
 echo substr(json_encode($header,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE|JSON_THROW_ON_ERROR),0,-1).',"events":[';
 $first=true;
 while($row=$stmt->fetch(PDO::FETCH_ASSOC)){
   if(!$first)echo ',';
   echo json_encode($row,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE|JSON_THROW_ON_ERROR);
   $first=false;
 }
 echo ']}';
 exit;
}
$companies=is_super()?dball('SELECT id,name FROM tenants ORDER BY name'):[];
render('Diagnóstico de pantallas',function()use($company,$companies,$devices,$id,$level,$events,$notice,$error){
 if($error)echo '<div class="alert alert-danger">'.h($error).'</div>';
 if($notice)echo '<div class="alert alert-success">'.h($notice).'</div>';
 echo '<p class="text-muted">Los eventos recibidos se asocian a la empresa y pantalla autenticadas. Los registros pendientes de un equipo sin conexión llegarán cuando pueda comunicarse con el portal.</p>';
 echo '<form method="get" class="ds-filters">';
 if(is_super()){
  echo '<div class="ds-filter-field"><label>Empresa</label><select class="form-select" name="tenant_id" onchange="this.form.submit()">';
  foreach($companies as $c)echo '<option value="'.(int)$c['id'].'"'.($company===(int)$c['id']?' selected':'').'>'.h($c['name']).'</option>';
  echo '</select></div>';
 }
 echo '<div class="ds-filter-field"><label>Pantalla</label><select class="form-select" name="device_id"><option value="0">Todas</option>';
 foreach($devices as $d)echo '<option value="'.(int)$d['id'].'"'.($id===(int)$d['id']?' selected':'').'>'.h($d['name']).'</option>';
 echo '</select></div><div class="ds-filter-field"><label>Severidad</label><select class="form-select" name="level">';
 foreach(['all'=>'Todas','debug'=>'Debug','info'=>'Info','warn'=>'Advertencias','error'=>'Errores','fatal'=>'Críticos'] as $k=>$v)echo '<option value="'.h($k).'"'.($level===$k?' selected':'').'>'.h($v).'</option>';
 echo '</select></div><button class="btn btn-primary" type="submit">Filtrar</button></form>';
 echo '<div class="ds-actions"><a class="btn btn-outline-primary" href="'.h(url('device-diagnostics.php?tenant_id='.$company.'&device_id='.$id.'&level='.$level.'&export=json')).'">Exportar historial completo (JSON)</a></div>';
 echo '<div class="card table-responsive"><table class="table"><thead><tr><th>Pantalla</th><th>Último contacto</th><th>Sincronización solicitada</th><th>Última aplicada</th><th>Acción</th></tr></thead><tbody>';
 foreach($devices as $d){
  $did=(int)$d['id'];$requested=(int)($d['requested_version']??0);$applied=(int)($d['applied_version']??0);
  echo '<tr><td>'.h($d['name']).'</td><td>'.h($d['last_seen']??'Nunca').'</td><td>'.h((string)$requested).' '.($requested>$applied?'<span class="badge bg-warning text-dark">Pendiente</span>':'').'</td><td>'.h((string)$applied).'</td><td>';
  if(can('devices.edit')){
   echo '<form method="post"><input type="hidden" name="_csrf" value="'.h(csrf()).'"><input type="hidden" name="tenant_id" value="'.$company.'"><input type="hidden" name="device_id" value="'.$did.'"><input type="hidden" name="action" value="sync"><button class="btn btn-sm btn-primary" type="submit">Sincronizar ahora</button></form>';
  }
  echo '</td></tr>';
 }
 echo '</tbody></table></div>';
 echo '<h2 class="h5 mt-4">Eventos de diagnóstico (últimos 300)</h2><div class="card table-responsive"><table class="table"><thead><tr><th>Fecha equipo</th><th>Recibido</th><th>Pantalla</th><th>Nivel</th><th>Área</th><th>Mensaje</th><th>Detalles</th></tr></thead><tbody>';
 foreach($events as $e){echo '<tr><td>'.h($e['event_at']).'</td><td>'.h($e['received_at']).'</td><td>'.h($e['device_name']).'</td><td>'.h($e['level']).'</td><td>'.h($e['area']).'</td><td>'.h($e['message']).'</td><td><details><summary>Ver</summary><pre style="white-space:pre-wrap;min-width:220px">'.h($e['details']).'</pre></details></td></tr>';}
 if(!$events)echo '<tr><td colspan="7" class="text-center text-muted">Todavía no llegaron logs desde las APK.</td></tr>';
 echo '</tbody></table></div>';
});
