<?php
require __DIR__.'/_init.php';require_once ROOT.'/app/schema.php';require_permission('updates.manage');
$msg='';$check=[];
try{$check=schema_check();}catch(Throwable $e){$msg=fault($e,'schema_check');}
if($_SERVER['REQUEST_METHOD']==='POST'){
 check_csrf();try{
  if(empty($_POST['backup_confirm']))throw new RuntimeException('Debe confirmar que descargó el respaldo de la base de datos');
  $actions=ensure_schema();log_event('migration_applied',['version'=>'0.3.0','changes'=>$actions]);
  $check=schema_check();$msg=$check?'Revisión incompleta: '.implode(', ',$check):'Esquema 0.3.0 verificado y migración aplicada sin eliminar registros.';
 }catch(Throwable $e){$msg=fault($e,'upgrade');}
}
render('Revisión y migración 0.3.0',function()use($msg,$check){
 echo '<div class="alert alert-warning">Haga un respaldo SQL antes de aplicar cambios. Las operaciones ALTER TABLE en MySQL pueden confirmarse individualmente.</div>';
 if($msg)echo '<div class="alert alert-info">'.h($msg).'</div>';
 echo '<div class="card card-body"><strong>Elementos pendientes:</strong><ul>';
 foreach($check as $miss)echo '<li>'.h($miss).'</li>';
 if(!$check)echo '<li>Ninguna columna pendiente detectada</li>';
 echo '</ul>';form_open();echo '<label class="d-block mb-2"><input type="checkbox" name="backup_confirm" value="1" required> He creado y guardado un respaldo SQL de esta instalación.</label><button class="btn btn-primary">Verificar y reparar esquema</button></form></div>';
});
