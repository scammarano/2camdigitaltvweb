<?php
declare(strict_types=1);
require_once __DIR__.'/bootstrap.php';
function ds_formats_defaults():array{return ['revision'=>0,'bottom_height_pct'=>20,'side_width_pct'=>40];}
function ds_formats_current(bool $lock=false):array {
 try{$r=dbone('SELECT revision,bottom_height_pct,side_width_pct FROM media_format_settings WHERE id=1'.($lock?' FOR UPDATE':''));}
 catch(PDOException $e){if(($e->errorInfo[1]??0)===1146)return ds_formats_defaults();throw $e;}
 return $r?array_map('intval',$r):ds_formats_defaults();
}
function ds_formats_validate(array $v):array {
 foreach(['bottom_height_pct','side_width_pct'] as $key)if(!isset($v[$key])||!is_int($v[$key])||$v[$key]<5||$v[$key]>80)throw new RuntimeException('Porcentaje de formato inválido (5–80%).');
 return array_intersect_key($v,array_flip(['bottom_height_pct','side_width_pct']));
}
function ds_formats_update(array $input,int $revision):int {
 $v=ds_formats_validate($input);$pdo=db();$pdo->beginTransaction();
 try{
  $old=ds_formats_current(true);if($old['revision']!==$revision)throw new RuntimeException('Los formatos cambiaron. Recarga antes de guardar.');
  foreach(['bottom'=>'bottom_height_pct','side'=>'side_width_pct'] as $layout=>$key){
   if($v[$key]===$old[$key])continue;
   foreach(dball('SELECT id,orientation,width_px,height_px FROM media WHERE layout_type=?',[$layout]) as $m){
    $oris=$m['orientation']==='any'?['landscape','portrait']:[$m['orientation']];
    foreach($oris as $ori){$canvas=$ori==='portrait'?[1080,1920]:[1920,1080];
     $w=$canvas[0]*($layout==='side'?$v[$key]/100:1);$h=$canvas[1]*($layout==='bottom'?$v[$key]/100:1);
     if((int)$m['width_px']<=0||(int)$m['height_px']<=0||abs(((int)$m['width_px']/(int)$m['height_px'])/($w/$h)-1)>.04)
      throw new RuntimeException('El cambio es incompatible con el medio #'.$m['id'].'. Corrige o reclasifica sus dimensiones antes de cambiar este formato.');
    }
   }
  }
  $next=$revision+1;$pdo->prepare('UPDATE media_format_settings SET revision=?,bottom_height_pct=?,side_width_pct=?,updated_at=UTC_TIMESTAMP() WHERE id=1')->execute([$next,$v['bottom_height_pct'],$v['side_width_pct']]);
  $pdo->commit();return $next;
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
/** Hold the format row while committing an upload/classification. */
function ds_formats_lock_geometry(string $orientation,string $layout,?int $width,?int $height):void {
 ds_formats_current(true);
 if(($width??0)>0&&($height??0)>0){$g=ds_media_geometry($orientation,$layout);if(!$g||!ds_media_geometry_check($width,$height,$g))throw new RuntimeException('Las medidas ya no coinciden con el formato central. Revisa el medio y vuelve a intentar.');}
}
