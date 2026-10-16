<?php
/** Authenticated GitHub transport + RSA-authenticated APK envelope. No signing secrets here. */
declare(strict_types=1);
require_once __DIR__.'/github_updater.php';
require_once __DIR__.'/apk_releases.php';

function apk_source_validate(array $v):array {
 $platform=(string)($v['platform']??'');
 if(!isset(ds_apk_platforms()[$platform]))throw new RuntimeException('Aplicación no autorizada');
 $repo=trim((string)($v['repository']??''));gh_api_url('',$repo);
 $package=trim((string)($v['android_package']??''));$cert=strtolower(trim((string)($v['certificate_sha256']??'')));
 $enabled=isset($v['enabled'])&&(string)$v['enabled']==='1';
 if($package!==''&&!preg_match('/^[A-Za-z][A-Za-z0-9_]*(?:\.[A-Za-z][A-Za-z0-9_]*)+$/D',$package))throw new RuntimeException('Identificador Android inválido');
 if($cert!==''&&!preg_match('/^[a-f0-9]{64}$/D',$cert))throw new RuntimeException('Huella del certificado inválida (64 caracteres SHA-256)');
 if($enabled&&($package===''||$cert===''))throw new RuntimeException('Antes de activar, configura el paquete Android y su certificado autorizado');
 if(($platform==='android-tv'&&$package!=='com.digitalsignage.tv')||($platform!=='android-tv'&&$package==='com.digitalsignage.tv'))throw new RuntimeException('El identificador Android no corresponde a esta aplicación');
 $mode=(string)($v['token_mode']??'shared');if(!in_array($mode,['public','shared','custom'],true))throw new RuntimeException('Origen de token inválido');
 return ['platform'=>$platform,'repository'=>$repo,'android_package'=>$package,'certificate_sha256'=>$cert,'token_mode'=>$mode,'enabled'=>(int)$enabled];
}
function apk_source_token_path(string $platform):string {
 if(!isset(ds_apk_platforms()[$platform]))throw new RuntimeException('Aplicación no autorizada');
 return ROOT.'/storage/secrets/apk-github-'.$platform;
}
function apk_source_token(array $source):?string {
 if($source['token_mode']==='public')return ''; // Deliberately anonymous; do not fall back to stored tokens.
 if($source['token_mode']==='shared')return gh_token_read();
 $path=apk_source_token_path($source['platform']);if(is_link($path))throw new RuntimeException('Ruta de credencial no confiable');
 $value=trim((string)@file_get_contents($path));return $value!==''?$value:null;
}
function apk_source_save(array $input,string $newToken,int $expected):void {
 $v=apk_source_validate($input);$lock=apk_sync_lock();
 try {
  if($newToken!==''){
   if(strlen($newToken)<20||strlen($newToken)>300||!preg_match('/^[A-Za-z0-9_\-]+$/D',$newToken))throw new RuntimeException('Token inválido');
   if($v['token_mode']!=='custom')throw new RuntimeException('Selecciona token propio para guardar una credencial nueva');
  }
  $prior=dbone('SELECT * FROM apk_repository_sources WHERE platform=?',[$v['platform']]);
  if(!$prior||(int)$prior['revision']!==$expected)throw new RuntimeException('La conexión cambió; recarga antes de guardar');
  if($newToken!==''){
   $path=apk_source_token_path($v['platform']);$dir=dirname($path);
   if(is_link($dir)||is_link($path))throw new RuntimeException('Ruta de credencial no confiable');
   if(!is_dir($dir)&&!mkdir($dir,0700,true)&&!is_dir($dir))throw new RuntimeException('No se pudo crear almacenamiento privado');
   $temp=$dir.'/.apk-token-'.bin2hex(random_bytes(8));
   try{if(file_put_contents($temp,$newToken,LOCK_EX)!==strlen($newToken))throw new RuntimeException('No se pudo guardar token');chmod($temp,0600);if(!rename($temp,$path))throw new RuntimeException('No se pudo activar token');}finally{if(is_file($temp))unlink($temp);}
  }
  if($v['enabled']&&apk_source_token($v)===null)throw new RuntimeException('Configura primero el token de lectura');
  execsql('UPDATE apk_repository_sources SET repository=?,android_package=?,certificate_sha256=?,token_mode=?,enabled=?,revision=revision+1,updated_at=UTC_TIMESTAMP() WHERE platform=? AND revision=?',[$v['repository'],$v['android_package'],$v['certificate_sha256'],$v['token_mode'],$v['enabled'],$v['platform'],$expected]);
  log_event('apk_repository_saved',['platform'=>$v['platform'],'repository'=>$v['repository'],'enabled'=>$v['enabled']]);
 }finally{flock($lock,LOCK_UN);fclose($lock);}
}
/** Only completed releases containing the agreed signed envelope, not arbitrary APK artifacts. */
function apk_repository_candidates(array $releases,string $platform):array {
 $out=[];
 foreach($releases as $release){
  if(!is_array($release)||($release['draft']??true)||empty($release['published_at']))continue;
  foreach(($release['assets']??[]) as $asset){
   if(!is_array($asset))continue;
   if(!preg_match('/^2CAM_APK_'.preg_quote($platform,'/').'_v[A-Za-z0-9.-]{1,40}_RSA\.zip$/D',(string)($asset['name']??'')))continue;
   if(!is_int($asset['id']??null)||$asset['id']<1||!is_int($asset['size']??null)||$asset['size']<100||$asset['size']>DS_GITHUB_ASSET_LIMIT)continue;
   $out[]=$asset;
  }
 }
 usort($out,static fn($a,$b)=>$b['id']<=>$a['id']);return $out;
}
/** Strict three-entry ZIP, bounded before decompressing; no archive-controlled filesystem paths. */
function apk_envelope_read(string $path,array $source):array {
 if(!is_file($path)||is_link($path)||filesize($path)>DS_GITHUB_ASSET_LIMIT)throw new RuntimeException('Paquete APK inexistente o demasiado grande');
 $z=new ZipArchive();if($z->open($path)!==true)throw new RuntimeException('Paquete APK ZIP inválido');
 try {
  if($z->numFiles!==3)throw new RuntimeException('El paquete APK debe contener exactamente manifiesto, firma y APK');
  foreach(['manifest.json'=>16000,'manifest.sig'=>2048,'app.apk'=>DS_APK_MAX_BYTES] as $name=>$limit){
   $stat=$z->statName($name);if(!$stat||$stat['size']<1||$stat['size']>$limit)throw new RuntimeException('Entrada APK inválida: '.$name);
   $count=0;for($i=0;$i<$z->numFiles;$i++)if($z->getNameIndex($i)===$name)$count++;
   if($count!==1)throw new RuntimeException('Entrada duplicada o ausente');
  }
  $raw=$z->getFromName('manifest.json');$sig=$z->getFromName('manifest.sig');
  if(!is_string($raw)||!is_string($sig))throw new RuntimeException('Manifiesto o firma ilegibles');
  verify_update_signature($raw,$sig,'RSA-SHA256');
  $m=json_decode($raw,true,16,JSON_THROW_ON_ERROR);
  if(!is_array($m)||($m['schema']??0)!==1||($m['product']??'')!=='2cam-apk'||($m['platform']??'')!==$source['platform']||($m['package']??'')!==$source['android_package']||($m['channel']??'')!=='debug')throw new RuntimeException('Identidad o canal del paquete APK no autorizado');
  if(!is_string($m['certificate_sha256']??null)||!hash_equals($source['certificate_sha256'],$m['certificate_sha256']))throw new RuntimeException('El certificado declarado no coincide con el autorizado');
  if(!preg_match('/^[A-Za-z0-9.-]{1,40}$/D',(string)($m['version_name']??''))||!is_int($m['version_code']??null)||$m['version_code']<1||$m['version_code']>2147483647||!is_int($m['min_sdk']??null)||$m['min_sdk']<19||$m['min_sdk']>999||!is_int($m['size']??null)||$m['size']<1024||$m['size']>DS_APK_MAX_BYTES||!preg_match('/^[a-f0-9]{64}$/D',(string)($m['sha256']??'')))throw new RuntimeException('Metadatos APK inválidos');
  if(($z->statName('app.apk')['size']??0)!==$m['size'])throw new RuntimeException('Tamaño APK distinto del firmado');
  $dir=ROOT.'/storage/apk-releases';if(is_link($dir))throw new RuntimeException('Almacenamiento APK inseguro');
  if(!is_dir($dir)&&!mkdir($dir,0750,true)&&!is_dir($dir))throw new RuntimeException('No se pudo crear almacenamiento APK');
  $temp=tempnam($dir,'.verified-');if($temp===false)throw new RuntimeException('No hay archivo temporal disponible');
  try {
   $input=$z->getStream('app.apk');$output=fopen($temp,'wb');if(!$input||!$output)throw new RuntimeException('No se pudo leer APK');
   try{$copied=stream_copy_to_stream($input,$output,$m['size']+1);}finally{fclose($input);fclose($output);}
   if($copied!==$m['size']||!hash_equals($m['sha256'],hash_file('sha256',$temp)))throw new RuntimeException('SHA-256 o tamaño APK incorrecto');
   ds_apk_check_package($temp);$actual=ds_apk_read_manifest($temp);
   foreach(['package','version_name','version_code','min_sdk'] as $field)if($actual[$field]!==$m[$field])throw new RuntimeException('El APK no coincide con el manifiesto firmado: '.$field);
   return ['manifest'=>$m,'temp'=>$temp];
  }catch(Throwable $e){if(is_file($temp))unlink($temp);throw $e;}
 }finally{$z->close();}
}
/** DB and public-file ownership change together; do not republish retired releases on later polls. */
function apk_repository_import(array $source,array $asset,string $envelope):array {
 $checked=apk_envelope_read($envelope,$source);$m=$checked['manifest'];$temp=$checked['temp'];$owned=null;$release=null;$pdo=db();
 try {
  $pdo->beginTransaction();$s=$pdo->prepare('SELECT * FROM apk_repository_sources WHERE platform=? FOR UPDATE');$s->execute([$source['platform']]);$current=$s->fetch();
  if(!$current||!(int)$current['enabled']||(int)$current['revision']!==(int)$source['revision'])throw new RuntimeException('Conexión modificada durante la importación');
  $s=$pdo->prepare('SELECT * FROM app_releases WHERE platform=? AND version_code=? FOR UPDATE');$s->execute([$source['platform'],$m['version_code']]);$existing=$s->fetchAll();
  foreach($existing as $row)if(!hash_equals($row['sha256'],$m['sha256'])||$row['version_name']!==$m['version_name'])throw new RuntimeException('Conflicto: versión ya registrada con otro binario; no se reemplaza');
  foreach($existing as $row)if($row['channel']==='debug'){
   $pdo->prepare('INSERT IGNORE INTO apk_release_origins(release_id,platform,repository,asset_id,envelope_sha256,certificate_sha256,imported_at) VALUES(?,?,?,?,?,?,UTC_TIMESTAMP())')->execute([$row['id'],$source['platform'],$source['repository'],$asset['id'],hash_file('sha256',$envelope),$m['certificate_sha256']]);
   $pdo->commit();return ['version'=>$m['version_name'],'imported'=>false];
  }
  $key='upload:'.bin2hex(random_bytes(20)).'.apk';$owned=ds_apk_storage_path($key);
  if(!rename($temp,$owned))throw new RuntimeException('No se pudo guardar APK verificada');chmod($owned,0640);
  $notes=substr((string)($m['notes']??'APK importada desde GitHub'),0,5000);
  $s=$pdo->prepare("INSERT INTO app_releases(platform,version_name,version_code,channel,status,release_notes,original_filename,storage_key,file_size,sha256,min_sdk,created_at) VALUES(?,?,?,'debug','draft',?,?,?,?,?,?,UTC_TIMESTAMP())");
  $s->execute([$source['platform'],$m['version_name'],$m['version_code'],$notes,'app-v'.$m['version_name'].'.apk',$key,$m['size'],$m['sha256'],$m['min_sdk']]);
  $id=(int)$pdo->lastInsertId();$s=$pdo->prepare('SELECT * FROM app_releases WHERE id=?');$s->execute([$id]);$release=$s->fetch();ds_apk_publish_static($release);
  $pdo->prepare("UPDATE app_releases SET status='published',published_at=UTC_TIMESTAMP() WHERE id=?")->execute([$id]);
  $pdo->prepare('INSERT INTO apk_release_origins(release_id,platform,repository,asset_id,envelope_sha256,certificate_sha256,imported_at) VALUES(?,?,?,?,?,?,UTC_TIMESTAMP())')->execute([$id,$source['platform'],$source['repository'],$asset['id'],hash_file('sha256',$envelope),$m['certificate_sha256']]);
  $pdo->commit();$owned=null;ds_apk_refresh_shortcut($pdo);
  log_event('apk_repository_imported',['platform'=>$source['platform'],'release_id'=>$id,'version'=>$m['version_name'],'sha256'=>$m['sha256']]);
  return ['version'=>$m['version_name'],'imported'=>true];
 }catch(Throwable $e){if($pdo->inTransaction()){$pdo->rollBack();if($release)ds_apk_remove_static($release);}throw $e;
 }finally{if(is_file($temp))unlink($temp);if($owned!==null&&is_file($owned))unlink($owned);}
}
function apk_sync_lock(){
 $dir=ROOT.'/storage';if(is_link($dir))throw new RuntimeException('Ruta de sincronización insegura');
 $path=$dir.'/apk-sync.lock';if(is_link($path))throw new RuntimeException('Archivo de bloqueo inseguro');
 $lock=fopen($path,'c');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB)){if($lock)fclose($lock);throw new RuntimeException('Ya hay una sincronización en curso; vuelve a intentar');}return $lock;
}
/** CLI scheduler and manual button share exactly this path. No device request starts a network import. */
function apk_repositories_sync(bool $force=false,?callable $fetch=null,?callable $download=null):array {
 $fetch??=static fn($path,$token,$repo)=>gh_http_json($path,$token,$repo);
 $download??=static fn($token,$id,$path,$repo)=>gh_download_asset($token,$id,$path,$repo);
 $lock=apk_sync_lock();$results=[];
 try {
  foreach(dball('SELECT * FROM apk_repository_sources WHERE enabled=1 ORDER BY platform') as $source){
   if(!$force&&$source['last_checked_at']!==null&&strtotime($source['last_checked_at'].' UTC')>time()-300)continue;
   $platform=$source['platform'];$count=0;$latest='';$error=false;
   try {
    $token=apk_source_token($source);if($token===null)throw new RuntimeException('Falta token de lectura');
    $releases=$fetch('/releases?per_page=30',$token,$source['repository']);
    $assets=apk_repository_candidates($releases,$platform);
    // Skip previously imported assets, but new/untrusted assets must still verify before acceptance.
    $attempts=0;
    foreach($assets as $asset){
     $origin=dbone('SELECT r.version_name FROM apk_release_origins o JOIN app_releases r ON r.id=o.release_id WHERE o.platform=? AND o.repository=? AND o.asset_id=?',[$platform,$source['repository'],$asset['id']]);
     if($origin){if($latest==='')$latest=$origin['version_name'];continue;}
     if(++$attempts>3)break;
     $temp=tempnam(ROOT.'/storage','apk-envelope-');if($temp===false)throw new RuntimeException('No se pudo crear temporal');
     try{$download($token,$asset['id'],$temp,$source['repository']);if(filesize($temp)!==$asset['size'])throw new RuntimeException('Descarga incompleta');$r=apk_repository_import($source,$asset,$temp);$count+=(int)$r['imported'];if($latest===''||version_compare($r['version'],$latest,'>'))$latest=$r['version'];}finally{if(is_file($temp))unlink($temp);}
    }
    $message=$assets?($count.' APK nuevas importadas; catálogo actualizado'):'Sin paquetes APK firmados publicados';
   }catch(Throwable $e){$error=true;$incident=bin2hex(random_bytes(6));$message='Error '.$incident.': '.($e instanceof PDOException?'Base de datos no disponible':substr($e->getMessage(),0,350));log_event('apk_repository_error',['platform'=>$platform,'incident'=>$incident,'type'=>get_class($e)]);}
   execsql('UPDATE apk_repository_sources SET last_checked_at=UTC_TIMESTAMP(),last_result=?,latest_version=IF(?=\'\',latest_version,?) WHERE platform=?',[$message,$latest,$latest,$platform]);
   $results[$platform]=['ok'=>!$error,'message'=>$message,'imported'=>$count];
  }
 }finally{flock($lock,LOCK_UN);fclose($lock);}
 return $results;
}
function apk_promote_stable(int $id):int {
 $pdo=db();$public=null;$lock=apk_sync_lock();
 try{
  $pdo->beginTransaction();$s=$pdo->prepare('SELECT * FROM app_releases WHERE id=? FOR UPDATE');$s->execute([$id]);$r=$s->fetch();
  if(!$r||$r['channel']!=='debug'||$r['status']!=='published')throw new RuntimeException('Selecciona una versión de Pruebas publicada');
  $s=$pdo->prepare("SELECT * FROM app_releases WHERE platform=? AND channel='stable' AND version_name=? FOR UPDATE");$s->execute([$r['platform'],$r['version_name']]);$old=$s->fetch();
  if($old){if(!hash_equals($old['sha256'],$r['sha256']))throw new RuntimeException('Conflicto con binario estable existente');throw new RuntimeException('Esta versión ya tiene un registro Estable; adminístralo desde el listado');}
  ds_apk_check_package(ds_apk_storage_path($r['storage_key']));
  $s=$pdo->prepare("INSERT INTO app_releases(platform,version_name,version_code,channel,status,release_notes,original_filename,storage_key,file_size,sha256,min_sdk,created_by,created_at) VALUES(?,?,?,'stable','draft',?,?,?,?,?,?,?,UTC_TIMESTAMP())");
  $s->execute([$r['platform'],$r['version_name'],$r['version_code'],$r['release_notes'],$r['original_filename'],$r['storage_key'],$r['file_size'],$r['sha256'],$r['min_sdk'],(int)(user()['id']??0)?:null]);
  $new=(int)$pdo->lastInsertId();$public=array_merge($r,['id'=>$new]);ds_apk_publish_static($public);
  $pdo->prepare("UPDATE app_releases SET status='published',published_at=UTC_TIMESTAMP() WHERE id=?")->execute([$new]);$pdo->commit();$public=null;
  log_event('apk_promoted_stable',['source_id'=>$id,'release_id'=>$new,'sha256'=>$r['sha256']]);return $new;
 }catch(Throwable $e){if($pdo->inTransaction()){$pdo->rollBack();if($public)ds_apk_remove_static($public);}throw $e;}finally{flock($lock,LOCK_UN);fclose($lock);}
}
