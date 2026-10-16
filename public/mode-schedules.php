<?php
/** Full-width grid + modal editor. Company-scoped mode program. */
require __DIR__.'/_init.php';require_once ROOT.'/app/domain.php';require_once ROOT.'/app/cleanup.php';require_permission('schedules.view');
$error='';$ok='';
function mode_name(int $value):string{return [1=>'Cartelera',2=>'Overlay',3=>'Turnos',4=>'Marcador deportivo'][$value]??'Inválido';}
function mode_days(int $mask):string {
 if($mask===127)return 'Todos';if($mask===31)return 'Lunes–viernes';if($mask===96)return 'Fines de semana';
 $out=[];foreach(['L','M','X','J','V','S','D'] as $i=>$d)if($mask&(1<<$i))$out[]=$d;return implode(' ',$out);
}
if($_SERVER['REQUEST_METHOD']==='POST'){
 check_csrf();try{
   require_permission('schedules.edit');$act=(string)($_POST['action']??'');$id=(int)($_POST['id']??0);
   $tenantId=post_tenant();$old=null;
   if($act!=='create'){
     $old=is_super()?dbone('SELECT * FROM device_mode_blocks WHERE id=?',[$id]):dbone('SELECT * FROM device_mode_blocks WHERE id=? AND tenant_id=?',[$id,tenant()]);
     if(!$old)throw new RuntimeException('Bloque inexistente o sin autorización');
   }
   if($act==='delete'){
     ds_cleanup_confirm();$ok=ds_cleanup_delete('mode_blocks',$id,(int)$old['tenant_id']);
   }else{
     if(!in_array($act,['create','edit'],true))throw new RuntimeException('Acción inválida');
     $device=(int)($_POST['device_id']??0);$mode=(int)($_POST['mode']??0);
     if(!dbone('SELECT id FROM devices WHERE id=? AND tenant_id=?',[$device,$tenantId]))throw new RuntimeException('La pantalla no pertenece a la empresa seleccionada');
     if($mode<1||$mode>4)throw new RuntimeException('Modo inválido');
     $mask=0;foreach((array)($_POST['days']??[]) as $day){if(!ctype_digit((string)$day)||$day>6)continue;$mask|=(1<<((int)$day));}
     if(!$mask)throw new RuntimeException('Selecciona al menos un día');
     $start=(string)($_POST['start_time']??'');$end=(string)($_POST['end_time']??'');
     if(!preg_match('/^\d{2}:\d{2}$/D',$start)||!preg_match('/^\d{2}:\d{2}$/D',$end)||$start>=$end)throw new RuntimeException('La hora final debe ser mayor que la inicial; divide bloques que crucen medianoche');
     $from=(string)($_POST['valid_from']??'');$until=(string)($_POST['valid_until']??'');
     foreach([$from,$until] as $date)if($date!==''&&!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$date))throw new RuntimeException('Fecha inválida');
     if($from&&$until&&$from>$until)throw new RuntimeException('Vigencia incorrecta');
     $priority=max(-100,min(100,(int)($_POST['priority']??0)));$enabled=isset($_POST['enabled'])?1:0;
     if($act==='create')execsql('INSERT INTO device_mode_blocks(tenant_id,device_id,mode,days_mask,start_time,end_time,valid_from,valid_until,priority,enabled) VALUES(?,?,?,?,?,?,?,?,?,?)',[$tenantId,$device,$mode,$mask,$start,$end,$from?:null,$until?:null,$priority,$enabled]);
     else execsql('UPDATE device_mode_blocks SET tenant_id=?,device_id=?,mode=?,days_mask=?,start_time=?,end_time=?,valid_from=?,valid_until=?,priority=?,enabled=? WHERE id=? AND tenant_id=?',[$tenantId,$device,$mode,$mask,$start,$end,$from?:null,$until?:null,$priority,$enabled,$id,(int)$old['tenant_id']]);
     $ok='Bloque de modo guardado';
   }
   log_event('mode_schedule_changed',['action'=>$act,'company'=>$tenantId]);
 }catch(Throwable $e){$error=$e instanceof PDOException?fault($e,'mode_schedules'):$e->getMessage();}
}
$scope=company_scope(isset($_GET['company'])?(string)$_GET['company']:null);
$w=$scope===null?'1=1':'b.tenant_id=?';$params=$scope===null?[]:[$scope];
$rows=dball('SELECT b.*,d.name device_name,t.name company_name FROM device_mode_blocks b JOIN devices d ON d.id=b.device_id AND d.tenant_id=b.tenant_id JOIN tenants t ON t.id=b.tenant_id WHERE '.$w.' ORDER BY t.name,d.name,b.priority DESC,b.start_time', $params);
$devices=dball('SELECT id,tenant_id,name FROM devices WHERE '.(is_super()?'1=1':'tenant_id=?').' ORDER BY name',is_super()?[]:[tenant()]);
function mode_form(string $action,?array $row,array $devices,int $defaultCompany):void {
  $tid=(int)($row['tenant_id']??$defaultCompany);form_open();echo '<input type="hidden" name="action" value="'.h($action).'">';
  if($row)echo '<input type="hidden" name="id" value="'.(int)$row['id'].'">';
  echo company_select($tid,'data-mode-company');
  echo '<label class="form-label">Pantalla</label><select class="form-select mb-3" data-mode-device name="device_id" required>';
  foreach($devices as $d)echo '<option value="'.(int)$d['id'].'" data-tenant="'.(int)$d['tenant_id'].'"'.((int)($row['device_id']??0)===(int)$d['id']?' selected':'').'>'.h($d['name']).'</option>';echo '</select>';
  echo '<label class="form-label">Modo durante este bloque</label><select class="form-select mb-3" name="mode">';
  foreach([1=>'Cartelera',2=>'Overlay (experimental)',3=>'Turnos',4=>'Marcador deportivo'] as $id=>$n)echo '<option value="'.$id.'"'.((int)($row['mode']??1)===$id?' selected':'').'>'.h($n).'</option>';echo '</select>';
  echo '<label class="form-label">Días</label><div class="d-flex flex-wrap gap-3 mb-3">';
  foreach(['L','M','X','J','V','S','D'] as $i=>$d)echo '<label><input type="checkbox" class="form-check-input" name="days[]" value="'.$i.'"'.(((int)($row['days_mask']??127)&(1<<$i))?' checked':'').'> '.$d.'</label>';echo '</div>';
  echo '<div class="row mb-3"><div class="col"><label>Inicio</label><input type="time" class="form-control" name="start_time" value="'.h(substr((string)($row['start_time']??'08:00'),0,5)).'" required></div><div class="col"><label>Fin</label><input type="time" class="form-control" name="end_time" value="'.h(substr((string)($row['end_time']??'18:00'),0,5)).'" required></div></div>';
  echo '<div class="row mb-3"><div class="col"><label>Desde</label><input class="form-control" type="date" name="valid_from" value="'.h((string)($row['valid_from']??'')).'"></div><div class="col"><label>Hasta</label><input class="form-control" type="date" name="valid_until" value="'.h((string)($row['valid_until']??'')).'"></div></div>';
  echo '<label>Prioridad</label><input type="number" name="priority" class="form-control mb-3" min="-100" max="100" value="'.(int)($row['priority']??0).'">';
  echo '<label class="mb-3"><input type="checkbox" name="enabled" value="1"'.((int)($row['enabled']??1)===1?' checked':'').'> Bloque activo</label>';
  echo '<div class="alert alert-info">En horarios superpuestos gana el bloque con mayor prioridad y luego el ID más reciente. Si no hay ninguno, se usa el modo principal de la pantalla.</div>';
  echo '<div class="ds-modal-actions"><button type="button" data-bs-dismiss="modal" class="btn btn-light">Cancelar</button><button class="btn btn-primary" type="submit">Guardar bloque</button></div></form>';
}
render('Programación de modos',function()use($rows,$devices,$scope,$error,$ok){
 if($error)echo '<div class="alert alert-danger">'.h($error).'</div>';if($ok)echo '<div class="alert alert-success">'.h($ok).'</div>';
 if(can('schedules.edit'))echo '<div class="ds-actions"><button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modeCreate">+ Nuevo bloque de modo</button></div>';
 echo '<p>Alterna Cartelera, Overlay y Pantalla inteligente en la misma pantalla según los días y horas. El dispositivo debe tener los permisos necesarios.</p>';
 echo '<form class="ds-filters" method="get">'.company_filter_control($scope).'<button class="btn btn-primary">Filtrar</button></form>';
 echo '<div class="card table-responsive"><table class="table"><thead><tr><th>Empresa</th><th>Pantalla</th><th>Modo</th><th>Días</th><th>Horario</th><th>Prioridad</th><th>Activo</th><th>Acciones</th></tr></thead><tbody>';
 $modals='';foreach($rows as $r){$id=(int)$r['id'];echo '<tr><td>'.h($r['company_name']).'</td><td>'.h($r['device_name']).'</td><td>'.h(mode_name((int)$r['mode'])).'</td><td>'.h(mode_days((int)$r['days_mask'])).'</td><td>'.h(substr($r['start_time'],0,5).'–'.substr($r['end_time'],0,5)).'</td><td>'.(int)$r['priority'].'</td><td>'.((int)$r['enabled']?'Sí':'No').'</td><td>';
 if(can('schedules.edit')){echo '<button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#m'.$id.'">Editar</button> ';echo '<button class="btn btn-sm btn-outline-danger" type="button" data-bs-toggle="modal" data-bs-target="#remove-mode_blocks-'.$id.'">Eliminar</button>';}
 echo '</td></tr>';
 if(can('schedules.edit')){
        $modals.=ds_cleanup_modal('mode_blocks',$id,(int)$r['tenant_id'],(string)$r['device_name'].' · '.mode_name((int)$r['mode']));
        ob_start();echo '<div class="modal fade" id="m'.$id.'" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h5>Editar bloque</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">';mode_form('edit',$r,$devices,$scope??tenant());echo '</div></div></div></div>';$modals.=ob_get_clean();}
 }
 echo '</tbody></table></div>'.$modals;
 if(can('schedules.edit')){echo '<div class="modal fade" id="modeCreate" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h5>Nuevo bloque de modo</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">';mode_form('create',null,$devices,$scope??tenant());echo '</div></div></div></div>';}
 echo '<script defer src="'.h(url('assets/js/mode-schedules.js')).'"></script>';
});
