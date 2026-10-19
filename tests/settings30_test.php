<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/playlist_assignment.php';require_once ROOT.'/app/media_separator.php';
$m=require ROOT.'/database/migrations/0.4.30.php';$m(db());$m(db());$_SESSION['user']=['id'=>100,'tenant_id'=>1];
function ck30(bool $v,string $why):void{if(!$v)throw new RuntimeException($why);}
function no30(callable $fn):void{try{$fn();}catch(RuntimeException $e){return;}throw new RuntimeException('Invalid assignment accepted');}
$mid=ds_global_separator('landscape','bottom','#123456');
execsql("INSERT INTO playlists(tenant_id,name,orientation,layout_type,is_shared,mode_mask) VALUES(1,'Shared30 selectable','landscape','bottom',1,2)");$pid=(int)db()->lastInsertId();
execsql("INSERT INTO playlist_items(tenant_id,playlist_id,kind,media_id,position,duration_seconds) VALUES(1,?,'media',?,10,3)",[$pid,$mid]);
execsql("INSERT INTO playlists(tenant_id,name,orientation,layout_type,is_shared,mode_mask) VALUES(1,'Private30 forbidden','landscape','bottom',0,2)");$private=(int)db()->lastInsertId();
execsql("INSERT INTO devices(tenant_id,name,orientation,mode) VALUES(2,'Screen30 preview fixture','landscape',1)");$did=(int)db()->lastInsertId();
$before=(int)dbone('SELECT COUNT(*) n FROM media')['n'];$rev=ds_config_for_device($did,2)['revision'];$next=ds_assign_readable_playlist(2,$did,$pid,2,$rev,100);
$c=ds_config_for_device($did,2);ck30($c['settings']['overlay_playlist_bottom']===$pid&&$next===$rev+1,'Shared assignment missing');
ck30($c['settings']['primary_mode']===1,'Assigning advertising silently changed operating module');ck30(count(expanded(2,$pid))===1,'Shared media not expanded');ck30((int)dbone('SELECT COUNT(*) n FROM media')['n']===$before,'Shared assignment copied media');
no30(fn()=>ds_assign_readable_playlist(2,$did,$private,2,$next,100));no30(fn()=>ds_assign_readable_playlist(2,1,$pid,2,$next,100));no30(fn()=>ds_assign_readable_playlist(2,$did,$pid,1,$next,100));no30(fn()=>ds_assign_readable_playlist(2,$did,$pid,2,$rev,100));
ob_start();ds_config_form($c['settings'],2,$did);$html=ob_get_clean();ck30(substr_count($html,'data-tab-target=')===8&&substr_count($html,'data-tab-panel=')===8,'Tabs missing');ck30(str_contains($html,'Shared30 selectable')&&!str_contains($html,'Private30 forbidden'),'Shared picker includes wrong tenant');
$token=bin2hex(random_bytes(32));execsql('UPDATE devices SET device_token_hash=? WHERE id=? AND tenant_id=2',[hash('sha256',$token),$did]);
file_put_contents(ROOT.'/.settings30-fixture.json',json_encode(['device_id'=>$did,'token'=>$token,'playlist'=>$pid,'private'=>$private]));chmod(ROOT.'/.settings30-fixture.json',0600);
echo "SETTINGS30_PASS tabs shared assignment no copies tenant mode shape revision guards\n";
