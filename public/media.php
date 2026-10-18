<?php
require __DIR__.'/_init.php';require_once ROOT.'/app/domain.php';require_once ROOT.'/app/media_geometry.php';require_once ROOT.'/app/cleanup.php';require_once ROOT.'/app/media_bulk.php';require_once ROOT.'/app/media_separator.php';require_permission('media.view');
$t=tenant();$error='';$ok='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 check_csrf();try{
  require_permission('media.edit');$action=$_POST['action']??'upload';$target=post_tenant();
  if($action==='share'){ds_content_share('media',(int)($_POST['id']??0),$target,isset($_POST['is_shared']));$ok='Disponibilidad actualizada';
  }elseif($action==='white_separator'){
   ds_media_white_separator($target,(string)($_POST['orientation']??''),(string)($_POST['layout_type']??''));
   $ok='Separador blanco disponible en la biblioteca. Puedes añadirlo y repetirlo en las listas compatibles.';
  }elseif(in_array($action,['bulk_copy','bulk_move','bulk_classify'],true)){
   if((string)($_POST['confirm_bulk']??'')!=='APLICAR')throw new RuntimeException('Escribe APLICAR para confirmar la operación masiva.');
   $selection=ds_bulk_ids($_POST['media_ids']??[]);
   $scopeSelected=company_scope(isset($_POST['filter_company'])?(string)$_POST['filter_company']:null);
   $destination=(int)($_POST['tenant_id']??tenant());
   $ok=ds_bulk_media_action($action,$selection,$scopeSelected,$destination,(string)($_POST['bulk_orientation']??'keep'),(string)($_POST['bulk_layout']??'keep'));
  }elseif($action==='delete'){
   ds_cleanup_confirm();
   $id=(int)($_POST['id']??0);$ok=ds_cleanup_delete('media',$id,$target);
  }elseif($action==='edit'){
   $id=(int)($_POST['id']??0);$m=owned_edit_record('media',$id);$old=(int)$m['tenant_id'];
   $name=trim($_POST['name']??'');$ori=$_POST['orientation']??'';$layout=$_POST['layout_type']??'';
   if($name===''||!isset(orientations()[$ori])||!isset(layouts()[$layout]))throw new RuntimeException('Nombre, orientación o formato inválidos');
   $linked=dball('SELECT p.name,p.orientation,p.layout_type FROM playlist_items i JOIN playlists p ON p.id=i.playlist_id AND p.tenant_id=i.tenant_id WHERE i.kind=? AND i.media_id=?',['media',$id]);
   foreach($linked as $p)if(!compatible($p,['orientation'=>$ori,'layout_type'=>$layout]))throw new RuntimeException('Cambio incompatible con lista: '.$p['name']);
   $w=isset($_POST['width_px']) && $_POST['width_px']!==''?(int)$_POST['width_px']:null;
   $h=isset($_POST['height_px']) && $_POST['height_px']!==''?(int)$_POST['height_px']:null;
   if(($w!==null && $w<=0)||($h!==null && $h<=0))throw new RuntimeException('Dimensiones inválidas');
   if($old!==$target){
    assert_unlinked_before_transfer('media',$id,$old);
    $src=ROOT.'/storage/media/'.$old.'/'.$m['filename'];$dstDir=ROOT.'/storage/media/'.$target;$dst=$dstDir.'/'.$m['filename'];
    if(!is_file($src)||is_file($dst))throw new RuntimeException('No se puede transferir el archivo físico. Comprueba que existe y que no haya duplicados.');
    if(!is_dir($dstDir)&&!mkdir($dstDir,0700,true)&&!is_dir($dstDir))throw new RuntimeException('No se pudo preparar el almacenamiento de la empresa destino');
    db()->beginTransaction();$moved=false;
    try{
      ds_formats_lock_geometry($ori,$layout,$w,$h);
      if(!rename($src,$dst))throw new RuntimeException('No se pudo mover el medio a la empresa destino');$moved=true;
      execsql('UPDATE media SET tenant_id=?,name=?,orientation=?,layout_type=?,width_px=?,height_px=? WHERE id=? AND tenant_id=?',[$target,$name,$ori,$layout,$w,$h,$id,$old]);
      db()->commit();
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();if($moved&&!rename($dst,$src))log_event('media_transfer_restore_failed',['id'=>$id]);throw $e;}
   }else {db()->beginTransaction();try{ds_formats_lock_geometry($ori,$layout,$w,$h);execsql('UPDATE media SET name=?,orientation=?,layout_type=?,width_px=?,height_px=? WHERE id=? AND tenant_id=?',[$name,$ori,$layout,$w,$h,$id,$old]);db()->commit();}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}}
   log_event('media_edited',['id'=>$id,'old_company'=>$old,'company'=>$target]);$ok='Medio editado';
  }elseif($action==='upload'){
   // Orientation is the target DISPLAY, not the physical shape of the uploaded artwork.
   $ori=(string)($_POST['orientation']??'');$layout=(string)($_POST['layout_type']??'');
   $geometry=ds_media_geometry($ori,$layout);
   if(!$geometry)throw new RuntimeException('Selecciona la orientación de la pantalla y la zona donde se mostrará el medio.');
   $names=$_FILES['files']['name']??[];
   if(!is_array($names)||count($names)<1||count($names)>30)throw new RuntimeException('Selecciona de 1 a 30 archivos para subir.');
   // PREFLIGHT EVERY FILE before importing any. Prevents partial bulk uploads.
   $prepared=[];
   foreach($names as $i=>$name){
    $filenameOriginal=substr(basename((string)$name),0,190);
    $err=(int)($_FILES['files']['error'][$i]??UPLOAD_ERR_NO_FILE);
    if($err!==UPLOAD_ERR_OK)throw new RuntimeException('No se pudo recibir «'.$filenameOriginal.'» (código de carga '.$err.'). Ningún archivo fue importado.');
    $tmp=(string)($_FILES['files']['tmp_name'][$i]??'');
    if($tmp===''||!is_uploaded_file($tmp))throw new RuntimeException('Archivo temporal no válido: «'.$filenameOriginal.'».');
    $bytes=(int)($_FILES['files']['size'][$i]??0);
    if($bytes<1||$bytes>40*1048576)throw new RuntimeException('«'.$filenameOriginal.'» debe pesar entre 1 byte y 40 MB.');
    $mime=(string)(new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    $allowed=['image/jpeg','image/png','image/webp','image/gif','video/mp4','video/webm','video/quicktime'];
    if(!in_array($mime,$allowed,true))throw new RuntimeException('Tipo de archivo no admitido para «'.$filenameOriginal.'»: '.$mime);
    $w=null;$h=null;
    if(str_starts_with($mime,'image/')){
     $dims=@getimagesize($tmp);
     if(!$dims)throw new RuntimeException('«'.$filenameOriginal.'» no es una imagen válida.');
     $w=(int)$dims[0];$h=(int)$dims[1];
     if(!ds_media_geometry_check($w,$h,$geometry))throw new RuntimeException(ds_media_geometry_error($filenameOriginal,$w,$h,$geometry));
    }
    $prepared[]=['tmp'=>$tmp,'original'=>$filenameOriginal,'bytes'=>$bytes,'mime'=>$mime,'w'=>$w,'h'=>$h];
   }
   // Atomic DB changes and compensating filesystem removal on failure.
   $dir=ROOT.'/storage/media/'.$target;
   if(!is_dir($dir)&&!mkdir($dir,0700,true)&&!is_dir($dir))throw new RuntimeException('No se pudo preparar el almacenamiento de medios.');
   $created=[];
   db()->beginTransaction();
   try {
    foreach($prepared as $item){
     ds_formats_lock_geometry($ori,$layout,$item['w'],$item['h']);
     $stored=bin2hex(random_bytes(20));$path=$dir.'/'.$stored;
     if(!move_uploaded_file($item['tmp'],$path))throw new RuntimeException('No se pudo guardar «'.$item['original'].'».');
     $created[]=$path;
     execsql('INSERT INTO media(tenant_id,name,filename,mime,size_bytes,sha256,orientation,layout_type,width_px,height_px) VALUES(?,?,?,?,?,?,?,?,?,?)',
       [$target,$item['original'],$stored,$item['mime'],$item['bytes'],hash_file('sha256',$path),$ori,$layout,$item['w'],$item['h']]);
    }
    db()->commit();
   }catch(Throwable $e){
    if(db()->inTransaction())db()->rollBack();
    foreach($created as $path)if(is_file($path))@unlink($path);
    throw $e;
   }
   log_event('media_uploaded',['count'=>count($prepared),'company'=>$target,'screen_orientation'=>$ori,'layout'=>$layout]);
   $ok=count($prepared).' medio(s) cargados. Pantalla: '.$geometry['screen_name'].' · Zona: '.$geometry['zone_name'].' ('.$geometry['width'].' × '.$geometry['height'].' px de referencia).';
  }

 }catch(Throwable $e){$error=$e instanceof PDOException?fault($e,'media'):$e->getMessage();}
}
$query=trim((string)($_GET['q']??''));$fOri=(string)($_GET['orientation']??'');$fLayout=(string)($_GET['layout']??'');$fType=(string)($_GET['type']??'');
$fMinMb=(string)($_GET['min_mb']??'');$fMaxMb=(string)($_GET['max_mb']??'');
$fWidth=(string)($_GET['width_px']??'');$fHeight=(string)($_GET['height_px']??'');
$fSort=(string)($_GET['sort']??'newest');$fOrder=(string)($_GET['order']??'desc');
$scope=company_scope(isset($_GET['company'])?(string)$_GET['company']:null);
$where='1=1';$params=[];if($scope!==null){$where.=' AND (m.tenant_id=? OR m.is_shared=1)';$params[]=$scope;}
if($query!==''){$where.=' AND m.name LIKE ?';$params[]='%'.str_replace(['%','_'],['\\%','\\_'],substr($query,0,120)).'%';}
if($fOri!=='' && isset(orientations()[$fOri])){$where.=' AND m.orientation=?';$params[]=$fOri;}
if($fLayout!=='' && isset(layouts()[$fLayout])){$where.=' AND m.layout_type=?';$params[]=$fLayout;}
if($fType==='image'||$fType==='video'){$where.=' AND m.mime LIKE ?';$params[]=$fType.'/%';}
foreach([['min_mb',$fMinMb],['max_mb',$fMaxMb]] as [$label,$input]){
 if($input!=='' && (!is_numeric($input) || (float)$input<0 || (float)$input>40960)){
  $error='Filtro '.$label.' inválido. Indica un tamaño entre 0 y 40960 MB.';
  $fMinMb='';$fMaxMb='';break;
 }
}
if($fMinMb!==''){$where.=' AND m.size_bytes>=?';$params[]=(int)round((float)$fMinMb*1048576);}
if($fMaxMb!==''){$where.=' AND m.size_bytes<=?';$params[]=(int)round((float)$fMaxMb*1048576);}
foreach([['m.width_px',$fWidth],['m.height_px',$fHeight]] as [$column,$n]){
 if($n!=='' && ctype_digit($n) && (int)$n>0 && (int)$n<=20000){$where.=' AND '.$column.'=?';$params[]=(int)$n;}
 elseif($n!==''){$error='Indica dimensiones en píxeles válidas (1–20000).';}
}
$sorts=['newest'=>'m.id','company'=>'t.name','name'=>'m.name','width'=>'m.width_px','height'=>'m.height_px','bytes'=>'m.size_bytes','presentation'=>'m.layout_type','orientation'=>'m.orientation'];
if(!isset($sorts[$fSort]))$fSort='newest';
if(!in_array($fOrder,['asc','desc'],true))$fOrder='desc';
$rows=dball('SELECT m.*,t.name AS company_name FROM media m JOIN tenants t ON t.id=m.tenant_id WHERE '.$where.' ORDER BY '.$sorts[$fSort].' '.strtoupper($fOrder).', m.id DESC',$params);
render('Biblioteca de medios',function()use($rows,$error,$ok,$query,$fOri,$fLayout,$fType,$scope,$fMinMb,$fMaxMb,$fWidth,$fHeight,$fSort,$fOrder){
 echo '<link rel="stylesheet" href="'.h(url('assets/css/media-geometry.css')).'?v=0420"><link rel="stylesheet" href="'.h(url('assets/css/media-bulk.css')).'?v=014"><link rel="stylesheet" href="'.h(url('assets/css/media-library-visual.css')).'?v=014"><script defer src="'.h(url('assets/js/media-geometry.js')).'?v=0420"></script><script defer src="'.h(url('assets/js/media-bulk.js')).'?v=014"></script><link rel="stylesheet" href="'.h(url('assets/css/media-import-wizard.css')).'?v=0426"><script defer src="'.h(url('assets/js/media-import-wizard.js')).'?v=0426"></script>';
 if($error)echo '<div class="alert alert-danger">'.h($error).'</div>';if($ok)echo '<div class="alert alert-success">'.h($ok).'</div>';
 if(can('media.edit')){
   echo '<div class="ds-actions"><button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#upload">+ Cargar medios (asistente)</button><button class="btn btn-outline-secondary" type="button" data-bs-toggle="modal" data-bs-target="#white-separator">+ Separador blanco</button> <a class="btn btn-outline-primary" href="'.h(url('media-guide.php')).'">Guía visual: 10 tamaños</a></div>';
   echo '<div class="modal fade" id="white-separator" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5>Crear separador blanco</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div><div class="modal-body">';form_open();
   echo '<input type="hidden" name="action" value="white_separator">'.company_select($scope??0).'<label>Orientación de pantalla</label>'.sel('orientation',['landscape'=>'Pantalla horizontal','portrait'=>'Pantalla vertical'],'landscape').'<label>Presentación</label>'.sel('layout_type',layouts(),'fullscreen').'<p class="ds-hint">Imagen PNG blanca con las medidas centrales del formato elegido. Su duración se define en cada lista. Si ya existe, se reutiliza.</p><button class="btn btn-primary">Crear medio blanco</button></form></div></div></div></div>';
   echo '<div class="modal fade ds-import-modal" id="upload" tabindex="-1" aria-labelledby="ds-import-title" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h5 class="modal-title" id="ds-import-title">Cargar y clasificar medios</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div><div class="modal-body">';
   echo '<section data-media-import data-upload-url="'.h(url('media-wizard-upload.php')).'" data-csrf="'.h(csrf()).'">';
   echo '<div data-import-select><p>Selecciona todos tus artes, fotografías o videos. Después podrás ver cada uno y asignarle <strong>empresa, pantalla y presentación</strong> por separado, antes de importar.</p><label class="form-label" for="ds-import-file">Seleccionar varios archivos</label><input class="form-control" type="file" id="ds-import-file" data-import-files multiple accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm,video/quicktime"><p class="ds-import-hint mt-2">Hasta 40 MB por archivo. Puedes agregar archivos en varias selecciones. Se importarán uno a uno después de la revisión; no se subirán al seleccionarlos.</p></div>';
   echo '<div data-import-work hidden><div class="ds-import-progress-line"><strong data-import-counter>Archivo 1 de 1</strong><span data-import-summary>0 listos</span></div><div class="ds-import-grid">';
   echo '<div class="ds-import-visual"><div class="ds-import-photo" data-import-preview><span>Vista previa del medio</span></div><div class="ds-import-file-meta"><strong data-import-filename></strong><span data-import-file-details></span></div><div class="ds-import-current-diagram"><div class="ds-preview-mini-screen" data-import-diagram data-orientation="landscape" data-layout="fullscreen"><span class="ds-mini-selected"></span></div><div><strong data-import-geometry-title>Presentación</strong><span data-import-geometry-size>1920 × 1080 px</span></div></div></div>';
   echo '<div class="ds-import-fields"><label class="form-label" for="ds-import-name">Nombre del medio</label><input class="form-control" id="ds-import-name" data-import-name type="text" maxlength="190" required><div class="mt-3" data-import-company>'.company_select($scope??0).'</div><button type="button" class="btn btn-sm btn-outline-primary mt-1" data-import-apply-company>Aplicar esta empresa a todos los pendientes</button>';
   echo '<fieldset class="ds-import-choice-group"><legend>Orientación de la pantalla donde aparecerá</legend><div class="ds-import-option-grid"><button type="button" data-import-orientation="landscape" aria-pressed="true"><span class="ds-preview-mini-screen" data-orientation="landscape" data-layout="fullscreen"><span class="ds-mini-selected"></span></span>Horizontal</button><button type="button" data-import-orientation="portrait" aria-pressed="false"><span class="ds-preview-mini-screen" data-orientation="portrait" data-layout="fullscreen"><span class="ds-mini-selected"></span></span>Vertical</button></div></fieldset>';
   echo '<fieldset class="ds-import-choice-group"><legend>Presentación del medio</legend><div class="ds-import-zone-grid">';
   foreach(ds_media_zones() as $key=>$zone){
    echo '<button type="button" data-import-layout="'.h($key).'" aria-pressed="'.($key==='fullscreen'?'true':'false').'"><span class="ds-preview-mini-screen" data-orientation="landscape" data-layout="'.h($key).'"><span class="ds-mini-selected"></span></span><span>'.h($zone['name']).'</span></button>';
   }
   echo '</div></fieldset><button type="button" class="btn btn-sm btn-outline-primary mt-2" data-import-apply-layout>Aplicar pantalla y presentación a los pendientes</button><p data-import-validation class="ds-import-validation" role="status">Verificando dimensiones...</p></div></div>';
   echo '<div class="ds-import-nav"><button type="button" class="btn btn-outline-secondary" data-import-prev>← Anterior</button><button type="button" class="btn btn-outline-warning" data-import-skip>Omitir este archivo</button><button type="button" class="btn btn-primary" data-import-next>Siguiente →</button></div><div class="ds-import-more"><button type="button" class="btn btn-sm btn-outline-secondary" data-import-more>+ Agregar más archivos a la cola</button><button type="button" class="btn btn-sm btn-outline-danger" data-import-reset>Vaciar selección</button></div></div>';
   echo '<div data-import-review hidden><h6>Revisar antes de cargar</h6><p>Confirma cada clasificación. Los archivos con proporción incorrecta deben corregirse, cambiar de zona u omitirse.</p><div data-import-review-list class="ds-import-review-list"></div><div class="ds-import-review-buttons"><button type="button" class="btn btn-outline-secondary" data-import-edit>← Volver a editar</button><button type="button" class="btn btn-success" data-import-submit>Subir los medios válidos</button></div></div>';
   echo '<div data-import-progress hidden><h6>Importando medios...</h6><div class="progress" role="progressbar" aria-label="Progreso de carga"><div class="progress-bar" data-import-bar style="width:0%">0%</div></div><div class="ds-import-upload-status" data-import-upload-status aria-live="polite"></div><button type="button" class="btn btn-outline-secondary" data-import-close disabled>Cerrar cuando termine</button></div>';
   echo '</section></div></div></div></div>';
 }

 echo '<form method="get" class="ds-filters" aria-label="Filtros de medios">'.company_filter_control($scope).'<div class="ds-filter-field"><label>Buscar archivo</label><input type="search" name="q" class="form-control" value="'.h($query).'" placeholder="Nombre del medio"></div><div class="ds-filter-field"><label>Tipo</label><select name="type" class="form-select"><option value="">Todos</option><option value="image" '.($fType==='image'?'selected':'').'>Imágenes</option><option value="video" '.($fType==='video'?'selected':'').'>Videos</option></select></div><div class="ds-filter-field"><label>Orientación de pantalla</label><select name="orientation" class="form-select"><option value="">Todas</option>';foreach(orientations() as $key=>$label)echo '<option value="'.h($key).'" '.($fOri===$key?'selected':'').'>'.h($label).'</option>';echo '</select></div><div class="ds-filter-field"><label>Presentación</label><select name="layout" class="form-select"><option value="">Todos</option>';foreach(layouts() as $key=>$label)echo '<option value="'.h($key).'" '.($fLayout===$key?'selected':'').'>'.h($label).'</option>';echo '</select></div>';
 echo '<div class="ds-filter-field"><label>Ancho exacto (px)</label><input class="form-control" type="number" min="1" max="20000" name="width_px" value="'.h($fWidth).'" placeholder="Ej. 768"></div>';
 echo '<div class="ds-filter-field"><label>Alto exacto (px)</label><input class="form-control" type="number" min="1" max="20000" name="height_px" value="'.h($fHeight).'" placeholder="Ej. 1080"></div>';
 echo '<div class="ds-filter-field"><label>Tamaño mínimo (MB)</label><input class="form-control" type="number" min="0" max="40960" step="0.01" name="min_mb" value="'.h($fMinMb).'"></div>';
 echo '<div class="ds-filter-field"><label>Tamaño máximo (MB)</label><input class="form-control" type="number" min="0" max="40960" step="0.01" name="max_mb" value="'.h($fMaxMb).'"></div>';
 echo '<div class="ds-filter-field"><label>Ordenar por</label><select name="sort" class="form-select">';
 foreach(['newest'=>'Más recientes','company'=>'Empresa','name'=>'Nombre','presentation'=>'Presentación','orientation'=>'Orientación de pantalla','width'=>'Ancho','height'=>'Alto','bytes'=>'Tamaño del archivo'] as $k=>$v)echo '<option value="'.h($k).'" '.($fSort===$k?'selected':'').'>'.h($v).'</option>';
 echo '</select></div><div class="ds-filter-field"><label>Dirección</label><select name="order" class="form-select"><option value="asc" '.($fOrder==='asc'?'selected':'').'>Ascendente</option><option value="desc" '.($fOrder==='desc'?'selected':'').'>Descendente</option></select></div>';
 echo '<div class="ds-filter-actions"><button class="btn btn-primary" type="submit">Filtrar</button><a class="btn btn-outline-secondary" href="'.h(url('media.php')).'">Limpiar</a></div></form><p class="ds-filter-count">Medios encontrados: '.count($rows).'</p>';
 if(can('media.edit') && count($rows)>0){
   echo '<div class="ds-bulk-toolbar"><div class="d-flex align-items-center flex-wrap gap-2"><strong><span data-bulk-selected>0</span> seleccionado(s)</strong><button class="btn btn-sm btn-outline-primary" type="button" data-bulk-open disabled data-bs-toggle="modal" data-bs-target="#ds-bulk-media-modal">Editar / mover / duplicar selección</button><button class="btn btn-sm btn-outline-secondary" type="button" data-bulk-clear>Deseleccionar</button></div><small>Selecciona los medios del listado filtrado. La selección se reinicia al cambiar filtros o empresa.</small></div>';
   echo '<div class="modal fade" id="ds-bulk-media-modal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Acciones masivas de medios</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div><div class="modal-body"><form method="post" id="ds-bulk-form"><input type="hidden" name="_csrf" value="'.h(csrf()).'"><input type="hidden" name="filter_company" value="'.h($scope===null?'all':(string)$scope).'">';
   echo '<p class="ds-bulk-warning">Los cambios se validan antes de guardarse y son atómicos. Duplicar preserva el original y crea un archivo independiente; mover requiere que los medios no estén utilizados por playlists.</p>';
   echo '<label class="form-label" for="ds-bulk-action">¿Qué quieres hacer?</label><select class="form-select mb-3" id="ds-bulk-action" name="action" required><option value="bulk_copy">Duplicar en otra empresa (conservar originales)</option><option value="bulk_move">Mover a otra empresa (cambiar propietario)</option><option value="bulk_classify">Editar clasificación de presentación</option></select>';
   echo '<div data-bulk-destination>'.company_select($scope??tenant()).'</div>';
   echo '<div data-bulk-classify hidden><p class="small">No se modifican los píxeles del archivo. Se validarán las proporciones y las playlists relacionadas.</p><label class="form-label">Orientación de la pantalla</label><select name="bulk_orientation" class="form-select mb-3"><option value="keep">No cambiar</option><option value="landscape">Pantalla horizontal</option><option value="portrait">Pantalla vertical</option></select><label class="form-label">Zona de presentación</label><select name="bulk_layout" class="form-select mb-3"><option value="keep">No cambiar</option>';
   foreach(layouts() as $k=>$v)echo '<option value="'.h($k).'">'.h($v).'</option>';
   echo '</select></div><p class="small text-muted">Se aplicará a <strong><span data-bulk-modal-selected>0</span> medios</strong>, como máximo 100 por lote.</p><label class="form-label" for="ds-bulk-confirm">Escribe APLICAR para confirmar</label><input class="form-control" id="ds-bulk-confirm" name="confirm_bulk" placeholder="APLICAR" pattern="APLICAR" required autocomplete="off"><div class="modal-footer px-0 pb-0 mt-3"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary" type="submit">Aplicar a la selección</button></div></form></div></div></div></div>';
 }
 echo '<div class="card table-responsive"><table class="table ds-media-grid"><thead><tr><th class="ds-bulk-select-col">'.(can('media.edit')?'<input type="checkbox" class="form-check-input" data-bulk-all aria-label="Seleccionar todos los medios filtrados" title="Seleccionar todos los medios visibles">':'').'</th><th>Vista previa</th><th>Empresa</th><th>Medio</th><th>MIME</th><th>Pantalla</th><th>Presentación</th><th>Resolución</th><th>Tamaño</th><th>Acciones</th></tr></thead><tbody>';
 $dsModals='';foreach($rows as $m){$dlg='med'.(int)$m['id'];
 $editableMedia=can('media.edit')&&(is_super()||(int)$m['tenant_id']===tenant());
 $orientation=(string)$m['orientation'];$layout=(string)$m['layout_type'];
 $guide=ds_media_geometry($orientation,$layout);
 $screenName=orientations()[$orientation]??'Sin clasificar';
 $presentationName=layouts()[$layout]??'Sin clasificar';
 $zoneNote=$guide?($guide['width_pct']===100 && $guide['height_pct']===100?'100% de pantalla':($guide['height_pct']!==100?$guide['height_pct'].'% de alto':$guide['width_pct'].'% de ancho')):'';
 $previewId=(int)$m['id'];
 $isImage=str_starts_with((string)$m['mime'],'image/');
 $sourceLink=url(($isImage?'media-thumbnail.php?id='.$previewId.'&full=1':'media-file.php?id='.$previewId));
 if($isImage){
   $mediaPreview='<img src="'.h(url('media-thumbnail.php?id='.$previewId)).'" loading="lazy" decoding="async" alt="Miniatura de '.h((string)$m['name']).'">';
 }else{
   $mediaPreview='<span class="ds-media-preview-video" aria-label="Medio de video">&#9654;<small>VIDEO</small></span>';
 }
 $screenOrientation=$orientation==='portrait'?'portrait':'landscape';
 $screenDiagram='<div class="ds-preview-mini-screen" data-orientation="'.h($screenOrientation).'" data-layout="'.h($layout).'" role="img" aria-label="'.h($screenName.' · '.$presentationName).'"><span class="ds-mini-selected"></span></div>';
 $presentation='<div class="ds-media-presentation">'.$screenDiagram.'<span class="ds-media-zone-name">'.h($presentationName).'</span>'.($zoneNote!==''?'<small class="ds-media-zone-percent">'.h($zoneNote).'</small>':'').'</div>';
 echo '<tr>'.($editableMedia?'<td class="ds-bulk-select-col"><input class="form-check-input" type="checkbox" data-bulk-id form="ds-bulk-form" name="media_ids[]" value="'.(int)$m['id'].'" aria-label="Seleccionar '.h((string)$m['name']).'"></td>':'<td></td>')
  .'<td class="ds-media-preview-cell"><a class="ds-media-preview-frame" href="'.h($sourceLink).'" target="_blank" rel="noopener" title="Abrir medio original">'.$mediaPreview.'</a></td>'
  .'<td>'.h($m['company_name']).'</td><td class="ds-media-filename">'.h($m['name']).'</td><td>'.h($m['mime']).'</td><td>'.h($screenName).'</td><td>'.$presentation.'</td>'
  .'<td><strong>'.h((string)($m['width_px']??'—')).' × '.h((string)($m['height_px']??'—')).' px</strong>'.($guide?'<small class="d-block text-muted">Formato: '.$guide['width'].' × '.$guide['height'].' px</small>':'').($guide&&(int)$m['width_px']>0&&(int)$m['height_px']>0&&!ds_media_geometry_check((int)$m['width_px'],(int)$m['height_px'],$guide)?'<small class="d-block text-danger">Proporción incompatible</small>':'').'</td><td>'.round((int)$m['size_bytes']/1048576,2).' MB</td>'
  .'<td><a class="btn btn-sm btn-outline-secondary" href="'.h($sourceLink).'" target="_blank" rel="noopener">Ver</a> ';
 if($editableMedia)echo '<button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#'.$dlg.'">Editar</button> <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#remove-media-'.(int)$m['id'].'">Eliminar</button>';
 echo ds_share_checkbox('media',$m).'</td></tr>';
 if($editableMedia){
 ob_start();echo '<div class="modal fade" id="'.$dlg.'" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5>Editar medio</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">';form_open();echo '<input type="hidden" name="action" value="edit"><input type="hidden" name="id" value="'.$m['id'].'">'.company_select((int)$m['tenant_id']).'<label>Nombre</label><input class="form-control mb-2" name="name" value="'.h($m['name']).'" required><label>Orientación de pantalla</label>'.sel('orientation',orientations(),$m['orientation']).'<label>Presentación en pantalla</label>'.sel('layout_type',layouts(),$m['layout_type']).'<div class="row"><div class="col"><label>Ancho px</label><input type="number" min="1" class="form-control" name="width_px" value="'.h((string)($m['width_px']??'')).'"></div><div class="col"><label>Alto px</label><input type="number" min="1" class="form-control" name="height_px" value="'.h((string)($m['height_px']??'')).'"></div></div><p class="text-muted mt-2">Las dimensiones son metadatos; no redimensionan el archivo original. Si hay listas incompatibles, el cambio se bloqueará.</p><button class="btn btn-primary">Guardar</button></form></div></div></div></div>';$dsModals.=ob_get_clean();
 $dsModals.=ds_cleanup_modal('media',(int)$m['id'],(int)$m['tenant_id'],(string)$m['name'],
   'Si se utiliza en una playlist, primero deberás quitarlo de ella. Al eliminarlo se libera espacio en el servidor.');
 }
 }
 echo '</tbody></table></div>'.$dsModals;
});
