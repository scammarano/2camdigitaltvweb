<?php
declare(strict_types=1);
require_once __DIR__.'/playlist_editor.php';require_once __DIR__.'/playlist_copy.php';require_once __DIR__.'/playlist_modes.php';require_once __DIR__.'/media_separator.php';
function ds_generate_playlists(int $tid,array $ids,array $options):array {
 require_permission('playlists.edit');if(!is_super()&&$tid!==tenant())throw new RuntimeException('Empresa inválida');
 $ids=array_values(array_unique(array_map('intval',$ids)));if(!$ids||count($ids)>500||min($ids)<1)throw new RuntimeException('Selecciona entre 1 y 500 medios');
 $mask=ds_playlist_mode_mask($options);$duration=ds_playlist_duration($options['duration']??10);$separatorDuration=ds_playlist_duration($options['separator_duration']??0.1);
 $effect=(string)($options['transition_effect']??'fade');if(!isset(transitions()[$effect]))throw new RuntimeException('Efecto inválido');
 $ms=ds_playlist_integer($options['transition_ms']??600,0,3000,'Duración efecto');$color=(string)($options['separator_color']??'');
 if($color!==''){require_permission('media.edit');if(!preg_match('/^#[a-fA-F0-9]{6}$/D',$color))throw new RuntimeException('Color de separador inválido');}
 $pdo=db();$pdo->beginTransaction();try{
  $company=dbone('SELECT name FROM tenants WHERE id=? FOR UPDATE',[$tid]);if(!$company)throw new RuntimeException('Empresa inválida');ds_formats_current(true);
  $groups=[];foreach($ids as $id){$m=ds_content_record('media',$id,$tid);if(!$m)throw new RuntimeException('Medio no disponible para la empresa');
   if(($mask&2)&&!str_starts_with($m['mime'],'image/'))throw new RuntimeException('Overlay requiere imágenes: desmarca el video o desmarca Overlay');
   if(!in_array($m['orientation'],['landscape','portrait'],true))throw new RuntimeException('Clasifica primero la orientación del medio: '.$m['name']);
   if(($mask&1)&&$m['layout_type']!=='fullscreen')throw new RuntimeException('Cartelera requiere medios de pantalla completa');
   $g=ds_media_geometry($m['orientation'],$m['layout_type']);if(!$g||!ds_media_geometry_check((int)$m['width_px'],(int)$m['height_px'],$g))throw new RuntimeException('Medidas incompatibles: '.$m['name']);
   $groups[$m['orientation'].':'.$m['layout_type']][]=$m;
  }
  $created=[];foreach($groups as $group){$first=$group[0];$base=ds_playlist_copy_name($company['name'].' · '.ds_playlist_mode_names($mask).' · '.layouts()[$first['layout_type']].' · '.orientations()[$first['orientation']],'');$name=$base;
   for($n=2;dbone('SELECT id FROM playlists WHERE tenant_id=? AND name=?',[$tid,$name]);$n++){$name=ds_playlist_copy_name($base,' ('.$n.')');if($n>999)throw new RuntimeException('Demasiadas listas con el mismo nombre');}
   execsql('INSERT INTO playlists(tenant_id,name,orientation,layout_type,mode_mask,transition_effect,transition_ms) VALUES(?,?,?,?,?,?,?)',[$tid,$name,$first['orientation'],$first['layout_type'],$mask,$effect,$ms]);$pid=(int)$pdo->lastInsertId();$pos=0;
   $separator=$color!==''?ds_global_separator($first['orientation'],$first['layout_type'],$color):0;
   foreach($group as $m){foreach([[$m['id'],$duration],...($separator?[[$separator,$separatorDuration]]:[])] as [$mid,$seconds])execsql("INSERT INTO playlist_items(tenant_id,playlist_id,kind,media_id,position,duration_seconds,repeat_count,transition_effect,transition_ms) VALUES(?,?,'media',?,?,?,1,?,?)",[$tid,$pid,$mid,++$pos*10,$seconds,$mid===$separator?'cut':'inherit',$mid===$separator?0:null]);}
   $created[]=['id'=>$pid,'name'=>$name,'items'=>$pos];
  }$pdo->commit();log_event('playlists_generated',['company'=>$tid,'lists'=>count($created),'media'=>count($ids)]);return $created;
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
