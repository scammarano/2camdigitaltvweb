<?php
declare(strict_types=1);
require __DIR__.'/_init.php';
require_once ROOT.'/app/apk_repository.php';
if(!is_super()){http_response_code(403);exit('Solo SUPERADMIN puede gestionar repositorios APK');}
$error='';$notice='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 check_csrf();
 try{
  $action=(string)($_POST['action']??'');
  if($action==='save'){
   apk_source_save($_POST,trim((string)($_POST['read_token']??'')),(int)($_POST['revision']??0));$notice='Conexión guardada. Usa Buscar ahora para comprobar acceso e importar.';
  }elseif($action==='sync'){
   $results=apk_repositories_sync(true);$messages=[];foreach($results as $platform=>$r)$messages[]=ds_apk_platforms()[$platform].': '.$r['message'];$notice=$messages?implode(' · ',$messages):'No hay repositorios activados. Configura una conexión primero.';
  }else throw new RuntimeException('Acción no reconocida');
 }catch(Throwable $e){$error=$e instanceof PDOException?fault($e,'apk_repositories'):$e->getMessage();}
}
$sources=dball('SELECT * FROM apk_repository_sources ORDER BY platform');
render('Repositorios y publicaciones APK',function()use($sources,$error,$notice){
 if($error)echo '<div class="alert alert-danger">'.h($error).'</div>';
 if($notice)echo '<div class="alert alert-info">'.h($notice).'</div>';
 echo '<p>GitHub → importación verificada en <strong>Pruebas</strong> → promoción manual a <strong>Estable</strong>. No se instala nada en los dispositivos sin su política y confirmación Android/Fire OS.</p>';
 echo '<div class="ds-actions"><a class="btn btn-outline-primary" href="'.h(url('apk-releases.php')).'">Administrar APK / promover a Estable</a>';
 form_open();echo '<input type="hidden" name="action" value="sync"><button class="btn btn-primary">Buscar ahora en todos los repositorios activos</button></form></div>';
 echo '<div class="card table-responsive"><table class="table"><thead><tr><th>Aplicación</th><th>Repositorio de publicaciones</th><th>Conexión</th><th>Última consulta UTC</th><th>Última versión encontrada</th><th>Resultado</th><th>Acciones</th></tr></thead><tbody>';
 foreach($sources as $s){$p=$s['platform'];$credential=apk_source_token($s)!==null;
  echo '<tr><td>'.h(ds_apk_platforms()[$p]).'</td><td>'.h($s['repository']).'</td><td>'.((int)$s['enabled']?'Activa':'Desactivada').' · '.($s['token_mode']==='public'?'Público, sin token':($credential?'Token configurado':'Falta token')).'</td><td>'.h($s['last_checked_at']??'Nunca').'</td><td>'.h($s['latest_version']?:'—').'</td><td>'.h($s['last_result']).'</td><td><button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#repo-'.$p.'">Configurar</button></td></tr>';
 }
 echo '</tbody></table></div>';
 foreach($sources as $s){$p=$s['platform'];
  echo '<div class="modal fade" id="repo-'.$p.'" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">'.h(ds_apk_platforms()[$p]).' · Conexión GitHub</h5><button class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div><div class="modal-body">';
  form_open();echo '<input type="hidden" name="action" value="save"><input type="hidden" name="platform" value="'.h($p).'"><input type="hidden" name="revision" value="'.(int)$s['revision'].'">';
  foreach(['repository'=>'Repositorio de publicaciones (propietario/nombre)','android_package'=>'Identificador Android de esta aplicación','certificate_sha256'=>'SHA-256 del certificado permanente autorizado'] as $key=>$label)echo '<label class="form-label">'.h($label).'</label><input class="form-control mb-3" name="'.h($key).'" value="'.h($s[$key]).'" maxlength="201" autocomplete="off">';
  echo '<label class="form-label">Credencial de lectura</label><select class="form-select mb-3" name="token_mode"><option value="public"'.($s['token_mode']==='public'?' selected':'').'>Público, sin token</option><option value="shared"'.($s['token_mode']==='shared'?' selected':'').'>Usar token del actualizador web</option><option value="custom"'.($s['token_mode']==='custom'?' selected':'').'>Token propio para este repositorio</option></select><label class="form-label">Nuevo token propio (vacío conserva el guardado)</label><input class="form-control mb-3" type="password" name="read_token" autocomplete="new-password" value="" maxlength="300"><p class="small text-muted">Si es público, selecciona Público, sin token y deja la contraseña vacía. Si luego vuelve a privado, selecciona token compartido o propio. Token fine-grained: selecciona solo estos repositorios y Contents: Read-only. Se guarda en almacenamiento privado, nunca se muestra ni se exporta. El token compartido se administra en Actualizar desde GitHub.</p>';
  echo '<div class="form-check mb-3"><input id="enable-'.$p.'" type="checkbox" class="form-check-input" name="enabled" value="1"'.((int)$s['enabled']?' checked':'').'><label class="form-check-label" for="enable-'.$p.'">Activar importación periódica de esta aplicación</label></div>';
  echo '<p class="small">Solo se admiten paquetes <code>2CAM_APK_'.h($p).'_vVERSION_RSA.zip</code>, firmados por la autoridad RSA del portal. Un APK suelto o artefacto provisional no se importa. El repositorio de publicaciones puede ser distinto del código fuente; Digital Signage utiliza actualmente el repositorio web para sus entregas.</p><button class="btn btn-primary">Guardar conexión</button></form></div></div></div></div>';
 }
 echo '<div class="card p-3 mt-3"><h3>Consulta automática cada cinco minutos</h3><p>Activa las conexiones y agrega una tarea Cron en el hosting. Funciona también con la web cerrada. No es una automatización de ChatGPT.</p><pre>'.h('*/5 * * * * php '.escapeshellarg(realpath(ROOT).'/app/cli/apk-sync.php')).'</pre><p>Usa el ejecutable PHP 8.1+ de tu hosting si <code>php</code> apunta a otra versión. Sin Cron, solo funcionará Buscar ahora. Las consultas del Fire Stick no ejecutan importaciones.</p><p>La instalación de esta actualización no activa repositorios ni crea tareas del hosting automáticamente.</p></div>';
});
