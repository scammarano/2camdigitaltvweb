<?php
require __DIR__.'/_init.php';require_once ROOT.'/app/domain.php';require_once ROOT.'/app/cleanup.php';require_permission('playlists.view');
$t=tenant();$error='';$ok='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 check_csrf();try{
  require_permission('playlists.edit');$target=post_tenant();$action=$_POST['action']??'create';
  if($action==='create'){
   $name=trim($_POST['name']??'');$ori=$_POST['orientation']??'';$layout=$_POST['layout_type']??'';
   if($name===''||!isset(layouts()[$layout])||!isset(orientations()[$ori]))throw new RuntimeException('Nombre/orientación/formato inválido');
   $effect=safe_transition((string)($_POST['transition_effect']??'fade'));$ms=safe_transition_ms($_POST['transition_ms']??600);
   execsql('INSERT INTO playlists(tenant_id,name,orientation,layout_type,transition_effect,transition_ms) VALUES(?,?,?,?,?,?)',[$target,$name,$ori,$layout,$effect,$ms]);
  }elseif($action==='delete_playlist'){
   ds_cleanup_confirm();
   $id=(int)($_POST['playlist_id']??0);$ok=ds_cleanup_delete('playlists',$id,$target);
  }elseif($action==='rename'){
   $id=(int)($_POST['playlist_id']??0);
    $oldRecord=owned_edit_record('playlists',$id);$old=(int)$oldRecord['tenant_id'];
   $name=trim($_POST['name']??'');if(!$name)throw new RuntimeException('Nombre requerido');
   $effect=safe_transition((string)($_POST['transition_effect']??'fade'));$ms=safe_transition_ms($_POST['transition_ms']??600);
   if($old!==$target)assert_unlinked_before_transfer('playlists',$id,$old);
   execsql('UPDATE playlists SET tenant_id=?,name=?,transition_effect=?,transition_ms=? WHERE tenant_id=? AND id=?',[$target,$name,$effect,$ms,$old,$id]);
  }else{
   $pid=(int)($_POST['playlist_id']??0);$parent=dbone('SELECT * FROM playlists WHERE id=? AND tenant_id=?',[$pid,$target]);if(!$parent)throw new RuntimeException('Lista inválida');
   if($action==='add'){
    $choice=explode(':',(string)($_POST['choice']??''),2);$kind=$choice[0]??'';$id=(int)($choice[1]??0);
    $table=$kind==='media'?'media':($kind==='playlist'?'playlists':null);if(!$table)throw new RuntimeException('Tipo inválido');
    $child=dbone("SELECT * FROM $table WHERE id=? AND tenant_id=?",[$id,$target]);if(!$child||!compatible($parent,$child))throw new RuntimeException('El contenido no tiene la orientación o el formato adecuados');
    if($kind==='playlist')validate_graph($target,$pid,$id);
    $row=dbone('SELECT COALESCE(MAX(position),0) n FROM playlist_items WHERE tenant_id=? AND playlist_id=?',[$target,$pid]);$pos=(int)$row['n']+10;
    $effect=(string)($_POST['transition_effect']??'inherit');if($effect!=='inherit'&&!isset(transitions()[$effect]))throw new RuntimeException('Transición inválida');
    $ms=($_POST['transition_ms']??'')===''?null:safe_transition_ms($_POST['transition_ms']);
    execsql('INSERT INTO playlist_items(tenant_id,playlist_id,kind,media_id,child_playlist_id,position,duration_seconds,repeat_count,transition_effect,transition_ms) VALUES(?,?,?,?,?,?,?,?,?,?)',[$target,$pid,$kind,$kind==='media'?$id:null,$kind==='playlist'?$id:null,$pos,max(1,(int)($_POST['duration']??10)),max(1,(int)($_POST['repeat']??1)),$effect,$ms]);
   }elseif(in_array($action,['edit','delete','up','down'],true)){
    $iid=(int)($_POST['item_id']??0);$item=dbone('SELECT * FROM playlist_items WHERE id=? AND playlist_id=? AND tenant_id=?',[$iid,$pid,$target]);if(!$item)throw new RuntimeException('Elemento inválido');
    if($action==='delete')execsql('DELETE FROM playlist_items WHERE id=? AND playlist_id=? AND tenant_id=?',[$iid,$pid,$target]);
    elseif($action==='edit'){
     $effect=(string)($_POST['transition_effect']??'inherit');if($effect!=='inherit'&&!isset(transitions()[$effect]))throw new RuntimeException('Transición inválida');
     $ms=($_POST['transition_ms']??'')===''?null:safe_transition_ms($_POST['transition_ms']);
     execsql('UPDATE playlist_items SET duration_seconds=?,repeat_count=?,transition_effect=?,transition_ms=? WHERE id=? AND playlist_id=? AND tenant_id=?',[max(1,(int)$_POST['duration']),max(1,(int)$_POST['repeat']),$effect,$ms,$iid,$pid,$target]);
    }
    else{
     $all=dball('SELECT id FROM playlist_items WHERE playlist_id=? AND tenant_id=? ORDER BY position,id',[$pid,$target]);$ids=array_map('intval',array_column($all,'id'));$index=array_search($iid,$ids,true);$other=$index+($action==='up'?-1:1);
     if($index!==false && isset($ids[$other])){
      [$ids[$index],$ids[$other]]=[$ids[$other],$ids[$index]];
      foreach($ids as $idx=>$rid)execsql('UPDATE playlist_items SET position=? WHERE id=? AND playlist_id=? AND tenant_id=?',[($idx+1)*10,$rid,$pid,$target]);
     }
    }
   }else throw new RuntimeException('Acción inválida');
  }
  if($ok==='')$ok='Lista actualizada';log_event('playlist_changed',['action'=>$action,'company'=>$target]);
 }catch(Throwable $e){$error=$e instanceof PDOException?fault($e,'playlists'):$e->getMessage();}
}
$query=trim((string)($_GET['q']??''));$fOri=(string)($_GET['orientation']??'');$fLayout=(string)($_GET['layout']??'');
$scope=company_scope(isset($_GET['company'])?(string)$_GET['company']:null);
$where='1=1';$params=[];
if($scope!==null){$where.=' AND p.tenant_id=?';$params[]=$scope;}
if($query!==''){$where.=' AND p.name LIKE ?';$params[]='%'.str_replace(['%','_'],['\\%','\\_'],substr($query,0,120)).'%';}
if($fOri!=='' && isset(orientations()[$fOri])){$where.=' AND p.orientation=?';$params[]=$fOri;}
if($fLayout!=='' && isset(layouts()[$fLayout])){$where.=' AND p.layout_type=?';$params[]=$fLayout;}
$filtered=dball('SELECT p.*,t.name company_name FROM playlists p JOIN tenants t ON t.id=p.tenant_id WHERE '.$where.' ORDER BY t.name,p.name',$params);
$poolWhere=is_super()?'1=1':'tenant_id=?';$poolParams=is_super()?[]:[tenant()];
$lists=dball('SELECT * FROM playlists WHERE '.$poolWhere.' ORDER BY name',$poolParams);
$media=dball('SELECT * FROM media WHERE '.$poolWhere.' ORDER BY name',$poolParams);
$items=dball('SELECT i.*,p.name child_name,m.name media_name FROM playlist_items i LEFT JOIN playlists p ON p.id=i.child_playlist_id AND p.tenant_id=i.tenant_id LEFT JOIN media m ON m.id=i.media_id AND m.tenant_id=i.tenant_id WHERE '.($scope===null?'1=1':'i.tenant_id=?').' ORDER BY i.playlist_id,i.position,i.id',$poolParams);
render('Listas de reproducción',function()use($lists,$filtered,$media,$items,$error,$ok,$query,$fOri,$fLayout,$scope){
 if($error)echo '<div class="alert alert-danger">'.h($error).'</div>';if($ok)echo '<div class="alert alert-success">'.h($ok).'</div>';
 if(can('playlists.edit'))create_modal('Nueva lista',function()use($scope){echo '<input type="hidden" name="action" value="create">'.company_select($scope??0);field('name','Nombre');echo '<label>Orientación</label>'.sel('orientation',orientations(),'landscape').'<label>Formato</label>'.sel('layout_type',layouts()).'<label>Transición predeterminada</label>'.sel('transition_effect',transitions(),'fade');field('transition_ms','Duración del efecto (milisegundos)','number','600');});
 echo '<form method="get" class="ds-filters" aria-label="Filtros de listas">'.company_filter_control($scope).'<div class="ds-filter-field"><label>Nombre de lista</label><input type="search" name="q" class="form-control" value="'.h($query).'" placeholder="Buscar lista"></div><div class="ds-filter-field"><label>Orientación</label><select name="orientation" class="form-select"><option value="">Todas</option>';foreach(orientations() as $key=>$label)echo '<option value="'.h($key).'" '.($fOri===$key?'selected':'').'>'.h($label).'</option>';echo '</select></div><div class="ds-filter-field"><label>Formato</label><select name="layout" class="form-select"><option value="">Todos</option>';foreach(layouts() as $key=>$label)echo '<option value="'.h($key).'" '.($fLayout===$key?'selected':'').'>'.h($label).'</option>';echo '</select></div><div class="ds-filter-actions"><button class="btn btn-primary" type="submit">Filtrar</button><a class="btn btn-outline-secondary" href="'.h(url('playlists.php')).'">Limpiar</a></div></form><p class="ds-filter-count">Listas encontradas: '.count($filtered).'</p>';
 if(!$filtered)echo '<div class="card ds-list-empty">No hay listas que coincidan con los filtros.</div>';
 foreach($filtered as $p){
  $dsModals='';
  $settingsId='playlist-settings-'.(int)$p['id'];
  echo '<div class="card card-body mb-3"><div class="d-flex justify-content-between flex-wrap gap-2"><h5><span class="badge text-bg-light me-2">'.h($p['company_name']).'</span>'.h($p['name']).' — '.h(orientations()[$p['orientation']]??'').', '.h(layouts()[$p['layout_type']]??'').'<small class="d-block text-muted mt-1">Transición: '.h(transitions()[$p['transition_effect']]??'Fundido suave').' · '.(int)$p['transition_ms'].' ms</small></h5><div class="d-flex gap-2">';
  if(can('playlists.edit'))echo '<button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#'.$settingsId.'">Configurar transiciones</button> <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#remove-playlists-'.(int)$p['id'].'">Eliminar lista</button>';
  echo '<a class="btn btn-sm btn-outline-primary" target="_blank" href="'.h(url('preview.php?id='.$p['id'])).'">Vista previa</a></div></div>';
  if(can('playlists.edit')){
    ob_start();echo '<div class="modal fade" id="'.$settingsId.'" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5>Configuración de lista</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">';form_open();
    echo '<input type="hidden" name="action" value="rename">'.company_select((int)$p['tenant_id']).'<input type="hidden" name="playlist_id" value="'.(int)$p['id'].'">';
    field('name','Nombre',(string)'text',(string)$p['name']);
    echo '<p class="ds-hint">Cambiar la empresa de una lista con elementos o asignaciones requiere desvincularlos primero.</p><label class="form-label">Efecto de transición</label>'.sel('transition_effect',transitions(),(string)$p['transition_effect']);
    field('transition_ms','Duración (milisegundos; 0 = corte)','number',(string)$p['transition_ms']);
    echo '<button class="btn btn-primary">Guardar cambios</button></form></div></div></div></div>';
    $dsModals.=ob_get_clean();
    $deleteModal=ds_cleanup_modal('playlists',(int)$p['id'],(int)$p['tenant_id'],(string)$p['name'],
       'Quita esta lista de otras listas, programaciones y pantallas antes de eliminarla. Los medios no se eliminan.');
    $deleteModal=str_replace('name="action" value="delete"','name="action" value="delete_playlist"',$deleteModal);
    $deleteModal=str_replace('name="id" value="'.(int)$p['id'].'"','name="playlist_id" value="'.(int)$p['id'].'"',$deleteModal);
    $dsModals.=$deleteModal;
  }
  echo '<div class="table-responsive"><table class="table table-sm"><thead><tr><th>#</th><th>Elemento</th><th>Segundos</th><th>Repetir</th><th>Transición</th><th>Acciones</th></tr></thead><tbody>';
  foreach($items as $i)if((int)$i['playlist_id']===(int)$p['id']){
   $id='item'.(int)$i['id'];echo '<tr><td>'.(int)$i['position'].'</td><td>'.h($i['kind']==='media'?$i['media_name']:'Lista: '.$i['child_name']).'</td><td>'.(int)$i['duration_seconds'].'</td><td>'.(int)$i['repeat_count'].'</td><td>'.h($i['transition_effect']==='inherit'?'Heredar':(transitions()[$i['transition_effect']]??'Fundido')).'</td><td>';
   if(can('playlists.edit')){
    echo '<button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#'.$id.'">Editar</button>';
    foreach(['up'=>'↑','down'=>'↓','delete'=>'Eliminar'] as $act=>$label){form_open();echo '<input type="hidden" name="playlist_id" value="'.$p['id'].'"><input type="hidden" name="tenant_id" value="'.(int)$p['tenant_id'].'"><input type="hidden" name="item_id" value="'.$i['id'].'"><button class="btn btn-sm btn-outline-secondary" name="action" value="'.$act.'" '.($act==='delete'?'onclick="return confirm(\'¿Eliminar?\')"':'').'>'.$label.'</button></form>';}
   }
   echo '</td></tr>';
   if(can('playlists.edit')){ob_start();echo '<div class="modal fade" id="'.$id.'" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5>Editar elemento</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">';form_open();echo '<input type="hidden" name="action" value="edit"><input type="hidden" name="playlist_id" value="'.$p['id'].'"><input type="hidden" name="tenant_id" value="'.(int)$p['tenant_id'].'"><input type="hidden" name="item_id" value="'.$i['id'].'">';field('duration','Duración imágenes (segundos)','number',(string)$i['duration_seconds']);field('repeat','Repeticiones','number',(string)$i['repeat_count']);echo '<label class="form-label">Efecto para este elemento</label>'.sel('transition_effect',['inherit'=>'Heredar de la lista']+transitions(),(string)$i['transition_effect']);echo '<label class="form-label">Duración propia (ms; vacío = heredar)</label><input class="form-control mb-3" type="number" min="0" max="3000" name="transition_ms" value="'.h($i['transition_ms']===null?'':(string)$i['transition_ms']).'">';echo '<button class="btn btn-primary">Guardar</button></form></div></div></div></div>';$dsModals.=ob_get_clean();}
  }
  echo '</tbody></table></div>'.$dsModals;
  if(can('playlists.edit')){
   echo '<form method="post" class="row g-2"><input type="hidden" name="_csrf" value="'.h(csrf()).'"><input type="hidden" name="action" value="add"><input type="hidden" name="playlist_id" value="'.$p['id'].'"><input type="hidden" name="tenant_id" value="'.(int)$p['tenant_id'].'"><div class="col-md-6"><select class="form-select" name="choice" required><option value="">Medio o lista compatible</option>';
   foreach($media as $m)if((int)$p['tenant_id']===(int)$m['tenant_id'] && compatible($p,$m))echo '<option value="media:'.$m['id'].'">Medio: '.h($m['name']).'</option>';
   foreach($lists as $q)if((int)$q['tenant_id']===(int)$p['tenant_id'] && (int)$q['id']!==(int)$p['id'] && compatible($p,$q))echo '<option value="playlist:'.$q['id'].'">Lista: '.h($q['name']).'</option>';
   echo '</select></div><div class="col-md-2"><input name="duration" type="number" min="1" value="10" title="Segundos" class="form-control"></div><div class="col-md-2"><input name="repeat" type="number" min="1" value="1" title="Repeticiones" class="form-control"></div><div class="col-md-2"><button class="btn btn-primary">Añadir</button></div><div class="col-12"><label class="form-label">Transición del nuevo elemento</label>'.sel('transition_effect',['inherit'=>'Heredar de la lista']+transitions(),'inherit').'</div></form>';
  }
  echo '</div>';
 }
});
