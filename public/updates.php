<?php
require __DIR__.'/_init.php';require_once ROOT.'/app/updater.php';require_permission('updates.manage');
$msg='';$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 check_csrf();try{
  if(!isset($_FILES['package']))throw new RuntimeException('No se recibió el archivo ZIP. Compruebe la selección del archivo y vuelva a intentarlo.');
  $uploadCode=(int)($_FILES['package']['error']??UPLOAD_ERR_NO_FILE);
  if($uploadCode!==UPLOAD_ERR_OK){
   $uploadErrors=[UPLOAD_ERR_INI_SIZE=>'Archivo superior al límite upload_max_filesize de PHP',UPLOAD_ERR_FORM_SIZE=>'Archivo superior al límite del formulario',UPLOAD_ERR_PARTIAL=>'Carga incompleta',UPLOAD_ERR_NO_FILE=>'No se seleccionó archivo',UPLOAD_ERR_NO_TMP_DIR=>'No está disponible el directorio temporal de PHP',UPLOAD_ERR_CANT_WRITE=>'El servidor no pudo escribir el archivo temporal',UPLOAD_ERR_EXTENSION=>'Una extensión PHP bloqueó la carga'];
   throw new RuntimeException($uploadErrors[$uploadCode]??('Error de carga PHP '.$uploadCode));
  }
  if(!isset($_POST['ack'])||$_POST['ack']!=='1')throw new RuntimeException('Debe confirmar el respaldo y el riesgo de migraciones');
  if(!is_uploaded_file($_FILES['package']['tmp_name']))throw new RuntimeException('Carga inválida');
  $info=package_info($_FILES['package']['tmp_name']);
  $result=update_apply($_FILES['package']['tmp_name']);$msg='Instalada versión '.h($result['version']).'. Respaldo: '.h($result['backup']);
 }catch(Throwable $e){$error=fault($e,'updates');}
}
render('Actualizaciones ZIP firmadas',function()use($msg,$error){
 if($msg)echo '<div class="alert alert-success">'.$msg.'</div>';if($error)echo '<div class="alert alert-danger">'.h($error).'</div>';
 echo '<div class="alert alert-info">Versión actual: '.h(trim(file_get_contents(ROOT.'/version.txt'))).'. Solo se aceptan paquetes firmados por la clave confiable instalada. Actualización con respaldo previo SQL y de archivos afectados.</div>';
 echo '<div class="alert alert-warning">Si una migración SQL falla después de ejecutar ALTER TABLE, MySQL puede requerir restauración manual del respaldo. No actualice sin una copia adicional en cPanel.</div>';
 echo '<div class="alert alert-info">También puedes <a href="'.h(url('github-updates.php')).'">actualizar directamente desde GitHub</a>, sin descargar ni subir ZIP manualmente.</div>';
 echo '<form method="post" enctype="multipart/form-data"><input type="hidden" name="_csrf" value="'.h(csrf()).'"><div class="card card-body"><label>Paquete firmado ZIP</label><input type="file" name="package" accept=".zip" required class="form-control mb-3"><label class="mb-3"><input type="checkbox" name="ack" value="1" required> Confirmo que dispongo de un respaldo externo de MySQL y archivos.</label><button class="btn btn-primary">Validar firma, respaldar e instalar</button></div></form>';
});
