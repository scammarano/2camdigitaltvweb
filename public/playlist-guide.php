<?php
require __DIR__.'/_init.php';require_once ROOT.'/app/domain.php';require_once ROOT.'/app/playlist_modes.php';require_once ROOT.'/app/media_geometry.php';require_permission('playlists.view');
render('Guía de listas y formatos',function(){
 echo '<p class="lead">Empresa → Orientación de la pantalla → Formato de lista → Medios compatibles → Asignación a pantalla y modo</p><p>Una barra y una franja son el mismo formato. La orientación siempre es la del televisor, no la forma del archivo recortado. Una franja lateral para una pantalla horizontal no se mezcla con una franja lateral para una pantalla vertical.</p>'.ds_playlist_modes_form().'<div class="row g-3">';
 $formats=ds_formats_current();
 foreach(['landscape'=>'Pantalla horizontal','portrait'=>'Pantalla vertical'] as $ori=>$title){echo '<section class="col-md-6"><h2 class="h4">'.h($title).'</h2>';foreach(layouts() as $layout=>$name){
  $g=ds_media_geometry($ori,$layout);$bottom=$formats['bottom_height_pct'];$side=$formats['side_width_pct'];
  $style=match($layout){'bottom'=>"bottom:0;left:0;width:100%;height:$bottom%",'side'=>"top:0;right:0;width:$side%;height:100%",'half_horizontal'=>'bottom:0;left:0;width:100%;height:50%','half_vertical'=>'top:0;right:0;width:50%;height:100%',default=>'inset:0'};
  echo '<div class="card card-body mb-2 d-flex flex-row align-items-center gap-3"><div aria-label="'.h($title.' · '.$name).'" style="position:relative;flex-shrink:0;width:'.($ori==='landscape'?'160':'90').'px;height:'.($ori==='landscape'?'90':'160').'px;background:#dce5ef;border:2px solid #174589;border-radius:4px;overflow:hidden"><span style="position:absolute;background:#07833c;'.$style.'"></span></div><div><strong>'.h($name).'</strong><br>'.h($title).'<br>'.($g?h($g['width'].' × '.$g['height'].' px'):'').'<br><small>'.($layout==='fullscreen'?'Cartelera, Overlay, Turnos y Marcador':'Overlay, Turnos y Marcador').'</small></div></div>';
 }echo '</section>';}
 echo '</div><p>Las medidas se toman de los formatos centrales de Medios; no se ajustan por pantalla. Si no existe una lista compatible con contenido reproducible, el modo no se activa. Las listas compartidas se reutilizan sin copiar los archivos, con los permisos de la empresa de destino.</p><a class="btn btn-primary" href="'.h(url('playlist-generator.php')).'">Generar una lista</a>';
});
