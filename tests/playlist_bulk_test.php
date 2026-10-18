<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/playlist_editor.php';
function ck27(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}
function reject27(callable $fn):void{$failed=false;try{$fn();}catch(RuntimeException $e){$failed=true;}ck27($failed,'Invalid batch accepted');}
$_SESSION['user']=['id'=>100,'tenant_id'=>1];$_SESSION['csrf']='bulk-fixture';
$migration=require ROOT.'/database/migrations/0.4.27.php';$migration(db());$migration(db());
ck27(ds_playlist_duration('0.1')===0.1&&ds_playlist_duration('0,1')===0.1,'Tenths parser');foreach(['0','0.01','1.11','-1','NaN'] as $bad)reject27(fn()=>ds_playlist_duration($bad));
execsql("INSERT INTO playlists(tenant_id,name,orientation,layout_type) VALUES(1,'Batch fixture','landscape','bottom')");$pid=(int)db()->lastInsertId();
$media=(int)dbone('SELECT id FROM media WHERE tenant_id=1 AND name LIKE ? LIMIT 1',['Separador blanco%'])['id'];
foreach([10,20,30] as $pos)execsql("INSERT INTO playlist_items(tenant_id,playlist_id,kind,media_id,position,duration_seconds,repeat_count,transition_effect,transition_ms) VALUES(1,?,'media',?,?,0.1,2,'cut',0)",[$pid,$media,$pos]);
$read=static fn()=>dball('SELECT * FROM playlist_items WHERE tenant_id=1 AND playlist_id=? ORDER BY position,id',[$pid]);
$before=$read();$ids=array_map('intval',array_column($before,'id'));$stamp=ds_playlist_stamp($before);
reject27(fn()=>ds_playlist_rows_action(1,$pid,[$ids[0],999999],'duplicate',$stamp));ck27($read()===$before,'Forged selection mutated rows');
ck27(ds_playlist_rows_action(1,$pid,[$ids[0],$ids[2]],'duplicate',$stamp)===2,'Batch duplicate count');$copied=$read();ck27(count($copied)===5,'Batch duplicate size');
ck27((int)$copied[0]['id']===$ids[0]&&(int)$copied[2]['id']===$ids[1]&&(int)$copied[3]['id']===$ids[2],'Copy placement/order');
foreach($copied as $i)ck27((float)$i['duration_seconds']===0.1&&(int)$i['repeat_count']===2&&$i['transition_effect']==='cut'&&(int)$i['transition_ms']===0,'Copy lost fields');
reject27(fn()=>ds_playlist_rows_action(1,$pid,[$ids[0]],'delete',$stamp));ck27($read()===$copied,'Stale delete mutated rows');
$stamp=ds_playlist_stamp($copied);$newIds=[(int)$copied[1]['id'],(int)$copied[4]['id']];ds_playlist_rows_action(1,$pid,$newIds,'delete',$stamp);$remaining=$read();ck27(array_map('intval',array_column($remaining,'id'))===$ids,'Batch delete removed other rows');
$rows=[];foreach($remaining as $i)$rows[$i['id']]=['duration'=>'0.1','repeat'=>'1','transition_effect'=>'cut','transition_ms'=>'0'];ds_playlist_save_grid(1,$pid,$rows,ds_playlist_stamp($remaining));
ck27(expanded(1,$pid)[0]['duration']===0.1,'Manifest rounded tenths');
$_SESSION['user']=['id'=>99,'tenant_id'=>1];reject27(fn()=>ds_playlist_rows_action(2,$pid,$ids,'duplicate',ds_playlist_stamp($read())));
echo "PLAYLIST_BULK_PASS: selected duplicate/delete order, preserved fields, rollback, stale/foreign rejection, decimal migration and 100ms manifest\n";
