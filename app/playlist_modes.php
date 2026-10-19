<?php
declare(strict_types=1);
function ds_playlist_modes():array{return [1=>'Cartelera',2=>'Overlay',4=>'Turnos',8=>'Marcador deportivo'];}
function ds_playlist_mode_mask(array $post):int {
 $layout=(string)($post['layout_type']??'');
 if(!isset(layouts()[$layout]))throw new RuntimeException('Selecciona el formato de lista');
 return $layout==='fullscreen'?15:14;
}
function ds_playlist_modes_form(int $mask=1):string {
 return '<p class="text-muted">La orientación y el formato determinan la compatibilidad. Cartelera usa pantalla completa; Overlay, Turnos y Marcador deportivo admiten todos los formatos de su orientación. Barra y franja designan el mismo formato.</p>';
}
function ds_playlist_mode_names(int $mask):string{return implode(' / ',array_values(array_filter(ds_playlist_modes(),fn($bit)=>($mask&$bit)!==0,ARRAY_FILTER_USE_KEY)));}
