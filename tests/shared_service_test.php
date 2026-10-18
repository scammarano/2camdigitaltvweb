<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/shared_content.php';require ROOT.'/app/device_service.php';require ROOT.'/app/user_access.php';require ROOT.'/app/media_separator.php';require ROOT.'/app/playlist_editor.php';require_once ROOT.'/app/device_config.php';
function ck28(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}
function reject28(callable $fn):void{$caught=false;try{$fn();}catch(RuntimeException $e){$caught=true;}ck28($caught,'Invalid cross-company/service action accepted');}
$m=require ROOT.'/database/migrations/0.4.28.php';$m(db());$m(db());
execsql("INSERT INTO users(id,tenant_id,name,email,password_hash,role,is_active) VALUES(211,2,'Admin B','admin-b@example.invalid',?,'admin',1)",[password_hash('test-password-28',PASSWORD_DEFAULT)]);
$_SESSION['user']=['id'=>100,'tenant_id'=>1];$_SESSION['csrf']='shared-fixture';
function owned28(string $ori,string $layout):int {
 $g=ds_media_geometry($ori,$layout);$png=ds_white_png($g['width'],$g['height']);$filename=bin2hex(random_bytes(20));$dir=ROOT.'/storage/media/1';if(!is_dir($dir))mkdir($dir,0700,true);file_put_contents($dir.'/'.$filename,$png);
 execsql('INSERT INTO media(tenant_id,name,filename,mime,size_bytes,sha256,orientation,layout_type,width_px,height_px) VALUES(1,?,?,?,?,?,?,?,?,?)',['Private sharing fixture '.$ori,$filename,'image/png',strlen($png),hash('sha256',$png),$ori,$layout,$g['width'],$g['height']]);return (int)db()->lastInsertId();
}
$mid=owned28('landscape','bottom');$private=owned28('portrait','fullscreen');
execsql("INSERT INTO playlists(tenant_id,name,orientation,layout_type) VALUES(1,'Shared publisher','landscape','bottom')");$root=(int)db()->lastInsertId();
execsql("INSERT INTO playlist_items(tenant_id,playlist_id,kind,media_id,position,duration_seconds) VALUES(1,?,'media',?,10,0.1)",[$root,$mid]);
execsql("INSERT INTO playlists(tenant_id,name,orientation,layout_type) VALUES(2,'Shared consumer','landscape','bottom')");$consumer=(int)db()->lastInsertId();
$_SESSION['user']=['id'=>211,'tenant_id'=>2];ck28(!can('content.share')&&can('devices.suspend'),'Role defaults');ck28(ds_content_record('playlists',$root,2)===null&&ds_readable_media($mid,2)===null,'Private content leaked');
$_SESSION['user']=['id'=>100,'tenant_id'=>1];ds_content_share('playlists',$root,1,true);
$count=(int)dbone('SELECT COUNT(*) n FROM media')['n'];$files=glob(ROOT.'/storage/media/*/*');
execsql("INSERT INTO playlist_items(tenant_id,playlist_id,kind,child_playlist_id,position) VALUES(2,?,'playlist',?,10)",[$consumer,$root]);
$_SESSION['user']=['id'=>211,'tenant_id'=>2];ck28(ds_content_record('playlists',$root,2)!==null&&ds_readable_media($mid,2)!==null&&ds_readable_media($private,2)===null,'Shared graph access');
ck28(expanded(2,$consumer)[0]['media_id']===$mid&&expanded(2,$consumer)[0]['duration']===0.1,'Shared playback reference');
ck28((int)dbone('SELECT COUNT(*) n FROM media')['n']===$count&&glob(ROOT.'/storage/media/*/*')===$files,'Sharing copied media/files');
reject28(fn()=>owned_edit_record('playlists',$root));reject28(fn()=>ds_playlist_save_grid(1,$root,[],'invalid'));
$cfg=ds_config_defaults();$cfg['queue_playlist_bottom']=$root;ds_config_validate_playlists($cfg,2,2);
$_SESSION['user']=['id'=>100,'tenant_id'=>1];reject28(fn()=>ds_content_share('playlists',$root,1,false));
execsql('DELETE FROM playlist_items WHERE playlist_id=?',[$consumer]);ds_content_share('playlists',$root,1,false);ck28(ds_readable_media($mid,2)===null,'Revocation did not close access');
ds_content_share('media',$mid,1,true);ck28(ds_content_record('media',$mid,2)!==null,'Shared media missing');ds_content_share('media',$mid,1,false);
$_SESSION['user']=['id'=>211,'tenant_id'=>2];$denied=ds_user_denials(['devices.suspend','media.edit']);execsql('UPDATE users SET denied_permissions_json=? WHERE id=211',[json_encode($denied)]);ck28(!can('devices.suspend')&&!can('media.edit')&&can('media.view'),'User restrictions not effective');
execsql('UPDATE users SET is_active=0 WHERE id=211');ck28(user()===null,'Disabled session remained valid');execsql('UPDATE users SET is_active=1,denied_permissions_json=NULL WHERE id=211');
$_SESSION['user']=['id'=>100,'tenant_id'=>1];reject28(fn()=>ds_user_denials(['media.view'],auth()));
$initial=ds_service_state(1,1);ds_service_change(1,1,true,'Test suspension',$initial['revision']);$state=ds_service_state(1,1);ck28($state['suspended']&&$state['reported_revision']!==$state['revision'],'Save invented effective confirmation');
reject28(fn()=>ds_service_report(1,1,$state['revision'],false));ds_service_report(1,1,$state['revision'],true);ck28(ds_service_state(1,1)['reported_suspended']===true,'Service report lost');
reject28(fn()=>ds_service_change(1,1,false,'',$initial['revision']));ds_service_change(1,1,false,'',$state['revision']);
$_SESSION['user']=['id'=>211,'tenant_id'=>2];reject28(fn()=>ds_service_change(1,1,true,'foreign',0));
$_SESSION['user']=['id'=>100,'tenant_id'=>1];ds_content_share('playlists',$root,1,true);execsql("INSERT INTO playlist_items(tenant_id,playlist_id,kind,child_playlist_id,position) VALUES(2,?,'playlist',?,10)",[$consumer,$root]);
execsql('UPDATE devices SET active_playlist_id=? WHERE id=1',[$root]);execsql('UPDATE devices SET active_playlist_id=? WHERE id=2',[$consumer]);
$tokens=[bin2hex(random_bytes(32)),bin2hex(random_bytes(32))];foreach($tokens as $i=>$token)execsql('UPDATE devices SET device_token_hash=? WHERE id=?',[hash('sha256',$token),$i+1]);
execsql('INSERT INTO roles(tenant_id,name,permissions_json) VALUES(1,?,?)',['Publisher fixture',json_encode(['media.view','content.share'])]);$publisherRole=(int)db()->lastInsertId();
execsql("INSERT INTO users(id,tenant_id,name,email,password_hash,role,role_id,is_active) VALUES(212,1,'Publisher','publisher@example.invalid','unused','operator',?,1)",[$publisherRole]);
execsql('UPDATE users SET denied_permissions_json=? WHERE id=211',[json_encode(['devices.suspend'])]);
session_write_close();session_id(bin2hex(random_bytes(16)));session_start();$_SESSION=['user'=>['id'=>211,'tenant_id'=>2],'active_tenant'=>2,'csrf'=>'denied-fixture'];$restrictedSession=session_id();session_write_close();
session_id(bin2hex(random_bytes(16)));session_start();$_SESSION=['user'=>['id'=>212,'tenant_id'=>1],'active_tenant'=>1,'csrf'=>'publisher-fixture'];$publisherSession=session_id();session_write_close();
file_put_contents(ROOT.'/.service-http-fixture.json',json_encode(['tokens'=>$tokens,'media'=>$mid,'private'=>$private,'restricted_session'=>$restrictedSession,'publisher_session'=>$publisherSession]));chmod(ROOT.'/.service-http-fixture.json',0600);
echo "SHARED_SERVICE_PASS: no byte copies, shared graph/media access, private isolation, read-only ownership, revocation guard, per-user restrictions/session invalidation, service request/report/stale/tenant checks\n";
