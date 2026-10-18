<?php
require __DIR__.'/_init.php';require_once ROOT.'/app/domain.php';require_once ROOT.'/app/cleanup.php';require_once ROOT.'/app/playlist_copy.php';require_once ROOT.'/app/playlist_editor.php';require_once ROOT.'/app/media_separator.php';require_once ROOT.'/app/playlist_modes.php';require_permission('playlists.view');
$t=tenant();$error='';$ok='';$createdCopy=null;
if($_SERVER['REQUEST_METHOD']==='POST'){
 check_csrf();try{
  $target=post_tenant();$action=$_POST['action']??'create';if($action!=='share')require_permission('playlists.edit');
  if($action==='share'){ds_content_share('playlists',(int)($_POST['id']??0),$target,isset($_POST['is_shared']));
  }elseif($action==='create'){
   $name=trim($_POST['name']??'');$ori=$_POST['orientation']??'';$layout=$_POST['layout_type']??'';
   if($name===''||!isset(layouts()[$layout])||!isset(orientations()[$ori]))throw new RuntimeException('Nombre/orientación/formato inválido');
   $effect=safe_transition((string)($_POST['transition_effect']??'fade'));$ms=safe_transition_ms($_POST['transition_ms']??600);
   execsql('INSERT INTO playlists(tenant_id,name,orientation,layout_type,transition_effect,transition_ms,mode_mask) VALUES(?,?,?,?,?,?,?)',[$target,$name,$ori,$layout,$effect,$ms,ds_playlist_mode_mask($_POST)]);
  }elseif($action==='duplicate'){
   $result=ds_playlist_duplicate((int)($_POST['playlist_id']??0),$target,(string)($_POST['copy_name']??''));
   $createdCopy=['company'=>$target,'id'=>$result['id']];
   $ok='Copia creada: '.(int)$result['lists'].' lista(s), '.(int)$result['items'].' renglón(es) y '.(int)$result['media_copied'].' archivo(s) copiados. La lista original y sus asignaciones se conservaron.';
  }elseif($action==='delete_playlist'){
   ds_cleanup_confirm();
   $id=(int)($_POST['playlist_id']??0);$ok=ds_cleanup_delete('playlists',$id,$target);
  }elseif($action==='rename'){
   $id=(int)($_POST['playlist_id']??0);
    $oldRecord=owned_edit_record('playlists',$id);$old=(int)$oldRecord['tenant_id'];
   $name=trim($_POST['name']??'');if(!$name)throw new RuntimeException('Nombre requerido');
   $effect=safe_transition((string)($_POST['transition_effect']??'fade'));$ms=safe_transition_ms($_POST['transition_ms']??600);
   if($old!==$target)assert_unlinked_before_transfer('playlists',$id,$old);
   $newMask=ds_playlist_mode_mask($_POST);$removed=((int)$oldRecord['mode_mask'])&(~$newMask);
   if($removed){
    foreach(dball('SELECT config_json FROM device_config_desired') as $r)foreach(json_decode($r['config_json'],true)?:[] as $key=>$value){
     $bit=str_starts_with($key,'overlay_playlist_')?2:(str_starts_with($key,'queue_playlist_')?4:(str_starts_with($key,'scoreboard_playlist_')?8:($key==='signage_playlist_id'?1:0)));
     if(($removed&$bit)&&(int)$value===$id)throw new RuntimeException('La lista está asignada a ese modo en una pantalla. Reasígnala antes de deshabilitarlo.');
    }
    if(($removed&1)&&(dbone('SELECT id FROM devices WHERE active_playlist_id=? LIMIT 1',[$id])||dbone('SELECT id FROM schedules WHERE playlist_id=? LIMIT 1',[$id])||dbone('SELECT id FROM schedule_items WHERE playlist_id=? LIMIT 1',[$id])))throw new RuntimeException('La lista se usa como cartelera en pantallas o programación; reasígnala antes de quitar ese modo');
   }
   execsql('UPDATE playlists SET tenant_id=?,name=?,transition_effect=?,transition_ms=?,mode_mask=? WHERE tenant_id=? AND id=?',[$target,$name,$effect,$ms,ds_playlist_mode_mask($_POST),$old,$id]);
  }else{
   $pid=(int)($_POST['playlist_id']??0);$parent=dbone('SELECT * FROM playlists WHERE id=? AND tenant_id=?',[$pid,$target]);if(!$parent)throw new RuntimeException('Lista inválida');
   if($action==='save_grid'){
    $rows=isset($_POST['rows_json'])?json_decode((string)$_POST['rows_json'],true,16,JSON_THROW_ON_ERROR):(array)($_POST['rows']??[]);
    if(!is_array($rows))throw new RuntimeException('Contenido de tabla inválido');
    ds_playlist_save_grid($target,$pid,$rows,(string)($_POST['stamp']??''));
   }elseif(in_array($action,['duplicate_row','bulk_duplicate','bulk_delete'],true)){
    $selection=$action==='duplicate_row'?[(int)($_POST['item_id']??0)]:json_decode((string)($_POST['selection_json']??'[]'),true,8,JSON_THROW_ON_ERROR);
    if(!is_array($selection))throw new RuntimeException('Selección inválida');
    $count=ds_playlist_rows_action($target,$pid,$selection,$action==='bulk_delete'?'delete':'duplicate',(string)($_POST['stamp']??''));
    $ok=$count.' renglón(es) '.($action==='bulk_delete'?'eliminados':'duplicados');
   }elseif($action==='add'){
    $choice=explode(':',(string)($_POST['choice']??''),2);$kind=$choice[0]??'';$id=(int)($choice[1]??0);
    if($kind==='separator'&&$id===0){$id=ds_media_white_separator($target,(string)$parent['orientation'],(string)$parent['layout_type']);$kind='media';}
    elseif($kind==='separator'&&$id===1){require_permission('media.edit');$id=ds_global_separator((string)$parent['orientation'],(string)$parent['layout_type'],'#000000');$kind='media';}
    $table=$kind==='media'?'media':($kind==='playlist'?'playlists':null);if(!$table)throw new RuntimeException('Tipo inválido');
    $child=ds_content_record($table,$id,$target);if(!$child||!compatible($parent,$child))throw new RuntimeException('El contenido no tiene la orientación o el formato adecuados');
    if($kind==='playlist'&&!expanded($target,$id))throw new RuntimeException('La lista seleccionada está vacía. Añádele medios antes de insertarla.');
    if($kind==='playlist')validate_graph($target,$pid,$id);
    $row=dbone('SELECT COALESCE(MAX(position),0) n FROM playlist_items WHERE tenant_id=? AND playlist_id=?',[$target,$pid]);$pos=(int)$row['n']+10;
    $effect=(string)($_POST['transition_effect']??'inherit');if($effect!=='inherit'&&!isset(transitions()[$effect]))throw new RuntimeException('Transición inválida');
    $ms=($_POST['transition_ms']??'')===''?null:safe_transition_ms($_POST['transition_ms']);
    execsql('INSERT INTO playlist_items(tenant_id,playlist_id,kind,media_id,child_playlist_id,position,duration_seconds,repeat_count,transition_effect,transition_ms) VALUES(?,?,?,?,?,?,?,?,?,?)',[$target,$pid,$kind,$kind==='media'?$id:null,$kind==='playlist'?$id:null,$pos,ds_playlist_duration($_POST['duration']??10),ds_playlist_integer($_POST['repeat']??1,1,30,'Repeticiones'),$effect,$ms]);
   }elseif(in_array($action,['edit','delete','up','down'],true)){
    $iid=(int)($_POST['item_id']??0);$item=dbone('SELECT * FROM playlist_items WHERE id=? AND playlist_id=? AND tenant_id=?',[$iid,$pid,$target]);if(!$item)throw new RuntimeException('Elemento inválido');
    if($action==='delete')execsql('DELETE FROM playlist_items WHERE id=? AND playlist_id=? AND tenant_id=?',[$iid,$pid,$target]);
    elseif($action==='edit'){
     $effect=(string)($_POST['transition_effect']??'inherit');if($effect!=='inherit'&&!isset(transitions()[$effect]))throw new RuntimeException('Transición inválida');
     $ms=($_POST['transition_ms']??'')===''?null:safe_transition_ms($_POST['transition_ms']);
     execsql('UPDATE playlist_items SET duration_seconds=?,repeat_count=?,transition_effect=?,transition_ms=? WHERE id=? AND playlist_id=? AND tenant_id=?',[ds_playlist_duration($_POST['duration']),ds_playlist_integer($_POST['repeat'],1,30,'Repeticiones'),$effect,$ms,$iid,$pid,$target]);
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
if($scope!==null){$where.=' AND (p.tenant_id=? OR p.is_shared=1)';$params[]=$scope;}
if($query!==''){$where.=' AND p.name LIKE ?';$params[]='%'.str_replace(['%','_'],['\\%','\\_'],substr($query,0,120)).'%';}
if($fOri!=='' && isset(orientations()[$fOri])){$where.=' AND p.orientation=?';$params[]=$fOri;}
if($fLayout!=='' && isset(layouts()[$fLayout])){$where.=' AND p.layout_type=?';$params[]=$fLayout;}
$filtered=dball('SELECT p.*,t.name company_name FROM playlists p JOIN tenants t ON t.id=p.tenant_id WHERE '.$where.' ORDER BY t.name,p.name',$params);
$poolWhere=$scope===null?'1=1':'(tenant_id=? OR is_shared=1)';$poolParams=$scope===null?[]:[$scope];
$lists=dball('SELECT * FROM playlists WHERE '.$poolWhere.' ORDER BY name',$poolParams);
$media=dball('SELECT * FROM media WHERE '.$poolWhere.' ORDER BY name',$poolParams);
$filteredIds=array_map('intval',array_column($filtered,'id'));
$items=$filteredIds?dball('SELECT i.*,p.name child_name,m.name media_name,m.mime media_mime FROM playlist_items i LEFT JOIN playlists p ON p.id=i.child_playlist_id LEFT JOIN media m ON m.id=i.media_id WHERE i.playlist_id IN ('.implode(',',array_fill(0,count($filteredIds),'?')).') ORDER BY i.playlist_id,i.position,i.id',$filteredIds):[];
render('Listas de reproducción',function()use($lists,$filtered,$media,$items,$error,$ok,$query,$fOri,$fLayout,$scope,$createdCopy){
 echo '<link rel="stylesheet" href="'.h(url('assets/css/playlist-editor.css')).'?v=0429"><script defer src="'.h(url('assets/js/playlist-editor.js')).'?v=0429"></script>';
 if($error)echo '<div class="alert alert-danger">'.h($error).'</div>';if($ok)echo '<div class="alert alert-success">'.h($ok).'</div>';
 if($createdCopy)echo '<a class="btn btn-primary mb-3" href="'.h(url('playlists.php?company='.$createdCopy['company'].'#playlist-'.$createdCopy['id'])).'">Ver copia en la empresa de destino</a>';
 if(can('playlists.edit'))echo '<a class="btn btn-primary mb-3" href="'.h(url('playlist-generator.php'.($scope?'?company='.$scope:''))).'">Generar listas automáticamente</a>';
 if(can('playlists.edit'))create_modal('Nueva lista',function()use($scope){echo '<input type="hidden" name="action" value="create">'.company_select($scope??0);field('name','Nombre');echo ds_playlist_modes_form().'<label>Orientación</label>'.sel('orientation',orientations(),'landscape').'<label>Formato</label>'.sel('layout_type',layouts()).'<label>Transición predeterminada</label>'.sel('transition_effect',transitions(),'fade');field('transition_ms','Duración del efecto (milisegundos)','number','600');});
 echo '<form method="get" class="ds-filters" aria-label="Filtros de listas">'.company_filter_control($scope).'<div class="ds-filter-field"><label>Nombre de lista</label><input type="search" name="q" class="form-control" value="'.h($query).'" placeholder="Buscar lista"></div><div class="ds-filter-field"><label>Orientación</label><select name="orientation" class="form-select"><option value="">Todas</option>';foreach(orientations() as $key=>$label)echo '<option value="'.h($key).'" '.($fOri===$key?'selected':'').'>'.h($label).'</option>';echo '</select></div><div class="ds-filter-field"><label>Formato</label><select name="layout" class="form-select"><option value="">Todos</option>';foreach(layouts() as $key=>$label)echo '<option value="'.h($key).'" '.($fLayout===$key?'selected':'').'>'.h($label).'</option>';echo '</select></div><div class="ds-filter-actions"><button class="btn btn-primary" type="submit">Filtrar</button><a class="btn btn-outline-secondary" href="'.h(url('playlists.php')).'">Limpiar</a></div></form><p class="ds-filter-count">Listas encontradas: '.count($filtered).'</p>';
 if(!$filtered)echo '<div class="card ds-list-empty">No hay listas que coincidan con los filtros.</div>';
 foreach($filtered as $p){
  $dsModals='';$editableList=can('playlists.edit')&&(is_super()||(int)$p['tenant_id']===tenant());
  $settingsId='playlist-settings-'.(int)$p['id'];
  $playlistItems=array_values(array_filter($items,static fn($i)=>(int)$i['playlist_id']===(int)$p['id']));
  $open=(int)($_POST['playlist_id']??0)===(int)$p['id']||($createdCopy&&(int)$createdCopy['id']===(int)$p['id']);
  echo '<details class="card card-body mb-3 ds-playlist" id="playlist-'.(int)$p['id'].'"'.($open?' open':'').'><summary><span class="badge text-bg-light me-2">'.h($p['company_name']).'</span><strong>'.h($p['name']).'</strong><span class="ds-playlist-meta">'.h(ds_playlist_mode_names((int)$p['mode_mask'])).' · '.h(orientations()[$p['orientation']]??'').' · '.h(layouts()[$p['layout_type']]??'').' · '.count($playlistItems).' elementos</span></summary><div class="ds-playlist-content"><div class="d-flex justify-content-between flex-wrap gap-2"><p class="text-muted">Transición predeterminada: '.h(transitions()[$p['transition_effect']]??'Fundido suave').' · '.(int)$p['transition_ms'].' ms</p><div class="d-flex flex-wrap gap-2">';
  if($editableList)echo '<button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#duplicate-playlist-'.(int)$p['id'].'">Duplicar / copiar a empresa</button> <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#'.$settingsId.'">Editar lista / empresa</button> <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#remove-playlists-'.(int)$p['id'].'">Eliminar lista</button>';
  echo '<a class="btn btn-sm btn-outline-primary" target="_blank" href="'.h(url('preview.php?id='.$p['id'])).'">Vista previa</a></div></div>';
  if($editableList){
    ob_start();echo '<div class="modal fade" id="duplicate-playlist-'.(int)$p['id'].'" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5>Duplicar lista · '.h($p['name']).'</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div><div class="modal-body">';form_open();
    echo '<input type="hidden" name="action" value="duplicate"><input type="hidden" name="playlist_id" value="'.(int)$p['id'].'">';
    field('copy_name','Nombre de la copia','text',ds_playlist_copy_name((string)$p['name'],' — copia'));
    echo str_replace('>Empresa</label>','>Empresa de destino</label>',company_select((int)$p['tenant_id']));
    echo '<p class="ds-hint">Puedes copiarla a la misma empresa'.(is_super()?' o a otra empresa':'').'. Se conservan orden, tiempos, repeticiones, transiciones y sublistas. En la misma empresa se reutilizan los medios; entre empresas se crean archivos y registros propios. No se copian horarios ni asignaciones a pantallas y no se modifica la lista original.</p><button type="submit" class="btn btn-primary">Crear copia independiente</button></form></div></div></div></div>';
    $dsModals.=ob_get_clean();
    ob_start();echo '<div class="modal fade" id="'.$settingsId.'" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5>Configuración de lista</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">';form_open();
    echo '<input type="hidden" name="action" value="rename">'.company_select((int)$p['tenant_id']).'<input type="hidden" name="playlist_id" value="'.(int)$p['id'].'">';
    field('name','Nombre',(string)'text',(string)$p['name']);echo ds_playlist_modes_form((int)$p['mode_mask']);
    echo '<p class="ds-hint">Cambiar de empresa traslada la lista y solo se permite si está vacía y sin asignaciones. Para conservarla y usarla en otra empresa, utiliza «Duplicar / copiar a empresa».</p><label class="form-label">Efecto de transición</label>'.sel('transition_effect',transitions(),(string)$p['transition_effect']);
    field('transition_ms','Duración (milisegundos; 0 = corte)','number',(string)$p['transition_ms']);
    echo '<button class="btn btn-primary">Guardar cambios</button></form></div></div></div></div>';
    $dsModals.=ob_get_clean();
    $deleteModal=ds_cleanup_modal('playlists',(int)$p['id'],(int)$p['tenant_id'],(string)$p['name'],
       'Quita esta lista de otras listas, programaciones y pantallas antes de eliminarla. Los medios no se eliminan.');
    $deleteModal=str_replace('name="action" value="delete"','name="action" value="delete_playlist"',$deleteModal);
    $deleteModal=str_replace('name="id" value="'.(int)$p['id'].'"','name="playlist_id" value="'.(int)$p['id'].'"',$deleteModal);
    $dsModals.=$deleteModal;
  }
  echo ds_share_checkbox('playlists',$p);
  $gridId='playlist-grid-'.(int)$p['id'];$bulkId='playlist-bulk-'.(int)$p['id'];
  if($editableList){
   echo '<form method="post" id="'.$gridId.'" data-playlist-grid><input type="hidden" name="_csrf" value="'.h(csrf()).'"><input type="hidden" name="action" value="save_grid"><input type="hidden" name="playlist_id" value="'.(int)$p['id'].'"><input type="hidden" name="tenant_id" value="'.(int)$p['tenant_id'].'"><input type="hidden" name="stamp" value="'.h(ds_playlist_stamp($playlistItems)).'"><div class="ds-grid-toolbar"><button type="button" class="btn btn-outline-primary" data-grid-edit>Modificar contenido</button><button type="submit" class="btn btn-primary" data-grid-save hidden>Guardar contenido</button><button type="button" class="btn btn-outline-secondary" data-grid-cancel hidden>Cancelar cambios</button><span class="ds-hint">Edita todos los renglones y guarda una sola vez.</span></div></form>';
  }
  if($editableList){
   echo '<form method="post" id="'.$bulkId.'" data-playlist-bulk><input type="hidden" name="_csrf" value="'.h(csrf()).'"><input type="hidden" name="playlist_id" value="'.(int)$p['id'].'"><input type="hidden" name="tenant_id" value="'.(int)$p['tenant_id'].'"><input type="hidden" name="stamp" value="'.h(ds_playlist_stamp($playlistItems)).'"><input type="hidden" name="selection_json" value="[]"><div class="ds-grid-toolbar"><strong><span data-row-count>0</span> seleccionados</strong><button type="button" class="btn btn-sm btn-outline-primary" data-rows-edit disabled>Editar seleccionados</button><button type="submit" name="action" value="bulk_duplicate" class="btn btn-sm btn-outline-primary" data-rows-action disabled>Duplicar seleccionados</button><button type="submit" name="action" value="bulk_delete" class="btn btn-sm btn-outline-danger" data-rows-action disabled>Eliminar seleccionados</button></div></form>';
   echo '<div data-rows-values class="ds-rows-values" hidden><strong>Aplicar los mismos valores a los seleccionados</strong><div class="row g-2 mt-1"><div class="col-md-3"><label>Duración (s)</label><input type="number" min="0.1" max="86400" step="0.1" class="form-control" data-bulk-value="duration" placeholder="Sin cambiar"></div><div class="col-md-2"><label>Repeticiones</label><input type="number" min="1" max="30" step="1" class="form-control" data-bulk-value="repeat" placeholder="Sin cambiar"></div><div class="col-md-3"><label>Efecto</label><select class="form-select" data-bulk-value="transition_effect"><option value="keep">Sin cambiar</option>';
   foreach(['inherit'=>'Heredar de la lista']+transitions() as $key=>$label)echo '<option value="'.h($key).'">'.h($label).'</option>';
   echo '</select></div><div class="col-md-4"><label>Duración efecto (ms)</label><select class="form-select" data-bulk-ms-mode><option value="keep">Sin cambiar</option><option value="inherit">Heredar de la lista</option><option value="set">Definir valor</option></select><input type="number" min="0" max="3000" step="1" class="form-control mt-1" data-bulk-value="transition_ms" placeholder="Milisegundos" disabled></div></div><button type="button" class="btn btn-outline-primary mt-2" data-rows-apply>Aplicar valores a seleccionados</button><p class="ds-hint">Los campos vacíos conservan su valor. Después pulsa Guardar contenido.</p></div>';
  }
  echo '<div class="table-responsive"><table class="table table-sm ds-playlist-grid"><thead><tr><th>'.($editableList?'<input type="checkbox" data-rows-all aria-label="Seleccionar todos los renglones">':'').'</th><th>Miniatura</th><th>Elemento</th><th>Duración (s)</th><th>Repeticiones</th><th>Efecto</th><th>Duración efecto (ms)</th><th>Acciones</th></tr></thead><tbody>';
  $usage=[];
  foreach($playlistItems as $i){
   $key=$i['kind'].':'.($i['kind']==='media'?$i['media_id']:$i['child_playlist_id']);$usage[$key]=($usage[$key]??0)+1;
  }
  foreach($playlistItems as $i){
   $iid=(int)$i['id'];$prefix='rows['.$iid.']';$editable=$editableList;
   $attrs=' form="'.$gridId.'" data-grid-field disabled';
   echo '<tr data-playlist-row="'.$iid.'"><td>'.($editable?'<input type="checkbox" data-row-select value="'.$iid.'" aria-label="Seleccionar elemento">':'').'</td><td class="ds-row-thumbnail">';
   if($i['kind']==='media'){
    $link=url('media-file.php?id='.(int)$i['media_id']);echo '<a href="'.h($link).'" target="_blank" rel="noopener" aria-label="Abrir medio">';
    if(str_starts_with((string)$i['media_mime'],'image/'))echo '<img src="'.h(url('media-thumbnail.php?id='.(int)$i['media_id'])).'" loading="lazy" decoding="async" alt="'.h($i['media_name']).'">';
    else echo '<video muted playsinline preload="none" data-row-video="'.h($link.'#t=0.1').'" aria-label="Miniatura de video"></video><small>VIDEO</small>';
    echo '</a>';
   }else echo '<a class="ds-row-list-preview" href="'.h(url('playlists.php?company='.(int)$p['tenant_id'].'#playlist-'.(int)$i['child_playlist_id'])).'" title="Abrir sublista">'.ds_icon('playlist',30).'<small>LISTA</small></a>';
   echo '</td><td class="ds-row-name">'.h($i['kind']==='media'?$i['media_name']:'Lista: '.$i['child_name']).'</td>';
   foreach(['duration'=>['Duración (s)',0.1,86400,$i['duration_seconds']],'repeat'=>['Repeticiones',1,30,$i['repeat_count']],'transition_effect'=>['Efecto',0,0,$i['transition_effect']],'transition_ms'=>['Duración efecto (ms)',0,3000,$i['transition_ms']]] as $field=>$v){
    echo '<td>';
    if($editable){
     if($field==='transition_effect'){
      echo '<select class="form-select form-select-sm" name="'.$prefix.'['.$field.']" aria-label="Efecto del elemento"'.$attrs.'>';
      foreach(['inherit'=>'Heredar de la lista']+transitions() as $key=>$label)echo '<option value="'.h($key).'"'.($key===$v[3]?' selected':'').'>'.h($label).'</option>';echo '</select>';
     }else echo '<input class="form-control form-control-sm" type="number" name="'.$prefix.'['.$field.']" min="'.$v[1].'" max="'.$v[2].'" step="'.($field==='duration'?'0.1':'1').'" value="'.h($v[3]===null?'':(string)$v[3]).'" aria-label="'.h($v[0]).' del elemento"'.($field==='transition_ms'?' placeholder="Heredar"':' required').$attrs.'>';
    }else echo h($field==='transition_effect'?($v[3]==='inherit'?'Heredar':(transitions()[$v[3]]??'')):($v[3]===null?'Heredar':(string)$v[3]));
    echo '</td>';
   }
   echo '<td class="ds-playlist-actions">';
   if($editable)foreach(['up'=>'↑','down'=>'↓','delete'=>ds_icon('delete',16),'duplicate_row'=>ds_icon('copy',16)] as $act=>$label){form_open();echo '<input type="hidden" name="playlist_id" value="'.(int)$p['id'].'"><input type="hidden" name="tenant_id" value="'.(int)$p['tenant_id'].'"><input type="hidden" name="item_id" value="'.$iid.'"><input type="hidden" name="stamp" value="'.h(ds_playlist_stamp($playlistItems)).'"><button class="btn btn-sm btn-outline-secondary" name="action" value="'.$act.'" aria-label="'.h(['up'=>'Subir elemento','down'=>'Bajar elemento','delete'=>'Eliminar elemento','duplicate_row'=>'Duplicar renglón'][$act]).'" '.($act==='delete'?'onclick="return confirm(\'¿Eliminar este elemento?\')"':'').'>'.$label.'</button></form>';}
   echo '</td></tr>';
  }
  if(!$playlistItems)echo '<tr><td colspan="8">Lista vacía. Añade medios, listas compatibles o un separador blanco.</td></tr>';
  echo '</tbody></table></div>'.$dsModals;
  if($editableList){
   $available=[];$excluded=0;
   foreach($lists as $q)if(((int)$q['tenant_id']===(int)$p['tenant_id']||(int)$q['is_shared']===1)&&(int)$q['id']!==(int)$p['id']&&compatible($p,$q)){
    try{validate_graph((int)$p['tenant_id'],(int)$p['id'],(int)$q['id']);$available[]=['list'=>$q,'empty'=>!expanded((int)$p['tenant_id'],(int)$q['id'])];}catch(RuntimeException $e){$excluded++;}
   }
   echo '<form method="post" class="row g-2 mt-3" data-playlist-add><input type="hidden" name="_csrf" value="'.h(csrf()).'"><input type="hidden" name="action" value="add"><input type="hidden" name="playlist_id" value="'.(int)$p['id'].'"><input type="hidden" name="tenant_id" value="'.(int)$p['tenant_id'].'"><div class="col-md-6"><label class="form-label">Medios o listas compatibles</label><select class="form-select" name="choice" required><option value="">Selecciona un medio o una lista</option>'.(can('media.edit')&&$p['orientation']!=='any'?'<optgroup label="Separadores"><option value="separator:0">Separador blanco · tamaño de esta lista</option><option value="separator:1">Separador negro · tamaño de esta lista</option></optgroup>':'').'<optgroup label="Medios compatibles">';
   foreach($media as $m)if(((int)$p['tenant_id']===(int)$m['tenant_id']||(int)$m['is_shared']===1)&&compatible($p,$m)){
    $used=$usage['media:'.$m['id']]??0;
    echo '<option value="media:'.(int)$m['id'].'">'.h($m['name']).($used?' · Ya incluido '.$used.' vez/veces':'').'</option>';
   }
   echo '</optgroup><optgroup label="Listas compatibles · '.count($available).'">';
   foreach($available as $entry){$q=$entry['list'];$used=$usage['playlist:'.$q['id']]??0;echo '<option value="playlist:'.(int)$q['id'].'"'.($entry['empty']?' disabled':'').'>Lista: '.h($q['name']).($entry['empty']?' · Vacía: añade medios primero':'').($used?' · Ya incluida '.$used.' vez/veces':'').'</option>';}
   echo '</optgroup></select><p class="ds-hint">Puedes repetir elementos. El contador indica sus renglones en esta lista. Las sublistas deben pertenecer a la misma empresa y tener formato y orientación compatibles; no se permiten referencias circulares.</p>';
   if(!$available)echo '<p class="text-muted">No hay otras listas compatibles. Crea una lista publicitaria para esta empresa, orientación y formato, añádele medios y después selecciónala aquí.</p>';
   echo '</div><div class="col-md-2"><label class="form-label">Duración (s)</label><input name="duration" type="number" min="0.1" step="0.1" max="86400" value="10" required class="form-control"></div><div class="col-md-2"><label class="form-label">Repeticiones</label><input name="repeat" type="number" min="1" max="30" value="1" required class="form-control"></div><div class="col-md-2 align-self-start"><button class="btn btn-primary mt-4">Añadir</button></div><div class="col-md-6"><label class="form-label">Efecto</label>'.sel('transition_effect',['inherit'=>'Heredar de la lista']+transitions(),'inherit').'</div><div class="col-md-6"><label class="form-label">Duración efecto (ms; vacío = heredar)</label><input name="transition_ms" type="number" min="0" max="3000" step="1" placeholder="Heredar" class="form-control"></div></form>';
  }
  echo '</div></details>';
 }
});
