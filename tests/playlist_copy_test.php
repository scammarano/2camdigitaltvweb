<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/playlist_copy.php';
function verify(bool $v,string $message):void{if(!$v)throw new RuntimeException($message);}
function denied(callable $fn):void{$caught=false;try{$fn();}catch(RuntimeException $e){$caught=true;}verify($caught,'Invalid copy accepted');}
execsql("INSERT INTO users(id,tenant_id,name,email,password_hash,role) VALUES(100,1,'Super','super@example.invalid','unused','superadmin')");
$_SESSION['user']=['id'=>100,'tenant_id'=>1];
$dir=ROOT.'/storage/media/1';if(!is_dir($dir))mkdir($dir,0700,true);$filename=str_repeat('c',40);$content='fixture-media-for-playlist-copy';file_put_contents($dir.'/'.$filename,$content);
execsql('INSERT INTO media(tenant_id,name,filename,mime,size_bytes,sha256,orientation,layout_type,width_px,height_px) VALUES(?,?,?,?,?,?,?,?,?,?)',[1,'Copy image',$filename,'image/png',strlen($content),hash('sha256',$content),'landscape','bottom',1920,216]);$mid=(int)db()->lastInsertId();
execsql("INSERT INTO playlists(tenant_id,name,orientation,layout_type,transition_effect,transition_ms) VALUES(1,'Copy source','landscape','bottom','zoom',700)");$root=(int)db()->lastInsertId();
execsql("INSERT INTO playlists(tenant_id,name,orientation,layout_type) VALUES(1,'Copy child','landscape','bottom')");$child=(int)db()->lastInsertId();
execsql("INSERT INTO playlist_items(tenant_id,playlist_id,kind,media_id,position,duration_seconds,repeat_count,transition_effect,transition_ms) VALUES(1,?,'media',?,20,9,2,'slide-left',900)",[$child,$mid]);
foreach([10,30] as $position)execsql("INSERT INTO playlist_items(tenant_id,playlist_id,kind,child_playlist_id,position) VALUES(1,?,'playlist',?,?)",[$root,$child,$position]);
$before=(int)dbone('SELECT COUNT(*) n FROM media')['n'];$same=ds_playlist_duplicate($root,1,'Same company copy');verify($same['lists']===2&&$same['items']===3&&$same['media_copied']===0,'Same-company graph copy');verify((int)dbone('SELECT COUNT(*) n FROM media')['n']===$before,'Same-company media duplicated unnecessarily');
$sameItems=dball('SELECT child_playlist_id FROM playlist_items WHERE playlist_id=? ORDER BY position,id',[$same['id']]);verify($sameItems[0]['child_playlist_id']===$sameItems[1]['child_playlist_id']&&(int)$sameItems[0]['child_playlist_id']!==$child,'Shared child was not independently cloned once');
$other=ds_playlist_duplicate($root,2,'Other company copy');verify($other['lists']===2&&$other['media_copied']===1,'Cross-company graph copy');
$foreignMedia=dbone('SELECT m.* FROM media m JOIN playlist_items i ON i.media_id=m.id WHERE m.tenant_id=2 AND i.tenant_id=2 AND m.name=?',['Copy image']);verify($foreignMedia!==null,'Destination media ownership');verify(file_get_contents(ROOT.'/storage/media/2/'.$foreignMedia['filename'])===$content,'File content changed');
$source=expanded(1,$root);$copy=expanded(2,$other['id']);foreach($source as &$i)unset($i['media_id']);unset($i);foreach($copy as &$i)unset($i['media_id']);unset($i);verify($source===$copy,'Order/duration/repetitions/transitions changed');
$counts=function():array{return [(int)dbone('SELECT COUNT(*) n FROM playlists')['n'],(int)dbone('SELECT COUNT(*) n FROM playlist_items')['n'],(int)dbone('SELECT COUNT(*) n FROM media')['n'],count(glob(ROOT.'/storage/media/2/*')?:[])];};
$old=$counts();denied(fn()=>ds_playlist_duplicate($root,2,'Other company copy'));verify($counts()===$old,'Duplicate name mutated data');
// The first media is copied before a broken second reference: both DB and file must roll back.
execsql("INSERT INTO playlist_items(tenant_id,playlist_id,kind,media_id,position) VALUES(1,?,'media',1,50)",[$child]);
$old=$counts();denied(fn()=>ds_playlist_duplicate($root,2,'Rollback missing file'));verify($counts()===$old,'Partial file/DB copy survived failure');execsql('DELETE FROM playlist_items WHERE tenant_id=1 AND playlist_id=? AND position=50',[$child]);
execsql("INSERT INTO playlist_items(tenant_id,playlist_id,kind,child_playlist_id,position) VALUES(1,?,'playlist',?,60)",[$child,$root]);$old=$counts();denied(fn()=>ds_playlist_duplicate($root,2,'Cycle copy'));verify($counts()===$old,'Cycle copy survived rollback');execsql('DELETE FROM playlist_items WHERE playlist_id=? AND position=60',[$child]);
$_SESSION['user']=['id'=>99,'tenant_id'=>1];denied(fn()=>ds_playlist_duplicate($root,2,'Forbidden destination'));denied(fn()=>ds_playlist_duplicate($other['id'],1,'Forbidden source'));
echo "PLAYLIST_COPY_PASS: same-company, cross-company files, nested DAG, preserved order/effects, duplicate name, rollback, cycles, tenant isolation\n";
