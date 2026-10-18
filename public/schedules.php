<?php
/** Company-scoped scheduling, with editable multi-playlist ordered sequences. */
require __DIR__.'/_init.php';
require_once ROOT.'/app/domain.php';
require_once ROOT.'/app/cleanup.php';
require_permission('schedules.view');
$error='';$ok='';
function schedule_days_text(int $mask):string {
    $names=['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'];
    if($mask===127)return 'Todos los días';
    if($mask===31)return 'Lunes a viernes';
    if($mask===96)return 'Fin de semana';
    $days=[];foreach($names as $n=>$v)if($mask & (1<<$n))$days[]=$v;
    return implode(', ',$days);
}
function schedule_time(string $value):string {
    if(!preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D',$value))throw new RuntimeException('Horas inválidas (HH:MM)');
    return $value;
}
function schedule_date(string $value):?string {
    if($value==='')return null;
    $d=DateTimeImmutable::createFromFormat('!Y-m-d',$value);
    if(!$d || $d->format('Y-m-d')!==$value)throw new RuntimeException('Fecha de vigencia inválida');
    return $value;
}
if($_SERVER['REQUEST_METHOD']==='POST') {
    check_csrf();
    try {
        require_permission('schedules.edit');
        $target=post_tenant();$action=(string)($_POST['action']??'');$id=(int)($_POST['id']??0);
        if($action==='delete') {
            ds_cleanup_confirm();$ok=ds_cleanup_delete('schedules',$id,$target);
        } elseif($action==='create'||$action==='edit') {
            if($action==='edit'){$previous=owned_edit_record('schedules',$id);$old=(int)$previous['tenant_id'];}
            $device=(int)($_POST['device_id']??0);
            $d=dbone('SELECT id,orientation FROM devices WHERE id=? AND tenant_id=?',[$device,$target]);
            if(!$d)throw new RuntimeException('Pantalla no válida para esta empresa');
            $raw=$_POST['playlists']??[];
            if(!is_array($raw)||count($raw)<1||count($raw)>50)throw new RuntimeException('Seleccione entre 1 y 50 listas en orden');
            $ids=[];
            foreach($raw as $rawId) {
                if(!is_scalar($rawId)||!ctype_digit((string)$rawId)||(int)$rawId<1)throw new RuntimeException('Lista inválida');
                $ids[]=(int)$rawId;
            }
            $unique=array_values(array_unique($ids));
            $placeholders=implode(',',array_fill(0,count($unique),'?'));
            $valid=dball('SELECT id,orientation FROM playlists WHERE tenant_id=? AND id IN ('.$placeholders.')',array_merge([$target],$unique));
            if(count($valid)!==count($unique))throw new RuntimeException('Una lista no pertenece a la empresa de la pantalla');
            foreach($valid as $p)if(!in_array($p['orientation'],['any',$d['orientation']],true))throw new RuntimeException('Una lista tiene orientación incompatible con la pantalla');
            $mask=0;
            if(!is_array($_POST['days']??null))throw new RuntimeException('Seleccione los días');
            foreach($_POST['days'] as $day) {
                if(!ctype_digit((string)$day)||(int)$day>6)throw new RuntimeException('Día inválido');
                $mask|=1<<(int)$day;
            }
            if(!$mask)throw new RuntimeException('Seleccione al menos un día');
            $start=schedule_time((string)($_POST['start_time']??''));
            $end=schedule_time((string)($_POST['end_time']??''));
            if($start>=$end)throw new RuntimeException('La hora final debe ser posterior a la inicial');
            $validFrom=schedule_date((string)($_POST['valid_from']??''));
            $validUntil=schedule_date((string)($_POST['valid_until']??''));
            if($validFrom!==null&&$validUntil!==null&&$validFrom>$validUntil)throw new RuntimeException('La fecha de fin no puede ser anterior al inicio');
            $priority=filter_var($_POST['priority']??0,FILTER_VALIDATE_INT);
            if($priority===false||$priority< -100||$priority>100)throw new RuntimeException('Prioridad inválida (-100 a 100)');
            db()->beginTransaction();
            try {
                if($action==='create') {
                    execsql('INSERT INTO schedules(tenant_id,device_id,playlist_id,days_mask,start_time,end_time,valid_from,valid_until,priority) VALUES(?,?,?,?,?,?,?,?,?)',[$target,$device,$ids[0],$mask,$start,$end,$validFrom,$validUntil,$priority]);
                    $id=(int)db()->lastInsertId();
                } else {
                    execsql('DELETE FROM schedule_items WHERE schedule_id=? AND tenant_id=?',[$id,$old]);
                    execsql('UPDATE schedules SET tenant_id=?,device_id=?,playlist_id=?,days_mask=?,start_time=?,end_time=?,valid_from=?,valid_until=?,priority=? WHERE id=? AND tenant_id=?',[$target,$device,$ids[0],$mask,$start,$end,$validFrom,$validUntil,$priority,$id,$old]);
                }
                foreach($ids as $position=>$pid)execsql('INSERT INTO schedule_items(tenant_id,schedule_id,playlist_id,position) VALUES(?,?,?,?)',[$target,$id,$pid,$position]);
                db()->commit();
            }catch(Throwable $e){db()->rollBack();throw $e;}
            $ok=$action==='create'?'Programación creada':'Programación actualizada';
            log_event('schedule_saved',['id'=>$id,'action'=>$action,'company'=>$target,'lists'=>count($ids)]);
        }else throw new RuntimeException('Acción no permitida');
    }catch(Throwable $e){$error=$e instanceof PDOException?fault($e,'schedules'):$e->getMessage();}
}
$scope=company_scope(isset($_GET['company'])?(string)$_GET['company']:null);
$filterScreen=(int)($_GET['device']??0);
$scopeWhere=$scope===null?'1=1':'s.tenant_id=?';$scopeParams=$scope===null?[]:[$scope];
if($filterScreen>0){$scopeWhere.=' AND s.device_id=?';$scopeParams[]=$filterScreen;}
$rows=dball('SELECT s.*,d.name device_name,d.orientation device_orientation,t.name company_name FROM schedules s JOIN devices d ON d.id=s.device_id AND d.tenant_id=s.tenant_id JOIN tenants t ON t.id=s.tenant_id WHERE '.$scopeWhere.' ORDER BY t.name,s.device_id,s.start_time,s.priority DESC,s.id', $scopeParams);
$poolWhere=is_super()?'1=1':'tenant_id=?';$poolParams=is_super()?[]:[tenant()];
$devices=dball('SELECT id,tenant_id,name,orientation FROM devices WHERE '.$poolWhere.' ORDER BY name',$poolParams);
$lists=dball('SELECT id,tenant_id,name,orientation,layout_type FROM playlists WHERE '.$poolWhere.' ORDER BY name',$poolParams);
$allItems=dball('SELECT i.schedule_id,i.playlist_id,i.position,p.name FROM schedule_items i JOIN playlists p ON p.id=i.playlist_id AND p.tenant_id=i.tenant_id WHERE '.($scope===null?'1=1':'i.tenant_id=?').' ORDER BY i.schedule_id,i.position,i.id',$scope===null?[]:[$scope]);
$bySchedule=[];foreach($allItems as $item)$bySchedule[(int)$item['schedule_id']][]=['id'=>(int)$item['playlist_id'],'name'=>$item['name']];
// Some legacy blocks have only schedules.playlist_id and no schedule_items.
foreach($rows as $r)if(empty($bySchedule[(int)$r['id']])) {
    foreach($lists as $p)if((int)$p['id']===(int)$r['playlist_id'] && (int)$p['tenant_id']===(int)$r['tenant_id']){$bySchedule[(int)$r['id']]=[['id'=>(int)$p['id'],'name'=>$p['name']]];break;}
}
$companies=is_super()?dball('SELECT id,name FROM tenants ORDER BY name'):[];
/** Draw modal content outside table markup; all controls are scoped by tenant. */
function schedule_form(string $action,array $s,array $devices,array $lists,array $companies,array $currentList):void {
    $tid=(int)($s['tenant_id']??tenant());$isEdit=$action==='edit';
    form_open();
    echo '<input type="hidden" name="action" value="'.h($action).'">';
    if($isEdit)echo '<input type="hidden" name="id" value="'.(int)$s['id'].'">';
    echo company_select($tid,'data-schedule-company');
    echo '<label class="form-label">Pantalla</label><select name="device_id" data-schedule-device class="form-select mb-3" required>';
    foreach($devices as $d)echo '<option value="'.(int)$d['id'].'" data-tenant="'.(int)$d['tenant_id'].'" data-orientation="'.h($d['orientation']).'"'.((int)($s['device_id']??0)===(int)$d['id']?' selected':'').'>'.h($d['name']).' ('.h(orientations()[$d['orientation']]??$d['orientation']).')</option>';
    echo '</select><label class="form-label">Secuencia de listas (en orden)</label>';
    echo '<div class="input-group mb-2"><select data-schedule-add-select class="form-select"><option value="">Elegir lista para agregar</option>';
    foreach($lists as $p)echo '<option value="'.(int)$p['id'].'" data-tenant="'.(int)$p['tenant_id'].'" data-orientation="'.h($p['orientation']).'">'.h($p['name']).' · '.h(orientations()[$p['orientation']]??$p['orientation']).'</option>';
    echo '</select><button type="button" class="btn btn-outline-primary" data-schedule-add>Añadir</button></div>';
    echo '<ol class="ds-schedule-sequence list-group list-group-numbered mb-3" data-schedule-sequence>';
    foreach($currentList as $p){$pid=(int)$p['id'];echo '<li class="list-group-item d-flex align-items-center justify-content-between gap-2" data-list-id="'.$pid.'"><input type="hidden" name="playlists[]" value="'.$pid.'"><span class="flex-grow-1">'.h($p['name']).'</span><button type="button" data-sequence-move="up" class="btn btn-sm btn-outline-secondary" aria-label="Subir">↑</button><button type="button" data-sequence-move="down" class="btn btn-sm btn-outline-secondary" aria-label="Bajar">↓</button><button type="button" data-sequence-remove class="btn btn-sm btn-outline-danger" aria-label="Quitar">×</button></li>';}
    echo '</ol><div class="form-text mb-3">Puedes añadir varias listas, repetirlas, quitarlas y cambiar el orden con las flechas.</div>';
    echo '<label class="form-label">Días de la semana</label><div class="mb-3 d-flex gap-3 flex-wrap">';
    foreach(['L','M','X','J','V','S','D'] as $i=>$name)echo '<label class="form-check-label"><input type="checkbox" class="form-check-input me-1" name="days[]" value="'.$i.'"'.((((int)($s['days_mask']??127))&(1<<$i))?' checked':'').'>'.h($name).'</label>';
    echo '</div><div class="row g-2 mb-3"><div class="col-sm-6"><label class="form-label">Hora inicial</label><input type="time" name="start_time" class="form-control" value="'.h(substr((string)($s['start_time']??'08:00'),0,5)).'" required></div><div class="col-sm-6"><label class="form-label">Hora final</label><input type="time" name="end_time" class="form-control" value="'.h(substr((string)($s['end_time']??'18:00'),0,5)).'" required></div></div>';
    echo '<div class="row g-2 mb-3"><div class="col-sm-6"><label class="form-label">Válida desde (opcional)</label><input type="date" name="valid_from" value="'.h((string)($s['valid_from']??'')).'" class="form-control"></div><div class="col-sm-6"><label class="form-label">Válida hasta (opcional)</label><input type="date" name="valid_until" value="'.h((string)($s['valid_until']??'')).'" class="form-control"></div></div>';
    echo '<label class="form-label">Prioridad</label><input type="number" name="priority" min="-100" max="100" class="form-control mb-3" value="'.(int)($s['priority']??0).'">';
    echo '<p class="small text-muted">No se admiten bloques que crucen medianoche en esta versión. Los horarios se interpretan según la configuración del reproductor.</p>';
    echo '<div class="ds-modal-actions"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary">'.($isEdit?'Guardar cambios':'Crear bloque').'</button></div></form>';
}
render('Programación',function()use($rows,$devices,$lists,$companies,$bySchedule,$error,$ok,$scope,$filterScreen){
    if($error)echo '<div class="alert alert-danger">'.h($error).'</div>';
    if($ok)echo '<div class="alert alert-success">'.h($ok).'</div>';
    if(can('schedules.edit'))echo '<div class="ds-actions"><button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newSchedule">+ Nueva programación</button></div>';
    echo '<form method="get" class="ds-filters" aria-label="Filtros de programación">'.company_filter_control($scope).'<div class="ds-filter-field"><label>Pantalla</label><select name="device" class="form-select"><option value="">Todas las pantallas</option>';
    foreach($devices as $d)echo '<option value="'.(int)$d['id'].'"'.($filterScreen===(int)$d['id']?' selected':'').'>'.h($d['name']).'</option>';
    echo '</select></div><div class="ds-filter-actions"><button type="submit" class="btn btn-primary">Filtrar</button><a class="btn btn-outline-secondary" href="'.h(url('schedules.php')).'">Limpiar</a></div></form>';
    echo '<p class="ds-filter-count">Programaciones encontradas: '.count($rows).'</p>';
    echo '<div class="card table-responsive"><table class="table"><thead><tr><th>Empresa</th><th>Pantalla</th><th>Listas en secuencia</th><th>Días</th><th>Horario</th><th>Vigencia</th><th>Prioridad</th><th>Acciones</th></tr></thead><tbody>';
    foreach($rows as $s){$selected=$bySchedule[(int)$s['id']]??[];$names=array_column($selected,'name');
        echo '<tr><td>'.h($s['company_name']).'</td><td>'.h($s['device_name']).'</td><td>'.h(implode(' → ',$names)).'</td><td>'.h(schedule_days_text((int)$s['days_mask'])).'</td><td>'.h(substr($s['start_time'],0,5).'–'.substr($s['end_time'],0,5)).'</td><td>'.h(($s['valid_from']??'Sin inicio').' / '.($s['valid_until']??'Sin fin')).'</td><td>'.(int)$s['priority'].'</td><td>';
        if(can('schedules.edit')){echo '<button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#sch'.(int)$s['id'].'">Editar</button> ';echo '<button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#remove-schedules-'.(int)$s['id'].'">Eliminar</button>';}
        echo '</td></tr>';
    }
    echo '</tbody></table></div>';
    if(!count($rows))echo '<div class="alert alert-info mt-3">No hay programaciones con esos filtros.</div>';
    if(can('schedules.edit')) {
        echo '<div class="modal fade" id="newSchedule" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h5>Nueva programación</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div><div class="modal-body" data-schedule-editor>';
        schedule_form('create',['tenant_id'=>$scope??tenant(),'days_mask'=>127],$devices,$lists,$companies,[]);
        echo '</div></div></div></div>';
        foreach($rows as $s) {
            echo ds_cleanup_modal('schedules',(int)$s['id'],(int)$s['tenant_id'],
                (string)$s['device_name'].' · '.substr((string)$s['start_time'],0,5).'–'.substr((string)$s['end_time'],0,5),
                'Se quitará este bloque y su secuencia de playlists, pero no se borrarán los archivos de medios.');
            echo '<div class="modal fade" id="sch'.(int)$s['id'].'" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h5>Editar programación · '.h($s['device_name']).'</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div><div class="modal-body" data-schedule-editor>';
            schedule_form('edit',$s,$devices,$lists,$companies,$bySchedule[(int)$s['id']]??[]);
            echo '</div></div></div></div>';
        }
    }
    echo '<script defer src="'.h(url('assets/js/schedules.js')).'?v=0.3.8"></script>';
});
