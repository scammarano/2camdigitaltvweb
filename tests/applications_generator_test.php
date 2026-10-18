<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/applications.php';require_once ROOT.'/app/playlist_generator.php';
$_SESSION['user']=['id'=>100,'tenant_id'=>1];$_SESSION['active_tenant']=1;
function ok29(bool $v,string $why):void{if(!$v)throw new RuntimeException($why);}
function reject29(callable $f,string $why):void{try{$f();}catch(RuntimeException $e){return;}throw new RuntimeException($why);}
$m=require ROOT.'/database/migrations/0.4.29.php';$m(db());$m(db());
$global=dball('SELECT * FROM media WHERE separator_key IS NOT NULL');ok29(count($global)>=20,'Missing global black/white formats');foreach($global as $m)ok29($m['tenant_id']===null&&(int)$m['is_shared']===1,'Separator company-owned');
$g=ds_media_geometry('landscape','bottom');$one=ds_global_separator('landscape','bottom','#123456');ok29($one===ds_global_separator('landscape','bottom','#123456'),'Color duplicated');ok29(ds_readable_media($one,2)!==null,'Global not readable cross-tenant');
reject29(fn()=>owned_edit_record('media',$one),'Global separator editable');
$mid=(int)dbone("SELECT id FROM media WHERE name='Copy image' AND tenant_id=1")['id'];
$before=(int)dbone('SELECT COUNT(*) n FROM media')['n'];
$lists=ds_generate_playlists(1,[$mid],['playlist_modes'=>['2','4'],'duration'=>'6.5','transition_effect'=>'slide-left','transition_ms'=>'700','separator_color'=>'#000000','separator_duration'=>'0.1']);
ok29(count($lists)===1&&$lists[0]['items']===2,'Generator group/separator missing');$pid=$lists[0]['id'];$p=dbone('SELECT * FROM playlists WHERE id=?',[$pid]);ok29((int)$p['mode_mask']===6&&str_contains($p['name'],'Company A'),'Generator mode/name');
$items=dball('SELECT * FROM playlist_items WHERE playlist_id=? ORDER BY position',[$pid]);ok29((float)$items[0]['duration_seconds']===6.5&&(float)$items[1]['duration_seconds']===0.1,'Generator timings');ok29((int)dbone('SELECT COUNT(*) n FROM media')['n']===$before,'Generator duplicated media');
$tot=(int)dbone('SELECT COUNT(*) n FROM playlists')['n'];reject29(fn()=>ds_generate_playlists(1,[$mid,999999],['playlist_modes'=>['2']]),'Generator accepted inaccessible media');ok29((int)dbone('SELECT COUNT(*) n FROM playlists')['n']===$tot,'Partial generator commit');
reject29(fn()=>ds_generate_playlists(1,[$mid],['playlist_modes'=>['1']]),'Cartelera accepted lower strip');
$c=ds_config_defaults();$c['overlay_playlist_bottom']=$pid;ds_config_validate_playlists($c,1,1);$c['scoreboard_playlist_bottom']=$pid;reject29(fn()=>ds_config_validate_playlists($c,1,1),'Wrong mode playlist accepted');
function add29(int $tid,string $app,array $values):int{return ds_app_entity($tid,$app,ds_app_config($tid,$app)['revision'],['action'=>'save','enabled'=>'1']+$values);}
$room=add29(1,'turnos',['kind'=>'room','name'=>'Sala 29']);$foreign=add29(2,'turnos',['kind'=>'room','name'=>'Sala ajena 29']);
$service=add29(1,'turnos',['kind'=>'service','parent_id'=>$room,'name'=>'Atención general']);$counter=add29(1,'turnos',['kind'=>'counter','parent_id'=>$room,'name'=>'Taquilla 1']);
reject29(fn()=>add29(1,'turnos',['kind'=>'service','parent_id'=>$foreign,'name'=>'Forged']),'Cross-tenant parent');
$screen=add29(1,'turnos',['kind'=>'screen','parent_id'=>$room,'device_id'=>1,'name'=>'Pantalla principal']);
$venue=add29(1,'sports',['kind'=>'venue','name'=>'Club 29']);$court=add29(1,'sports',['kind'=>'court','parent_id'=>$venue,'name'=>'Pista 1','discipline'=>'padel']);
try{add29(1,'sports',['kind'=>'screen','parent_id'=>$court,'device_id'=>1,'name'=>'Conflict']);throw new RuntimeException('Same screen bound to both engines');}catch(PDOException $e){}
reject29(fn()=>ds_app_snapshot(2,'turnos',1),'Foreign device config');
$snapshot=ds_app_snapshot(1,'turnos',1);ok29($snapshot['tenant_id']===1&&!str_contains(json_encode($snapshot),'Sala ajena'),'Cross tenant config leaked');
$rev=ds_app_config(1,'turnos')['revision'];ds_app_save_settings(1,'turnos',$rev,['title'=>'Mi empresa','request_whatsapp'=>'1']);reject29(fn()=>ds_app_save_settings(1,'turnos',$rev,['title'=>'stale']),'Stale config accepted');
reject29(fn()=>ds_app_entity(1,'turnos',ds_app_config(1,'turnos')['revision'],['action'=>'delete','id'=>$room,'kind'=>'room']),'Parent with children deleted');
// Public sports scope includes its venue teams/tournament, never sibling-venue data.
$tournament=add29(1,'sports',['kind'=>'tournament','parent_id'=>$venue,'name'=>'Torneo 29']);
$team=add29(1,'sports',['kind'=>'team','parent_id'=>$venue,'name'=>'Equipo 29']);
$otherVenue=add29(1,'sports',['kind'=>'venue','name'=>'Club aislado']);$otherTeam=add29(1,'sports',['kind'=>'team','parent_id'=>$otherVenue,'name'=>'Equipo aislado']);
$match=add29(1,'sports',['kind'=>'match','parent_id'=>$court,'name'=>'Partido 29','tournament_id'=>(string)$tournament,'home_team_id'=>(string)$team]);
reject29(fn()=>add29(1,'sports',['kind'=>'match','parent_id'=>$court,'name'=>'Forged team','home_team_id'=>(string)$otherTeam]),'Wrong venue team');
execsql("INSERT INTO devices(tenant_id,name,orientation,mode) VALUES(1,'Sports scope fixture','landscape',4)");$sportsDevice=(int)db()->lastInsertId();
add29(1,'sports',['kind'=>'screen','parent_id'=>$court,'device_id'=>$sportsDevice,'name'=>'Pantalla Sports']);
$public=ds_app_snapshot(1,'sports',$sportsDevice);$names=array_column($public['entities'],'name');ok29(in_array('Equipo 29',$names,true)&&in_array('Torneo 29',$names,true)&&!in_array('Equipo aislado',$names,true),'Sports scope missed team/tournament or leaked other venue');
reject29(fn()=>assert_unlinked_before_transfer('devices',$sportsDevice,1),'Bound device transferable');
ds_app_entity(1,'turnos',ds_app_config(1,'turnos')['revision'],['action'=>'save','kind'=>'room','id'=>$room,'name'=>'Sala 29']);reject29(fn()=>ds_app_snapshot(1,'turnos',1),'Inactive room served to screen');
ds_app_entity(1,'turnos',ds_app_config(1,'turnos')['revision'],['action'=>'save','kind'=>'room','id'=>$room,'name'=>'Sala 29','enabled'=>'1']);
$role=dbone('SELECT id FROM roles WHERE tenant_id=2 LIMIT 1');if(!$role){execsql("INSERT INTO roles(tenant_id,name,permissions_json) VALUES(2,'Operador29','[]')");$role=['id'=>(int)db()->lastInsertId()];}
// Fixtures for HTTP revocation/tenant-bound API tests; credentials are ephemeral and never published.
$token=bin2hex(random_bytes(32));execsql('INSERT INTO application_tokens(tenant_id,app,label,token_hash,created_by,created_at) VALUES(1,?,?,?,100,UTC_TIMESTAMP())',['turnos','CI29',hash('sha256',$token)]);$tokenId=(int)db()->lastInsertId();
file_put_contents(ROOT.'/.application-http-fixture.json',json_encode(['token'=>$token,'id'=>$tokenId]));chmod(ROOT.'/.application-http-fixture.json',0600);
echo 'APPLICATIONS_GENERATOR_PASS: global color assets, no copies, decimals, mode checks, atomic generation, tenants, screen exclusivity, revisions and scoped API snapshots'.PHP_EOL;
