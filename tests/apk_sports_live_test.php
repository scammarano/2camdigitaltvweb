<?php
/** Public signed Sports release -> isolated portal catalogue, without any GitHub token. */
declare(strict_types=1);
require dirname(__DIR__).'/app/apk_repository.php';
$migration=require ROOT.'/database/migrations/0.4.24.php';$migration(db());$migration(db());
$source=dbone("SELECT * FROM apk_repository_sources WHERE platform='sports-control'");
if(!$source||$source['android_package']!=='services.twocam.sportscontrol'||$source['certificate_sha256']!=='7bd01e3921783cbbad9e09214d2857a75208a71c56215c219217c101829af7e0'||$source['token_mode']!=='public'||(int)$source['enabled']!==0)throw new RuntimeException('Sports preset migration mismatch');
$revision=$source['revision'];$migration(db());if(dbone("SELECT revision FROM apk_repository_sources WHERE platform='sports-control'")['revision']!==$revision)throw new RuntimeException('Sports migration not idempotent');
execsql("UPDATE apk_repository_sources SET enabled=0 WHERE platform='android-tv'");
execsql("UPDATE apk_repository_sources SET enabled=1 WHERE platform='sports-control'");
$result=apk_repositories_sync(true);$r=$result['sports-control']??null;
if(!$r||!$r['ok']||$r['imported']!==1)throw new RuntimeException('Public Sports import failed: '.($r['message']??'no result'));
$row=dbone("SELECT * FROM app_releases WHERE platform='sports-control' AND channel='debug' AND version_code=3");
if(!$row||$row['status']!=='published'||$row['version_name']!=='0.3.0-test'||(int)$row['min_sdk']!==23||$row['sha256']!=='3acaf8319e56128e74e63941a1365630eeeaab8e9d9c9d656bccc124e7ec76f1'||hash_file('sha256',ds_apk_static_path($row))!==$row['sha256'])throw new RuntimeException('Sports actual catalogue/identity/hash mismatch');
if(dbone("SELECT id FROM app_releases WHERE platform='sports-control' AND channel='stable'"))throw new RuntimeException('Sports promoted automatically');
$again=apk_repositories_sync(true);if(!$again['sports-control']['ok']||$again['sports-control']['imported']!==0)throw new RuntimeException('Sports duplicate on second poll');
// A later administrator identity is protected on repeat migrations.
execsql("UPDATE apk_repository_sources SET android_package='services.twocam.futurecontrol' WHERE platform='sports-control'");$migration(db());
if(dbone("SELECT android_package FROM apk_repository_sources WHERE platform='sports-control'")['android_package']!=='services.twocam.futurecontrol')throw new RuntimeException('Sports migration overwrote administrator configuration');
echo "SPORTS_PUBLIC_IMPORT_PASS: real public RSA envelope, no token, APK metadata/certificate/hash, Pruebas only, idempotent import, administrator identity preserved\n";
