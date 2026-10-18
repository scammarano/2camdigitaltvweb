<?php
declare(strict_types=1);
require_once __DIR__.'/domain.php';
function ds_content_record(string $table,int $id,int $tid):?array {
 if(!in_array($table,['media','playlists'],true))throw new RuntimeException('Contenido inválido');
 return dbone("SELECT * FROM $table WHERE id=? AND (tenant_id=? OR is_shared=1)",[$id,$tid]);
}
/** Sharing a playlist also grants read access to its reachable media, never to unrelated files. */
function ds_shared_media_ids():array {
 static $cache=null,$generation=-1;$now=$GLOBALS['ds_shared_generation']??0;if($cache!==null&&$generation===$now)return $cache;$cache=[];$generation=$now;
 $pending=array_map('intval',array_column(dball('SELECT id FROM playlists WHERE is_shared=1'),'id'));$seen=[];
 for($depth=0;$pending&&$depth<=12;$depth++){
  $pending=array_values(array_diff(array_unique($pending),array_keys($seen)));if(!$pending)break;
  foreach($pending as $id)$seen[$id]=true;$marks=implode(',',array_fill(0,count($pending),'?'));
  $rows=dball("SELECT i.kind,i.media_id,i.child_playlist_id,p.tenant_id owner,m.tenant_id media_owner,m.is_shared media_shared,c.tenant_id child_owner,c.is_shared child_shared FROM playlist_items i JOIN playlists p ON p.id=i.playlist_id AND p.tenant_id=i.tenant_id LEFT JOIN media m ON m.id=i.media_id LEFT JOIN playlists c ON c.id=i.child_playlist_id WHERE i.playlist_id IN ($marks)",$pending);$pending=[];
  foreach($rows as $r){if($r['kind']==='media'&&($r['media_owner']===$r['owner']||(int)$r['media_shared']===1))$cache[(int)$r['media_id']]=true;
   elseif($r['kind']==='playlist'&&($r['child_owner']===$r['owner']||(int)$r['child_shared']===1))$pending[]=(int)$r['child_playlist_id'];}
 }return $cache;
}
function ds_readable_media(int $id,int $tid):?array {
 $m=dbone('SELECT * FROM media WHERE id=?',[$id]);if(!$m)return null;
 return (int)$m['tenant_id']===$tid||(int)$m['is_shared']===1||isset(ds_shared_media_ids()[$id])?$m:null;
}
function ds_content_share(string $table,int $id,int $tid,bool $shared):void {
 require_permission('content.share');if(!is_super()&&tenant()!==$tid)throw new RuntimeException('Empresa inválida');
 if(!in_array($table,['media','playlists'],true))throw new RuntimeException('Contenido inválido');
 db()->beginTransaction();try{
  $row=dbone("SELECT * FROM $table WHERE id=? AND tenant_id=? FOR UPDATE",[$id,$tid]);if(!$row)throw new RuntimeException('Solo el propietario puede compartir este contenido');
  if(!$shared){
   $column=$table==='media'?'media_id':'child_playlist_id';
   if(dbone("SELECT id FROM playlist_items WHERE $column=? AND tenant_id<>? LIMIT 1",[$id,$tid]))throw new RuntimeException('Otras empresas usan este contenido. Retira sus referencias antes de dejar de compartirlo.');
   if($table==='playlists'){
    foreach([['devices','active_playlist_id'],['schedules','playlist_id'],['schedule_items','playlist_id']] as [$tab,$col])if(dbone("SELECT id FROM $tab WHERE $col=? AND tenant_id<>? LIMIT 1",[$id,$tid]))throw new RuntimeException('Hay pantallas o programaciones de otras empresas que utilizan esta lista');
    foreach(dball('SELECT config_json FROM device_config_desired WHERE tenant_id<>?',[$tid]) as $r)foreach(json_decode($r['config_json'],true)?:[] as $key=>$value)if(str_contains($key,'playlist_')&&(int)$value===$id)throw new RuntimeException('Una configuración remota utiliza esta lista compartida');
   }
  }
  execsql("UPDATE $table SET is_shared=? WHERE id=? AND tenant_id=?",[$shared?1:0,$id,$tid]);db()->commit();$GLOBALS['ds_shared_generation']=($GLOBALS['ds_shared_generation']??0)+1;
  log_event('content_sharing_changed',['entity'=>$table,'id'=>$id,'company'=>$tid,'shared'=>$shared]);
 }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
}
function ds_share_checkbox(string $table,array $r):string {
 if(!can('content.share')||(!is_super()&&(int)$r['tenant_id']!==tenant()))return (int)$r['is_shared']===1?'<span class="badge text-bg-info">Compartido · solo lectura fuera de la empresa propietaria</span>':'';
 return '<form method="post" class="ds-share-form"><input type="hidden" name="_csrf" value="'.h(csrf()).'"><input type="hidden" name="action" value="share"><input type="hidden" name="tenant_id" value="'.(int)$r['tenant_id'].'"><input type="hidden" name="id" value="'.(int)$r['id'].'"><input type="hidden" name="playlist_id" value="'.(int)$r['id'].'"><label><input type="checkbox" name="is_shared" value="1"'.((int)$r['is_shared']?' checked':'').' onchange="this.form.submit()"> Disponible para otras empresas</label>'.($table==='playlists'?'<small class="d-block text-muted">Incluye la lectura de los medios contenidos. Las otras empresas no pueden editar el original.</small>':'').'</form>';
}
