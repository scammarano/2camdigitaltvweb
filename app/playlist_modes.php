<?php
declare(strict_types=1);
function ds_playlist_modes():array{return [1=>'Cartelera',2=>'Overlay',4=>'Turnos',8=>'Marcador deportivo'];}
function ds_playlist_mode_mask(array $post):int {
 $mask=0;foreach(($post['playlist_modes']??[]) as $bit){if(!in_array((string)$bit,['1','2','4','8'],true))throw new RuntimeException('Modo de lista inválido');$mask|=(int)$bit;}
 if(!$mask)throw new RuntimeException('Selecciona al menos un modo de uso para la lista');return $mask;
}
function ds_playlist_modes_form(int $mask=1):string {
 $s='<fieldset class="mb-3"><legend class="fs-6">Modos que pueden usar esta lista</legend>';
 foreach(ds_playlist_modes() as $bit=>$label)$s.='<label class="d-block"><input type="checkbox" name="playlist_modes[]" value="'.$bit.'"'.($mask&$bit?' checked':'').'> '.h($label).'</label>';
 return $s.'</fieldset>';
}
function ds_playlist_mode_names(int $mask):string{return implode(' / ',array_values(array_filter(ds_playlist_modes(),fn($bit)=>($mask&$bit)!==0,ARRAY_FILTER_USE_KEY)));}
