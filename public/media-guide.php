<?php
/** The ten canonical creative sizes for 2Cam Digital Signage. */
require __DIR__.'/_init.php';
require_once ROOT.'/app/media_geometry.php';
require_permission('media.view');
$orientations=ds_media_orientations();$zones=ds_media_zones();
render('Guía visual de tamaños',static function()use($orientations,$zones){
 echo '<link rel="stylesheet" href="'.h(url('assets/css/media-geometry.css')).'?v=049">';
 echo '<div class="alert alert-info"><strong>Pantalla y archivo son cosas diferentes.</strong> La orientación describe cómo está instalado el televisor; la zona verde indica dónde se verá el contenido. Por eso un arte para una barra lateral puede ser vertical aunque la pantalla sea horizontal.</div>';
 echo '<div class="ds-media-guide-head"><p>Valores estándar a 1080p. <strong>Barra inferior = '.(int)$zones['bottom']['h'].'% de la altura</strong>; <strong>barra lateral = '.(int)$zones['side']['w'].'% del ancho</strong>. Medidas expresadas como <strong>ancho × alto en píxeles</strong>, no centímetros.</p><a class="btn btn-primary" href="'.h(url('media.php')).'">Volver a biblioteca de medios</a></div>';
 foreach($orientations as $key=>$display){
  echo '<h2 class="h5 mt-4">'.h($display['name']).' · '.(int)$display['width'].' × '.(int)$display['height'].' px</h2>';
  echo '<div class="ds-media-layout-guide">';
  foreach($zones as $zone=>$params){$g=ds_media_geometry($key,$zone);
   echo '<div class="ds-media-layout-card" role="group" aria-label="'.h($display['name'].', '.$params['name']).'">';
   echo '<div class="ds-preview-mini-screen" data-orientation="'.h($key).'" data-layout="'.h($zone).'"><div class="ds-mini-selected"></div></div>';
   echo '<strong>'.h($params['name']).'</strong>';
   echo '<span class="ds-media-geometry-dims" style="font-size:17px">'.$g['width'].' × '.$g['height'].' px</span>';
   echo '<small>'.(int)$params['w'].'% del ancho · '.(int)$params['h'].'% de la altura</small>';
   echo '</div>';
  }
  echo '</div>';
 }
 echo '<div class="card card-body"><h3 class="h6">Cómo preparar los archivos</h3><p>Diseña el arte a las dimensiones de la zona, no a las dimensiones completas del televisor, salvo que elijas «Pantalla completa». Se aceptan resoluciones proporcionales (por ejemplo, el doble de píxeles) con tolerancia de proporción del 4%. La plataforma no recorta ni redimensiona permanentemente el archivo; el reproductor puede ajustarlo manteniendo su proporción.</p><p>Los píxeles no equivalen a centímetros sin conocer el tamaño físico y la densidad de la pantalla. Para producción utiliza PNG o JPG en las medidas indicadas. Comprueba el encuadre de los videos: el navegador puede leer sus metadatos, pero la validación completa de dimensiones de video depende del códec y del equipo.</p><p>Las zonas laterales y medias pantallas se sitúan inicialmente a la <strong>derecha</strong>; las horizontales se sitúan <strong>abajo</strong>. Overlay conserva su posición, pero comparte las mismas medidas centrales del formato de medios.</p></div>';
});
