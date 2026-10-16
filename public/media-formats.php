<?php
require __DIR__.'/_init.php';require_once ROOT.'/app/media_formats.php';require_permission('media.view');
$error='';$ok='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 check_csrf();if(!is_super()){http_response_code(403);exit('Solo SUPERADMIN puede modificar los formatos centrales.');}
 try{$v=[];foreach(['bottom_height_pct','side_width_pct'] as $k){$s=$_POST[$k]??'';if(!is_string($s)||!ctype_digit($s))throw new RuntimeException('Porcentaje inválido');$v[$k]=(int)$s;}
  $revision=ds_formats_update($v,(int)($_POST['revision']??-1));log_event('media_formats_updated',['revision'=>$revision,'formats'=>$v]);$ok='Formato central actualizado. Se aplicará a las pantallas al sincronizar su manifiesto.';
 }catch(Throwable $e){$error=$e instanceof PDOException?fault($e,'media_formats'):$e->getMessage();}
}
$v=ds_formats_current();
render('Formatos de medios',function()use($v,$error,$ok){
 if($error)echo '<div class="alert alert-danger">'.h($error).'</div>';if($ok)echo '<div class="alert alert-success">'.h($ok).'</div>';
 echo '<p>Una definición para toda esta instalación y todas sus empresas. Cartelera, Overlay, Turnos y Marcador utilizan las medidas del formato de su playlist. Requiere APK 0.4.15 o posterior. No se modifica desde una pantalla ni desde la LAN.</p><div class="card table-responsive"><table class="table"><thead><tr><th>Formato</th><th>Medida central</th></tr></thead><tbody><tr><td>Franja inferior</td><td>'.$v['bottom_height_pct'].'% del alto</td></tr><tr><td>Franja lateral</td><td>'.$v['side_width_pct'].'% del ancho</td></tr><tr><td>Media pantalla</td><td>50%</td></tr><tr><td>Pantalla completa</td><td>100%</td></tr></tbody></table></div>';
 if(is_super())create_modal('Editar formatos centrales',function()use($v){echo '<input type="hidden" name="revision" value="'.$v['revision'].'"><p>El cambio se rechazará si algún medio existente no conserva la proporción o tiene dimensiones desconocidas. Los archivos no se recortan ni convierten automáticamente.</p>';field('bottom_height_pct','Alto de franja inferior (%)','number',(string)$v['bottom_height_pct']);field('side_width_pct','Ancho de franja lateral (%)','number',(string)$v['side_width_pct']);});
 echo '<a class="btn btn-outline-primary mt-3" href="'.h(url('media-guide.php')).'">Ver medidas de los artes</a>';
});
