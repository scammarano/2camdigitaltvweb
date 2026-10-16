<?php
/** Import the real Turnos release anonymously; test its multi-hyphen version using the actual APK parser. */
declare(strict_types=1);
require dirname(__DIR__).'/app/apk_repository.php';
$migration=require ROOT.'/database/migrations/0.4.25.php';$migration(db());$migration(db());
$cfg=dbone("SELECT * FROM apk_repository_sources WHERE platform='turnos-control'");
if(!$cfg||$cfg['android_package']!=='services.twocam.turnos'||$cfg['certificate_sha256']!=='431172fe64dbeb2536f3f08fba96650c5ca6db3fe4cd1f098b216af30638f69c'||$cfg['token_mode']!=='public'||(int)$cfg['enabled']!==0)throw new RuntimeException('Turnos verified preset missing');
$revision=$cfg['revision'];$migration(db());if(dbone("SELECT revision FROM apk_repository_sources WHERE platform='turnos-control'")['revision']!==$revision)throw new RuntimeException('Turnos migration not idempotent');
execsql('UPDATE apk_repository_sources SET enabled=0');$cfg['enabled']=1;apk_source_save($cfg,'',(int)$cfg['revision']);
$result=apk_repositories_sync(true);$r=$result['turnos-control']??null;
if(!$r||!$r['ok']||$r['imported']!==1)throw new RuntimeException('Real Turnos public import failed: '.($r['message']??'no result'));
$row=dbone("SELECT * FROM app_releases WHERE platform='turnos-control' AND channel='debug' AND version_code=200");
if(!$row||$row['version_name']!=='0.2.0-local-test'||$row['status']!=='published'||(int)$row['min_sdk']!==22||$row['sha256']!=='6b3c90eaa0591c02d6e20fed0ba44fbd6125807ba57133e308a4fbc6ad318d49'||hash_file('sha256',ds_apk_static_path($row))!==$row['sha256'])throw new RuntimeException('Turnos imported binary/metadata mismatch');
if(dbone("SELECT id FROM app_releases WHERE platform='turnos-control' AND channel='stable'"))throw new RuntimeException('Turnos automatically promoted to stable');
$again=apk_repositories_sync(true);if(!$again['turnos-control']['ok']||$again['turnos-control']['imported']!==0)throw new RuntimeException('Turnos reimport duplicated');
execsql("UPDATE apk_repository_sources SET android_package='services.twocam.otherturnos' WHERE platform='turnos-control'");$migration(db());
if(dbone("SELECT android_package FROM apk_repository_sources WHERE platform='turnos-control'")['android_package']!=='services.twocam.otherturnos')throw new RuntimeException('Turnos migration replaced administrator identity');
echo "TURNOS_PUBLIC_IMPORT_PASS: real multi-hyphen APK version, public signed envelope, identity/certificate/hash, Pruebas, idempotent import, administrator config preserved\n";
