<?php
/** Single allow-listed non-secret schema for device settings (version 1). */
require_once __DIR__.'/bootstrap.php';require_once __DIR__.'/shared_content.php';
function ds_config_schema():array {static $schema;return $schema??=json_decode(file_get_contents(__DIR__.'/config-schema.json'),true,32,JSON_THROW_ON_ERROR);}
function ds_config_defaults():array {return array_column(ds_config_schema()['fields'],'default','key');}
function ds_config_migrate_v1(array $input):array {
 if(($input['update_channel']??'')==='beta')$input['update_channel']='stable'; // Conservative legacy migration.
 if(($input['smart_app']??'')==='scoreboard'){
  if(($input['primary_mode']??0)==3)$input['primary_mode']=4;
  if(isset($input['enabled_modes'])&&($input['enabled_modes']&4))$input['enabled_modes']|=8;
 }
 unset($input['smart_app'],$input['smart_header_pct']);
 foreach(['overlay','queue','scoreboard'] as $m)unset($input[$m.'_width_pct'],$input[$m.'_height_pct']);
 if(($input['overlay_layout']??'')==='right')$input['overlay_layout']='side';
 return $input;
}
function ds_config_validate(array $input):array {
 $input=ds_config_migrate_v1($input);$defaults=ds_config_defaults();
 foreach($input as $key=>$val)if(!array_key_exists($key,$defaults))throw new RuntimeException('Parámetro desconocido: '.$key);
 $full=array_merge($defaults,$input);$out=[];
 foreach(ds_config_schema()['fields'] as $f){$k=$f['key'];$v=$full[$k];
  if($f['type']==='bool'){
   if(!is_bool($v))throw new RuntimeException('Tipo booleano requerido: '.$k);$out[$k]=$v;
  }elseif($f['type']==='enum'){
   if((is_int($f['default'])?!is_int($v):!is_string($v))||!array_key_exists((string)$v,$f['options']))throw new RuntimeException('Opción inválida: '.$k);
   $out[$k]=is_int($f['default'])?(int)$v:(string)$v;
  }else{
   if(!is_int($v)||$v<$f['min']||$v>$f['max'])throw new RuntimeException('Fuera de rango: '.$k);$out[$k]=$v;
  }
 }
 if(($out['enabled_modes']&(1<<($out['primary_mode']-1)))===0)throw new RuntimeException('El modo principal debe estar habilitado');
 return $out;
}
function ds_config_form_input(array $post):array {
 $out=[];foreach(ds_config_schema()['fields'] as $f){$k=$f['key'];
  if($f['type']==='bool')$out[$k]=isset($post[$k]);
  elseif($f['type']==='mask'){$n=0;foreach([1,2,4,8] as $bit)if(isset($post['mode_'.$bit]))$n|=$bit;$out[$k]=$n;}
  elseif($f['type']==='enum'&&!is_int($f['default']))$out[$k]=(string)($post[$k]??'');
  else { $raw=$post[$k]??'';if(!is_string($raw)||!preg_match('/^[0-9]{1,10}$/D',$raw))throw new RuntimeException('Número inválido: '.$f['label']);$out[$k]=(int)$raw;}
 }return ds_config_validate($out);
}
function ds_config_form(array $c,int $tid,int $did):void {
 echo '<p class="alert alert-info">Las dimensiones se administran en Formatos de medios, no por pantalla. Requiere APK 0.4.15 o posterior.</p>';
 $groups=ds_config_schema()['groups'];echo '<div data-settings-tabs><div class="nav nav-pills flex-nowrap overflow-auto gap-2 mb-3" role="tablist">';foreach($groups as $index=>$title)echo '<button type="button" class="nav-link" role="tab" data-tab-target="'.$index.'">'.h($title).'</button>';echo '</div>';
 $group='';foreach(ds_config_schema()['fields'] as $f){$k=$f['key'];
  if($group!==$f['group']){if($group!=='')echo '</section>';echo '<section data-tab-panel="'.array_search($f['group'],$groups,true).'"><h5 class="border-bottom mt-4">'.h($f['group']).'</h5>';$group=$f['group'];if($group==='Overlay')echo '<p>Durante cada aparición se reproduce la playlist con sus duraciones y transiciones. Al terminar la ventana visible, espera la pausa configurada antes de volver a aparecer.</p>';}
  if($f['type']==='bool'){echo '<label class="d-block mb-2"><input type="checkbox" name="'.h($k).'"'.($c[$k]?' checked':'').'> '.h($f['label']).'</label>';continue;}
  if($f['type']==='mask'){echo '<label>'.h($f['label']).'</label>';foreach(ds_config_schema()['modes'] as $id=>$label){$bit=1<<((int)$id-1);echo '<label class="d-block"><input type="checkbox" name="mode_'.$bit.'"'.($c[$k]&$bit?' checked':'').'> '.h($label).'</label>';}continue;}
  echo '<label class="form-label">'.h($f['label']).'</label>';
  if($f['type']==='playlist'){
   $modeBit=str_starts_with($k,'overlay_')?2:(str_starts_with($k,'queue_')?4:(str_starts_with($k,'scoreboard_')?8:1));
   $options=[0=>($k==='signage_playlist_id'?'Programación del portal':'Sin asignar')];
   foreach(dball('SELECT p.id,p.name FROM playlists p JOIN devices d ON (d.tenant_id=p.tenant_id OR p.is_shared=1) WHERE d.id=? AND d.tenant_id=? AND (p.mode_mask & '.$modeBit.')<>0 AND p.layout_type=? AND (p.orientation=d.orientation OR p.orientation=?) ORDER BY p.id',[$did,$tid,$f['layout'],'any']) as $p)$options[(int)$p['id']]='#'.$p['id'].' · '.$p['name'];
   if($c[$k]>0&&!isset($options[$c[$k]]))$options[$c[$k]]='#'.$c[$k].' · No disponible';
   echo sel($k,$options,(string)$c[$k]);
   echo '<a class="btn btn-outline-primary my-2" target="_blank" rel="noopener" data-playlist-preview="'.h($k).'" data-preview-base="'.h(url('preview.php?id=')).'" href="'.h(url('preview.php?id='.$c[$k])).'">▶ Probar lista seleccionada</a>';
  }elseif($f['type']==='enum')echo sel($k,$f['options'],(string)$c[$k]);
  else echo '<input class="form-control mb-2" type="number" name="'.h($k).'" min="'.$f['min'].'" max="'.$f['max'].'" value="'.(int)$c[$k].'" required>';
 }
 echo '</section></div>';
}
function ds_config_validate_playlists(array $clean,int $tid,int $did):void {
 $d=dbone('SELECT orientation FROM devices WHERE id=? AND tenant_id=?',[$did,$tid]);if(!$d)throw new RuntimeException('Pantalla de otra empresa');
 foreach($clean as $key=>$id){
  if(!str_contains($key,'playlist_')||!$id)continue;
  $layout=$key==='signage_playlist_id'?'fullscreen':preg_replace('/^(overlay|queue|scoreboard)_playlist_/','',$key);
  $p=ds_content_record('playlists',(int)$id,$tid);
  $modeBit=str_starts_with($key,'overlay_')?2:(str_starts_with($key,'queue_')?4:(str_starts_with($key,'scoreboard_')?8:1));
  if(!$p||!((int)$p['mode_mask']&$modeBit)||$p['layout_type']!==$layout||!in_array($p['orientation'],['any',$d['orientation']],true))throw new RuntimeException('Playlist incompatible o de otra empresa: '.$key);
 }
}
function ds_config_json(array $settings):string {return json_encode(ds_config_validate($settings),JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function ds_config_for_device(int $deviceId,int $tid):array {
 $row=dbone('SELECT revision,config_json,changed_at FROM device_config_desired WHERE device_id=? AND tenant_id=?',[$deviceId,$tid]);
 if($row){$v=json_decode($row['config_json'],true,8,JSON_THROW_ON_ERROR);
   if(!array_key_exists('primary_mode',$v)){$device=dbone('SELECT mode FROM devices WHERE id=? AND tenant_id=?',[$deviceId,$tid]);$rawMode=(int)($device['mode']??1);$mask=(int)($v['enabled_modes']??7);$v['primary_mode']=($mask&(1<<($rawMode-1)))?$rawMode:(($mask&1)?1:(($mask&2)?2:3));}
   return ['revision'=>(int)$row['revision'],'settings'=>ds_config_validate($v),'changed_at'=>$row['changed_at']];}
 $d=dbone('SELECT mode FROM devices WHERE id=? AND tenant_id=?',[$deviceId,$tid]);
 $defaults=ds_config_defaults();$defaults['primary_mode']=(int)($d['mode']??1);
 return ['revision'=>0,'settings'=>ds_config_validate($defaults),'changed_at'=>null];
}
function ds_mode_layout(array $c,int $mode):string {
 return match($mode){2=>match($c['overlay_layout']){'top'=>'bottom','left'=>'side',default=>$c['overlay_layout']},3=>$c['queue_layout'],4=>$c['scoreboard_layout'],default=>'fullscreen'};
}
function ds_mode_playlist(array $c,int $mode):int {
 return $mode===1?$c['signage_playlist_id']:$c[match($mode){2=>'overlay',3=>'queue',default=>'scoreboard'}.'_playlist_'.ds_mode_layout($c,$mode)];
}
function ds_mode_require_playlist(array $c,int $tid,int $did,int $mode):void {
 require_once __DIR__.'/domain.php';
 if($mode<1||$mode>4||!($c['enabled_modes']&(1<<($mode-1))))throw new RuntimeException('El modo no está habilitado');
 $layout=ds_mode_layout($c,$mode);$id=ds_mode_playlist($c,$mode);$ids=$id>0?[$id]:[];
 if(!$ids&&$mode===1){
  $device=dbone('SELECT active_playlist_id FROM devices WHERE id=? AND tenant_id=?',[$did,$tid]);
  if(!empty($device['active_playlist_id']))$ids[]=(int)$device['active_playlist_id'];
  foreach(dball('SELECT COALESCE(si.playlist_id,s.playlist_id) playlist_id FROM schedules s LEFT JOIN schedule_items si ON si.schedule_id=s.id AND si.tenant_id=s.tenant_id WHERE s.device_id=? AND s.tenant_id=?',[$did,$tid]) as $r)$ids[]=(int)$r['playlist_id'];
 }
 foreach(array_unique($ids) as $pid){
  $modeBit=1<<($mode-1);
  $list=dbone('SELECT p.id FROM playlists p JOIN devices d ON (d.tenant_id=p.tenant_id OR p.is_shared=1) WHERE p.id=? AND d.tenant_id=? AND d.id=? AND (p.mode_mask & '.$modeBit.')<>0 AND p.layout_type=? AND (p.orientation=d.orientation OR p.orientation=?)',[$pid,$tid,$did,$layout,'any']);
  if(!$list)continue;
  foreach(expanded($tid,$pid) as $item)if(($item['layout']??'fullscreen')===$layout && ($mode!==2||str_starts_with($item['mime'],'image/')))return;
 }
 $name=ds_config_schema()['modes'][(string)$mode];
 throw new RuntimeException($name.': asigna una playlist compatible y con contenido para '.$layout.' antes de activar'.($mode===2?' (Overlay requiere imágenes)':''));
}
function ds_mode_activation_changed(array $old,array $new):bool {
 $mode=$new['primary_mode'];return $old['primary_mode']!==$mode || ds_mode_layout($old,$mode)!==ds_mode_layout($new,$mode) || ds_mode_playlist($old,$mode)!==ds_mode_playlist($new,$mode);
}
function ds_config_publish(int $deviceId,int $tid,array $settings,int $expectedRevision,?int $uid=null):int {
 $clean=ds_config_validate($settings);ds_config_validate_playlists($clean,$tid,$deviceId);$json=ds_config_json($clean);
 $before=ds_config_for_device($deviceId,$tid)['settings'];
 if(ds_mode_activation_changed($before,$clean))ds_mode_require_playlist($clean,$tid,$deviceId,$clean['primary_mode']);
 $pdo=db();$pdo->beginTransaction();
 try {
  $stmt=$pdo->prepare('SELECT id FROM devices WHERE id=? AND tenant_id=? FOR UPDATE');$stmt->execute([$deviceId,$tid]);if(!$stmt->fetch())throw new RuntimeException('Pantalla no encontrada en la empresa');
  $stmt=$pdo->prepare('SELECT revision FROM device_config_desired WHERE device_id=? AND tenant_id=? FOR UPDATE');$stmt->execute([$deviceId,$tid]);$row=$stmt->fetch(PDO::FETCH_ASSOC);
  $actual=$row?(int)$row['revision']:0;
  if($actual!==$expectedRevision)throw new RuntimeException('La configuración fue modificada por otro administrador. Recarga antes de guardar.');
  $next=$actual+1;
  if($row){$stmt=$pdo->prepare('UPDATE device_config_desired SET revision=?,config_json=?,changed_by=?,changed_at=UTC_TIMESTAMP() WHERE device_id=? AND tenant_id=?');$stmt->execute([$next,$json,$uid,$deviceId,$tid]);}
  else {$stmt=$pdo->prepare('INSERT INTO device_config_desired(device_id,tenant_id,revision,config_json,changed_by,changed_at) VALUES(?,?,?,?,?,UTC_TIMESTAMP())');$stmt->execute([$deviceId,$tid,$next,$json,$uid]);}
  // Overlay options remain consistent with the existing portal module.
  $stmt=$pdo->prepare('INSERT INTO device_runtime_options(device_id,tenant_id,overlay_interval_sec,overlay_duration_sec,modes_enabled) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE overlay_interval_sec=VALUES(overlay_interval_sec),overlay_duration_sec=VALUES(overlay_duration_sec),modes_enabled=VALUES(modes_enabled)');
  $stmt->execute([$deviceId,$tid,$clean['overlay_interval_sec'],$clean['overlay_duration_sec'],$clean['enabled_modes']]);
  // Primary mode is the fallback in manifest; scheduled blocks may override it.
  $stmt=$pdo->prepare('UPDATE devices SET mode=? WHERE id=? AND tenant_id=?');
  $stmt->execute([$clean['primary_mode'],$deviceId,$tid]);
  $existing=$pdo->prepare('SELECT widget_type FROM device_live_panels WHERE device_id=? AND tenant_id=? FOR UPDATE');
  $existing->execute([$deviceId,$tid]);$found=$existing->fetch(PDO::FETCH_ASSOC);
  if(!$found || $found['widget_type']!==($clean['primary_mode']===4?'scoreboard':'queue')){
   $payload=($clean['primary_mode']===4?'scoreboard':'queue')==='queue'
     ? ['title'=>'Sala de espera','ticket'=>'--','counter'=>'1','last_issued'=>0,'last_called'=>0,'call_sequence'=>0,'message'=>'']
     : ['competition'=>'Marcador','home'=>'LOCAL','away'=>'VISITANTE','home_score'=>0,'away_score'=>0,'period'=>'1','clock'=>'00:00'];
   $pdo->prepare('INSERT INTO device_live_panels(device_id,tenant_id,widget_type,payload_json) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE widget_type=VALUES(widget_type),payload_json=VALUES(payload_json),updated_at=CURRENT_TIMESTAMP')
       ->execute([$deviceId,$tid,($clean['primary_mode']===4?'scoreboard':'queue'),json_encode($payload,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)]);
  }
  $pdo->commit();return $next;
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
function ds_config_backup(int $deviceId,int $tid,string $source,string $label,?int $uid=null):int {
 if(!in_array($source,['desired','reported','manual'],true))throw new RuntimeException('Tipo de respaldo inválido');
 if(!dbone('SELECT id FROM devices WHERE id=? AND tenant_id=?',[$deviceId,$tid]))throw new RuntimeException('Pantalla inválida');
 if($source==='reported'){
  $r=dbone('SELECT applied_revision,config_json FROM device_config_reported WHERE device_id=? AND tenant_id=?',[$deviceId,$tid]);
  if(!$r || !$r['config_json'])throw new RuntimeException('La APK todavía no ha reportado su configuración');
  $settings=ds_config_validate(json_decode($r['config_json'],true,8,JSON_THROW_ON_ERROR));$rev=(int)$r['applied_revision'];
 }else {$r=ds_config_for_device($deviceId,$tid);$settings=$r['settings'];$rev=$r['revision'];}
 execsql('INSERT INTO device_config_backups(device_id,tenant_id,source,label,config_json,revision,created_by,created_at) VALUES(?,?,?,?,?,?,?,UTC_TIMESTAMP())',[$deviceId,$tid,$source,substr($label,0,150),ds_config_json($settings),$rev,$uid]);
 return (int)db()->lastInsertId();
}
