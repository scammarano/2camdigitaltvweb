<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/playlist_editor.php';
require ROOT.'/app/media_separator.php';
function ck26(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}
function reject26(callable $fn):void{$caught=false;try{$fn();}catch(RuntimeException $e){$caught=true;}ck26($caught,'Invalid edit accepted');}
$_SESSION['user']=['id'=>100,'tenant_id'=>1];$_SESSION['csrf']='editor-fixture';
$_SESSION['active_tenant']=2;ck26(company_scope()===2,'Active company ignored');ck26(company_scope('1')===1,'Explicit filter ignored');ck26(company_scope('all')===null,'Explicit all ignored');
unset($_SESSION['active_tenant']);ck26(company_scope()===null,'Global scope not restored');
$_SESSION['user']=['id'=>99,'tenant_id'=>1];$_SESSION['active_tenant']=2;ck26(company_scope('all')===1&&company_scope('2')===1&&company_scope()===1,'Tenant escaped scope');
$_SESSION['user']=['id'=>100,'tenant_id'=>1];$_SESSION['active_tenant']=1;
foreach([1,2] as $tid){
 execsql("INSERT INTO schedules(tenant_id,device_id,playlist_id,days_mask,start_time,end_time) VALUES(?,?,?,127,'11:00','12:00')",[$tid,$tid,$tid]);
 $sid=(int)db()->lastInsertId();execsql('INSERT INTO schedule_items(tenant_id,schedule_id,playlist_id,position) VALUES(?,?,?,0)',[$tid,$sid,$tid]);
 execsql("INSERT INTO device_mode_blocks(tenant_id,device_id,mode,days_mask,start_time,end_time) VALUES(?,?,1,127,'11:00','12:00')",[$tid,$tid]);
}
execsql("INSERT INTO playlists(tenant_id,name,orientation,layout_type) VALUES(1,'Editor fixture','landscape','bottom')");$pid=(int)db()->lastInsertId();
$mid=ds_media_white_separator(1,'landscape','bottom');$again=ds_media_white_separator(1,'landscape','bottom');ck26($again===$mid,'Separator duplicated unnecessarily');
$m=dbone('SELECT * FROM media WHERE id=?',[$mid]);$bytes=file_get_contents(ROOT.'/storage/media/1/'.$m['filename']);$info=getimagesizefromstring($bytes);ck26($info[0]===1920&&$info[1]===216&&hash('sha256',$bytes)===$m['sha256'],'PNG dimensions/hash mismatch');
if(!is_dir(ROOT.'/test-results'))mkdir(ROOT.'/test-results',0700,true);file_put_contents(ROOT.'/test-results/white-bottom.png',$bytes);
foreach(['landscape','portrait'] as $o)foreach(array_keys(layouts()) as $l){$id=ds_media_white_separator(1,$o,$l);$image=dbone('SELECT * FROM media WHERE id=?',[$id]);$g=ds_media_geometry($o,$l);$dims=getimagesize(ROOT.'/storage/media/1/'.$image['filename']);ck26($dims[0]===$g['width']&&$dims[1]===$g['height'],'Incorrect separator geometry');}
foreach([10,20] as $pos)execsql("INSERT INTO playlist_items(tenant_id,playlist_id,kind,media_id,position) VALUES(1,?,'media',?,?)",[$pid,$mid,$pos]);
$read=static fn()=>dball('SELECT * FROM playlist_items WHERE tenant_id=1 AND playlist_id=? ORDER BY position,id',[$pid]);
$before=$read();$stamp=ds_playlist_stamp($before);$rows=[];foreach($before as $i)$rows[$i['id']]=['duration'=>'7','repeat'=>'2','transition_effect'=>'slide-right','transition_ms'=>'1200'];
ds_playlist_save_grid(1,$pid,$rows,$stamp);$saved=$read();foreach($saved as $i)ck26((int)$i['duration_seconds']===7&&(int)$i['repeat_count']===2&&$i['transition_effect']==='slide-right'&&(int)$i['transition_ms']===1200,'Four columns not persisted');
reject26(fn()=>ds_playlist_save_grid(1,$pid,$rows,$stamp));ck26($read()===$saved,'Stale edit changed rows');
$stamp=ds_playlist_stamp($saved);$ids=array_keys($rows);$bad=$rows;$bad[$ids[1]]['repeat']='31';$bad[$ids[0]]['duration']='99';reject26(fn()=>ds_playlist_save_grid(1,$pid,$bad,$stamp));ck26($read()===$saved,'Partial grid edit survived rejection');
$bad=$rows;$bad[999999]=$bad[$ids[1]];unset($bad[$ids[1]]);reject26(fn()=>ds_playlist_save_grid(1,$pid,$bad,$stamp));ck26($read()===$saved,'Forged row mutated list');
$_SESSION['user']=['id'=>99,'tenant_id'=>1];reject26(fn()=>ds_playlist_save_grid(2,$pid,$rows,$stamp));reject26(fn()=>ds_media_white_separator(2,'landscape','bottom'));
$_SESSION['user']=['id'=>100,'tenant_id'=>1];
$rows[$ids[0]]['transition_ms']='';$rows[$ids[0]]['transition_effect']='inherit';ds_playlist_save_grid(1,$pid,$rows,$stamp);$updated=$read();ck26($updated[0]['transition_ms']===null,'Empty duration does not inherit');
echo "PLAYLIST_EDITOR_PASS: four columns, whole-grid rollback, stale/forged/cross-company rejection, scope defaults, ten PNG formats and reuse\n";
