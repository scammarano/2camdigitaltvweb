<?php
declare(strict_types=1);
$source=dirname(__DIR__);$fixture=sys_get_temp_dir().'/signage-apk-import-'.bin2hex(random_bytes(6));
foreach(['app','config','storage','public','database/migrations'] as $dir)mkdir($fixture.'/'.$dir,0700,true);
foreach(glob($source.'/app/*.php') as $file)copy($file,$fixture.'/app/'.basename($file));
copy($source.'/app/config-schema.json',$fixture.'/app/config-schema.json');
foreach(glob($source.'/database/migrations/*.php') as $file)copy($file,$fixture.'/database/migrations/'.basename($file));
copy($source.'/config/config.php',$fixture.'/config/config.php');
copy($source.'/public/api-app-update.php',$fixture.'/public/api-app-update.php');
$private=openssl_pkey_new(['private_key_bits'=>3072,'private_key_type'=>OPENSSL_KEYTYPE_RSA]);
file_put_contents($fixture.'/config/update-rsa-pubkey.pem',openssl_pkey_get_details($private)['key']);
require $fixture.'/app/apk_repository.php';
function ck(bool $v,string $message):void{if(!$v)throw new RuntimeException('APK TEST: '.$message);}
function rejects(callable $f,string $message):void{$rejected=false;try{$f();}catch(Throwable $e){$rejected=true;}ck($rejected,$message);}
$pdo=db();
// Only schema creation, not the old migration's runtime APK seeding.
$migration=file_get_contents($source.'/database/migrations/0.4.2.php');preg_match('/\$pdo->exec\("(CREATE TABLE IF NOT EXISTS app_releases .*?)"\);/s',$migration,$match);$pdo->exec($match[1]);
$migration=require $source.'/database/migrations/0.4.23.php';$migration($pdo);$migration($pdo);
$cfg=dbone("SELECT * FROM apk_repository_sources WHERE platform='android-tv'");
$cfg['enabled']=1;$cfg['revision']=(int)$cfg['revision'];
execsql("UPDATE apk_repository_sources SET enabled=1 WHERE platform='android-tv'");
$encoded='';foreach(glob($source.'/.release-assets/android-*.b64') as $chunk)$encoded.=trim(file_get_contents($chunk));
$apk=base64_decode($encoded,true);ck(is_string($apk)&&strlen($apk)>1024,'fixture APK decode');
$path=$fixture.'/actual.apk';file_put_contents($path,$apk);$actual=ds_apk_read_manifest($path);
$manifest=array_merge($actual,['schema'=>1,'product'=>'2cam-apk','platform'=>'android-tv','channel'=>'debug','certificate_sha256'=>$cfg['certificate_sha256'],'size'=>strlen($apk),'sha256'=>hash('sha256',$apk),'notes'=>'Integration test APK']);
function envelope(array $m,string $apk,$key,string $name):string{
 global $fixture;$raw=json_encode($m,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);openssl_sign($raw,$sig,$key,OPENSSL_ALGO_SHA256);
 $path=$fixture.'/'.$name.'.zip';$z=new ZipArchive();$z->open($path,ZipArchive::CREATE|ZipArchive::OVERWRITE);$z->addFromString('manifest.json',$raw);$z->addFromString('manifest.sig',base64_encode($sig));$z->addFromString('app.apk',$apk);$z->close();return $path;
}
$good=envelope($manifest,$apk,$private,'good');$asset=['id'=>922001];
gh_token_save(str_repeat('a',40));
// Switch public -> private with no deletion of credentials/catalog; anonymous mode must not leak token.
$public=array_merge($cfg,['token_mode'=>'public']);apk_source_save($public,'',(int)$cfg['revision']);
$cfg=dbone("SELECT * FROM apk_repository_sources WHERE platform='android-tv'");
ck($cfg['token_mode']==='public'&&apk_source_token($cfg)===''&&gh_token_read()===str_repeat('a',40),'public works without consuming stored token');
$release=['draft'=>false,'published_at'=>'2026-10-16T20:00:00Z','assets'=>[['id'=>$asset['id'],'size'=>filesize($good),'name'=>'2CAM_APK_android-tv_v'.$actual['version_name'].'_RSA.zip']]];
$fetch=static function($path,$token,$repo)use($release){ck($token==='','public metadata request is anonymous');return [$release];};
$download=static function($token,$id,$dest)use($good){ck($token==='','public download is anonymous');return copy($good,$dest);};
$synced=apk_repositories_sync(true,$fetch,$download);ck($synced['android-tv']['ok']&&$synced['android-tv']['imported']===1,'scheduler imported new APK');
ck(apk_repositories_sync(false,static function(){throw new RuntimeException('should be throttled');},$download)===[],'scheduler throttle');
$row=dbone("SELECT * FROM app_releases WHERE platform='android-tv' AND channel='debug' AND version_code=?",[$actual['version_code']]);
ck($row!==null&&$row['status']==='published','published to tests');ck(hash_file('sha256',ds_apk_static_path($row))===$manifest['sha256'],'public binary exact');
ck(!apk_repository_import($cfg,$asset,$good)['imported'],'idempotent');
function query_update(string $channel):array {
 global $fixture;$token=str_repeat('c',64);execsql('UPDATE devices SET device_token_hash=? WHERE id=1',[hash('sha256',$token)]);
 $script='$_SERVER["REQUEST_METHOD"]="GET";$_SERVER["HTTP_AUTHORIZATION"]='.var_export('Bearer '.$token,true).';$_GET='.var_export(['channel'=>$channel,'sdk'=>22],true).';require '.var_export($fixture.'/public/api-app-update.php',true).';';
 $pipes=[];$p=proc_open([PHP_BINARY,'-r',$script],[1=>['pipe','w'],2=>['pipe','w']],$pipes);if(!is_resource($p))throw new RuntimeException('API subprocess unavailable');$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$code=proc_close($p);ck($code===0,'API invocation: '.$err);return json_decode($out,true,16,JSON_THROW_ON_ERROR);
}
ck(query_update('stable')['available']===false,'stable-only does not see tests');
ck(query_update('debug')['version_code']===$actual['version_code'],'tests channel sees new APK');
$new=apk_promote_stable((int)$row['id']);$stable=dbone('SELECT * FROM app_releases WHERE id=?',[$new]);
ck($stable['channel']==='stable'&&$stable['status']==='published'&&$stable['storage_key']===$row['storage_key']&&$stable['sha256']===$row['sha256'],'promotion same binary');
ck(query_update('stable')['sha256']===$manifest['sha256'],'promoted APK offered to stable-only');
ck(query_update('debug')['sha256']===$manifest['sha256'],'tests also sees stable APK');
$failed=apk_repositories_sync(true,static function(){throw new RuntimeException('Simulated GitHub outage');},$download);ck(!$failed['android-tv']['ok']&&str_contains($failed['android-tv']['message'],'Simulated GitHub outage'),'visible network error');ck(query_update('stable')['available'],'network error preserves catalog');
$saved=dbone("SELECT * FROM apk_repository_sources WHERE platform='android-tv'");$saved['token_mode']='shared';apk_source_save($saved,'',(int)$saved['revision']);
$saved=dbone("SELECT * FROM apk_repository_sources WHERE platform='android-tv'");ck(apk_source_token($saved)===str_repeat('a',40),'private restored with stored token');
ck(query_update('stable')['sha256']===$manifest['sha256'],'catalog survives public/private change');
$cfg=$saved;
rejects(fn()=>apk_promote_stable((int)$row['id']),'duplicate promotion');
execsql("UPDATE app_releases SET status='retired' WHERE id=?",[$row['id']]);ck(!apk_repository_import($cfg,$asset,$good)['imported'],'retired not resurrected');
foreach(['platform'=>'sports-control','package'=>'other.android.package','certificate_sha256'=>str_repeat('a',64),'sha256'=>str_repeat('b',64),'version_code'=>$actual['version_code']+1,'min_sdk'=>25] as $key=>$value){$bad=$manifest;$bad[$key]=$value;$path=envelope($bad,$apk,$private,'bad-'.$key);rejects(fn()=>apk_envelope_read($path,$cfg),'reject altered '.$key);}
$badKey=openssl_pkey_new(['private_key_bits'=>3072,'private_key_type'=>OPENSSL_KEYTYPE_RSA]);$path=envelope($manifest,$apk,$badKey,'untrusted');rejects(fn()=>apk_envelope_read($path,$cfg),'untrusted RSA');
$z=new ZipArchive();$z->open($good);$z->addFromString('../bad.php','bad');$z->close();rejects(fn()=>apk_envelope_read($good,$cfg),'extra/traversal entry');
$rel=['draft'=>false,'published_at'=>'2026-10-16T20:00:00Z','assets'=>[['id'=>12,'size'=>2048,'name'=>'2CAM_APK_android-tv_v0.4.16-debug_RSA.zip']]];
ck(count(apk_repository_candidates([$rel],'android-tv'))===1,'completed release accepted');$rel['draft']=true;ck(apk_repository_candidates([$rel],'android-tv')===[],'draft rejected');
rejects(fn()=>apk_source_validate(array_merge($cfg,['repository'=>'evil.com/a/../../b'])),'unsafe repo');
rejects(fn()=>apk_source_validate(array_merge($cfg,['platform'=>'turnos-control'])),'wrong app mapping');
$lock=apk_sync_lock();rejects(fn()=>apk_sync_lock(),'concurrent import lock');flock($lock,LOCK_UN);fclose($lock);
echo "APK_REPOSITORY_PASS: signature, APK metadata/hash, per-app identity, promotion same bytes, duplicate/retired safety, traversal, lock, migration idempotency\n";
