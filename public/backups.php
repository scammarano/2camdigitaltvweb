<?php
require __DIR__.'/_init.php';require_permission('updates.manage');
$dir=ROOT.'/storage/backups';$list=glob($dir.'/before-*.zip')?:[];
$marker=ROOT.'/storage/maintenance.lock';$notice='';
if($_SERVER['REQUEST_METHOD']==='POST'){check_csrf();if(isset($_POST['release_maintenance']) && $_POST['ack_restore']==='yes' && is_file($marker)){unlink($marker);log_event('maintenance_released',['confirmation'=>'external_sql_checked']);$notice='Bloqueo levantado por SUPERADMIN bajo su responsabilidad.';}}
if(isset($_GET['file'])){
 $file=basename((string)$_GET['file']);if(!preg_match('/^before-[0-9.]+-[A-Za-z0-9-]+\.zip$/D',$file)||!is_file($dir.'/'.$file)){http_response_code(404);exit;}
 header('Content-Type: application/zip');header('Content-Disposition: attachment; filename="'.$file.'"');header('X-Content-Type-Options: nosniff');header('Content-Length: '.filesize($dir.'/'.$file));readfile($dir.'/'.$file);exit;
}
render('Respaldos del actualizador',function()use($list,$marker,$notice){if($notice)echo '<div class="alert alert-info">'.h($notice).'</div>';if(is_file($marker)){echo '<div class="alert alert-danger">Existe un mantenimiento fallido. Verifica los archivos y restaura SQL desde respaldo si hubo migraciones DDL, antes de levantar el bloqueo.</div>';form_open();echo '<input type="hidden" name="ack_restore" value="yes"><button class="btn btn-danger mb-3" name="release_maintenance" value="1" onclick="return confirm(\'¿Comprobaste los archivos y la base de datos?\')">Confirmé recuperación; liberar actualizaciones</button></form>';}echo '<div class="alert alert-info">Incluyen SQL completo y archivos modificados por el paquete. Conserve copia externa. La restauración de SQL tras ALTER TABLE puede requerir intervención en phpMyAdmin.</div><div class="card card-body">';foreach(array_reverse($list) as $f)echo '<p><a href="?file='.rawurlencode(basename($f)).'">'.h(basename($f)).'</a> · '.round(filesize($f)/1048576,2).' MB</p>';echo '</div>';});
