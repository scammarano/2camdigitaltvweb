<?php
/** SUPERADMIN release catalogue: upload, publish/retire, notes. Grid-first UX. */
declare(strict_types=1);
require __DIR__.'/_init.php';
require_once ROOT.'/app/apk_releases.php';
require_once ROOT.'/app/apk_repository.php';
if(!is_super()){http_response_code(403);exit('Solo SUPERADMIN puede publicar APK');}
$error='';$notice='';
if($_SERVER['REQUEST_METHOD']==='POST') {
    check_csrf();
    $action=(string)($_POST['action']??'');
    $storedFile=null;
    try {
        if($action==='upload') {
            $platform=(string)($_POST['platform']??'android-tv');
            if(!isset(ds_apk_platforms()[$platform]))
                throw new RuntimeException('Selecciona la aplicación correspondiente');
            $channel=(string)($_POST['channel']??'debug');
            if(!isset(ds_apk_channels()[$channel]))
                throw new RuntimeException('Canal de publicación incorrecto');
            $notes=trim((string)($_POST['release_notes']??''));
            if(strlen($notes)>5000)throw new RuntimeException('Las notas no pueden superar 5000 caracteres');
            // No version, versionCode, minSdk or SHA-256 fields from the user.
            // All of them are extracted and calculated from the actual uploaded APK.
            $upload=$_FILES['apk_file']??[];
            $file=ds_apk_save_upload($upload);
            $storedFile=ds_apk_storage_path($file['key']);
            $meta=$file['metadata'];
            $version=$meta['version_name'];
            $code=$meta['version_code'];
            $minSdk=$meta['min_sdk'];
            $androidPackage=$meta['package'];
            if($platform==='android-tv' && $androidPackage!=='com.digitalsignage.tv')
                throw new RuntimeException('El APK seleccionado no pertenece a 2Cam Digital Signage. Selecciona la aplicación correcta.');
            if($platform!=='android-tv' && $androidPackage==='com.digitalsignage.tv')
                throw new RuntimeException('Este archivo es Digital Signage; no corresponde a Turnos ni Sports.');
            // Prevent silently replacing an Android app with an unrelated package ID.
            $previous=dbone('SELECT storage_key FROM app_releases WHERE platform=? ORDER BY id DESC LIMIT 1',[$platform]);
            if($previous){
                $previousPath=ds_apk_storage_path((string)$previous['storage_key']);
                if(is_file($previousPath)){
                    $prior=ds_apk_read_manifest($previousPath);
                    if($prior['package']!==$androidPackage)
                        throw new RuntimeException('El APK tiene un identificador Android distinto al de las versiones anteriores de esta aplicación. No se publicará para evitar reemplazar otra app.');
                }
            }
            if(dbone('SELECT id FROM app_releases WHERE platform=? AND channel=? AND version_name=?',[$platform,$channel,$version]))
                throw new RuntimeException('La versión '.$version.' ya está registrada para esa aplicación y canal. No se creó un duplicado.');
            if($notes==='')$notes='APK '.$version.' de '.ds_apk_platforms()[$platform].' · Publicación manual.';
            if(strlen($notes)<8)throw new RuntimeException('Descripción demasiado corta');
            $name=ds_apk_platform_prefix($platform).'-v'.$version.'-'.$channel.'.apk';
            $stmt=db()->prepare("INSERT INTO app_releases(platform,version_name,version_code,channel,status,release_notes,original_filename,storage_key,file_size,sha256,min_sdk,created_by,created_at)
                VALUES(?,?,?,?,'draft',?,?,?,?,?,?,?,UTC_TIMESTAMP())");
            $stmt->execute([$platform,$version,$code,$channel,$notes,$name,$file['key'],$file['size'],$file['sha256'],$minSdk,(int)user()['id']]);
            $id=(int)db()->lastInsertId();
            $storedFile=null; // Ownership transferred to the database: keep file if publication fails.
            log_event('apk_release_uploaded',['platform'=>$platform,'version'=>$version,'channel'=>$channel,'sha256'=>$file['sha256'],'package'=>$androidPackage]);
            if(isset($_POST['publish_now']) && (string)$_POST['publish_now']==='1'){
                $release=dbone('SELECT * FROM app_releases WHERE id=?',[$id]);
                try{
                    ds_apk_publish_static($release);
                    execsql("UPDATE app_releases SET status='published',published_at=UTC_TIMESTAMP() WHERE id=?",[$id]);
                    ds_apk_refresh_shortcut(db());
                    log_event('apk_release_publish',['release_id'=>$id,'version'=>$version]);
                    $notice='APK v'.$version.' publicado correctamente en '.ds_apk_platforms()[$platform].'. SHA-256 calculado automáticamente.';
                }catch(Throwable $publishError){
                    // A partial publish is not a reason to delete the original uploaded APK.
                    log_event('apk_release_publish_error',['release_id'=>$id,'message'=>$publishError->getMessage()]);
                    $stateRow=dbone('SELECT status FROM app_releases WHERE id=?',[$id]);
                    if(($stateRow['status']??'')==='published')
                        $notice='APK v'.$version.' publicado, pero no se pudo actualizar el acceso directo de descarga. Revisa tv.apk; no vuelvas a subir este archivo.';
                    else
                        $notice='Se cargó el APK v'.$version.', pero no se pudo finalizar la publicación. Comprueba el estado y usa el botón Publicar en el listado; no lo vuelvas a subir.';
                }
            }else{
                $notice='APK v'.$version.' recibido correctamente. Está en borrador; puedes publicarlo desde el listado.';
            }
        } elseif($action==='promote') {
            apk_promote_stable((int)($_POST['release_id']??0));
            $notice='Versión promovida a Estable con el mismo archivo, firma y SHA-256. La publicación de Pruebas se conserva.';
        } elseif($action==='edit') {
            $id=(int)($_POST['release_id']??0);
            $r=dbone('SELECT id FROM app_releases WHERE id=?',[$id]);if(!$r)throw new RuntimeException('Versión no encontrada');
            $notes=trim((string)($_POST['release_notes']??''));if(strlen($notes)<8||strlen($notes)>5000)throw new RuntimeException('Descripción: entre 8 y 5000 caracteres');
            execsql('UPDATE app_releases SET release_notes=? WHERE id=?',[$notes,$id]);
            log_event('apk_release_edited',['release_id'=>$id]);$notice='Notas de la versión actualizadas.';
        } elseif($action==='publish'||$action==='retire') {
            $id=(int)($_POST['release_id']??0);
            $r=dbone('SELECT * FROM app_releases WHERE id=?',[$id]);
            if($r && !isset(ds_apk_platforms()[(string)$r['platform']]))throw new RuntimeException('Aplicación no autorizada');
            if(!$r)throw new RuntimeException('Versión no encontrada');
            if($action==='publish'){
                $file=ds_apk_storage_path($r['storage_key']);
                if(!is_file($file)||filesize($file)!==(int)$r['file_size']||!hash_equals($r['sha256'],hash_file('sha256',$file)))
                    throw new RuntimeException('No se puede publicar: el APK falta o su hash es incorrecto');
                ds_apk_publish_static($r);
                try {
                    execsql("UPDATE app_releases SET status='published',published_at=UTC_TIMESTAMP() WHERE id=?",[$id]);
                } catch(Throwable $e) { ds_apk_remove_static($r); throw $e; }
                ds_apk_refresh_shortcut(db());
                $notice='Versión publicada. Descarga directa .apk disponible.';
            }else{
                ds_apk_remove_static($r);
                execsql("UPDATE app_releases SET status='retired' WHERE id=?",[$id]);
                ds_apk_refresh_shortcut(db());
                $notice='Versión retirada del catálogo y de las descargas directas.';
            }
            log_event('apk_release_'.$action,['release_id'=>$id]);
        } else throw new RuntimeException('Acción no reconocida');
    }catch(Throwable $e){
        // A failed INSERT must not leave an orphaned uploaded APK behind.
        if($storedFile!==null&&is_file($storedFile))@unlink($storedFile);
        if($e instanceof PDOException && (int)($e->errorInfo[1]??0)===1062)$error='Esta versión ya existe para ese canal. Usa Editar o publica una versión nueva.';
        else $error=$e instanceof PDOException?fault($e,'apk_releases'):$e->getMessage();
    }
}
$search=trim((string)($_GET['q']??''));$state=(string)($_GET['status']??'all');
if($state!=='all'&&!isset(ds_apk_statuses()[$state]))$state='all';
$app=(string)($_GET['app']??'all');
if($app!=='all'&&!isset(ds_apk_platforms()[$app]))$app='all';
$where=[];$params=[];
if($app!=='all'){$where[]='platform=?';$params[]=$app;}
if(!$where)$where=['1=1'];
if($search!==''){$where[]='(version_name LIKE ? OR original_filename LIKE ?)';$params[]='%'.substr($search,0,80).'%';$params[]='%'.substr($search,0,80).'%';}
if($state!=='all'){$where[]='status=?';$params[]=$state;}
$releases=dball('SELECT * FROM app_releases WHERE '.implode(' AND ',$where).' ORDER BY id DESC LIMIT 300',$params);
$publicUrl=url('app-downloads.php');
render('Versiones y descargas APK',function()use($releases,$error,$notice,$search,$state,$app,$publicUrl){
    if($error)echo '<div class="alert alert-danger">'.h($error).'</div>';
    if($notice)echo '<div class="alert alert-success">'.h($notice).'</div>';
    echo '<p><a class="btn btn-outline-primary" href="'.h(url('apk-repositories.php')).'">Repositorios y publicaciones · Buscar APK en GitHub</a></p>';
    echo '<div class="alert alert-info">Administras las tres aplicaciones Android desde un único catálogo público para todas las empresas. Las herramientas operativas de Turnos y Sports estarán en sus respectivos portales. Solo las versiones <strong>Publicadas</strong> se muestran en el sitio. En Fire TV, utiliza <strong>tv.php</strong> y el enlace directo terminado en <strong>.apk</strong>. Las compilaciones debug son exclusivamente para pruebas.</div>';
    echo '<div class="ds-actions"><a class="btn btn-outline-primary" href="'.h($publicUrl).'" target="_blank" rel="noopener">Abrir página pública de descargas ↗</a><button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newApkModal">+ Publicar nueva versión</button></div>';
    echo '<form method="get" class="ds-filters"><div class="ds-filter-field"><label for="releaseQ">Buscar versión</label><input id="releaseQ" name="q" class="form-control" value="'.h($search).'" placeholder="Ej.: 0.4.3"></div><div class="ds-filter-field"><label for="releaseStatus">Estado</label><select id="releaseStatus" name="status" class="form-select"><option value="all">Todos</option>';
    foreach(ds_apk_statuses() as $key=>$label)echo '<option value="'.h($key).'"'.($state===$key?' selected':'').'>'.h($label).'</option>';
    echo '</select></div><div class="ds-filter-field"><label for="releaseApp">Aplicación</label><select id="releaseApp" name="app" class="form-select"><option value="all">Todas</option>';
    foreach(ds_apk_platforms() as $key=>$label)echo '<option value="'.h($key).'"'.($app===$key?' selected':'').'>'.h($label).'</option>';
    echo '</select></div><button class="btn btn-primary">Filtrar</button><a href="'.h(url('apk-releases.php')).'" class="btn btn-light">Limpiar</a></form>';
    echo '<div class="card table-responsive"><table class="table"><thead><tr><th>Aplicación</th><th>Versión</th><th>Canal</th><th>Estado</th><th>Android mín.</th><th>Tamaño</th><th>Descargas</th><th>SHA-256</th><th>Acciones</th></tr></thead><tbody>';
    $modals='';
    foreach($releases as $r){
      $id=(int)$r['id'];$published=$r['status']==='published';
      echo '<tr><td><strong>'.h(ds_apk_platforms()[$r['platform']]??'Aplicación desconocida').'</strong></td><td><strong>v'.h($r['version_name']).'</strong><br><small class="text-muted">Código '.(int)$r['version_code'].'</small></td><td>'.h(ds_apk_channels()[$r['channel']]??$r['channel']).'</td><td>'.h(ds_apk_statuses()[$r['status']]??$r['status']).'</td><td>API '.(int)$r['min_sdk'].'</td><td>'.h(ds_apk_human_size((int)$r['file_size'])).'</td><td>'.(int)$r['downloads'].'</td><td><code title="'.h($r['sha256']).'">'.h(substr($r['sha256'],0,13)).'…</code></td><td class="ds-row-actions">';
      echo '<button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#releaseEdit'.$id.'">Editar</button> ';
      if($published)echo '<a href="'.h(url('downloads/'.ds_apk_static_basename($r))).'" class="btn btn-sm btn-outline-primary">APK</a> ';
      if($published&&$r['channel']==='debug'){
        form_open();echo '<input type="hidden" name="release_id" value="'.$id.'"><input type="hidden" name="action" value="promote"><button class="btn btn-sm btn-outline-success" onclick="return confirm(\'¿Promover este mismo APK a Estable para los dispositivos que solo admiten estables?\')">Promover a Estable</button></form> ';
      }
      form_open();echo '<input type="hidden" name="release_id" value="'.$id.'"><input type="hidden" name="action" value="'.($published?'retire':'publish').'">';
      echo '<button type="submit" class="btn btn-sm '.($published?'btn-outline-danger':'btn-outline-success').'">'.($published?'Retirar':'Publicar').'</button></form></td></tr>';
      ob_start();echo '<div class="modal fade" tabindex="-1" id="releaseEdit'.$id.'" aria-hidden="true"><div class="modal-dialog modal-dialog-centered modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Editar notas · v'.h($r['version_name']).'</h5><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Cerrar"></button></div><div class="modal-body">';
      form_open();echo '<input type="hidden" name="action" value="edit"><input type="hidden" name="release_id" value="'.$id.'"><label class="form-label">Descripción / novedades</label><textarea name="release_notes" class="form-control mb-3" rows="7" maxlength="5000" required>'.h($r['release_notes']).'</textarea><p class="text-muted small">Para reemplazar un APK, publica una nueva versión; no se modifica el binario de una versión existente.</p><div class="ds-modal-actions"><button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary" type="submit">Guardar cambios</button></div></form></div></div></div></div>';$modals.=ob_get_clean();
    }
    if(!$releases)echo '<tr><td colspan="9" class="text-center text-muted py-4">Sin versiones coincidentes</td></tr>';
    echo '</tbody></table></div>'.$modals;
    echo '<div class="modal fade" id="newApkModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Publicar una APK</h5><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Cerrar"></button></div><div class="modal-body">';
    echo '<div class="alert alert-info"><strong>Es sencillo:</strong> elige la aplicación, selecciona el archivo <code>.apk</code> y pulsa <strong>Subir y publicar</strong>. La versión, el Android mínimo y el SHA-256 se detectan automáticamente del archivo.</div>';
    echo '<form method="post" enctype="multipart/form-data" id="dsEasyApkForm"><input type="hidden" name="_csrf" value="'.h(csrf()).'"><input type="hidden" name="action" value="upload"><div class="row g-3">';
    echo '<div class="col-md-12"><label class="form-label" for="dsApkApp">1. ¿Qué aplicación deseas publicar?</label><select class="form-select" id="dsApkApp" name="platform" required>';
    foreach(ds_apk_platforms() as $key=>$label)echo '<option value="'.h($key).'">'.h($label).'</option>';
    echo '</select></div>';
    echo '<div class="col-md-12"><label class="form-label" for="dsApkFile">2. Selecciona el archivo APK</label><input id="dsApkFile" name="apk_file" type="file" accept=".apk,application/vnd.android.package-archive" class="form-control" required><div class="form-text">Límite de tu hosting: '.h(ds_apk_upload_max_text()).'. Se admite solo el archivo .apk, no el ZIP de GitHub. <strong>No necesitas calcular SHA-256.</strong></div></div>';
    echo '<div class="col-12"><label class="form-label" for="dsApkNotes">Descripción de los cambios (opcional)</label><textarea name="release_notes" id="dsApkNotes" class="form-control" rows="2" maxlength="5000" placeholder="Por ejemplo: nuevas pantallas y correcciones"></textarea></div>';
    echo '<div class="col-12"><details class="border rounded p-3"><summary class="fw-semibold" style="cursor:pointer">Opciones avanzadas (normalmente no hace falta modificarlas)</summary><div class="pt-3">';
    echo '<label class="form-label" for="dsApkChannel">Canal de publicación</label><select id="dsApkChannel" name="channel" class="form-select"><option value="debug">Pruebas (recomendado mientras desarrollamos)</option><option value="beta">Beta</option><option value="stable">Estable</option></select>';
    echo '<div class="form-check mt-3"><input class="form-check-input" id="dsApkPublish" type="checkbox" name="publish_now" value="1" checked><label class="form-check-label" for="dsApkPublish">Publicar automáticamente al terminar la subida</label></div>';
    echo '</div></details></div>';
    echo '</div><div class="ds-modal-actions"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary" id="dsApkPublishButton">Subir y publicar APK</button></div></form></div></div></div></div>';
    echo '<script>(function(){var f=document.getElementById("dsEasyApkForm");if(!f)return;f.addEventListener("submit",function(){var b=document.getElementById("dsApkPublishButton");if(b){b.disabled=true;b.textContent="Subiendo y comprobando APK…";}});})();</script>';

});
