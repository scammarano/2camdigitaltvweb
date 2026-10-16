<?php
/** Read-only GitHub integration against the signed delivery release, isolated CI database. */
declare(strict_types=1);
require dirname(__DIR__).'/app/apk_repository.php';
$key=ROOT.'/.release-assets/update-public.txt';
copy($key,ROOT.'/config/update-rsa-pubkey.pem');
$migration=file_get_contents(ROOT.'/database/migrations/0.4.2.php');preg_match('/\$pdo->exec\("(CREATE TABLE IF NOT EXISTS app_releases .*?)"\);/s',$migration,$match);db()->exec($match[1]);
$m=require ROOT.'/database/migrations/0.4.22.php';$m(db());
$token=getenv('GH_TOKEN');if(!is_string($token)||$token==='')throw new RuntimeException('CI read token missing');
// Actions supplies its own short-lived installation credential, not a user PAT.
// Provision only this isolated fixture; never print it or submit it to the PAT UI.
$credential=apk_source_token_path('android-tv');$dir=dirname($credential);if(!is_dir($dir))mkdir($dir,0700,true);
file_put_contents($credential,$token,LOCK_EX);chmod($credential,0600);
execsql("UPDATE apk_repository_sources SET enabled=1,token_mode='custom' WHERE platform='android-tv'");
$result=apk_repositories_sync(true);$r=$result['android-tv']??null;
if(!$r||!$r['ok']||$r['imported']<1)throw new RuntimeException('Live GitHub import failed: '.($r['message']??'No result'));
$expected=json_decode(file_get_contents(ROOT.'/.release-assets/apk-envelope-manifest.json'),true,16,JSON_THROW_ON_ERROR);
$release=dbone("SELECT * FROM app_releases WHERE platform='android-tv' AND channel='debug' AND version_code=?",[$expected['version_code']]);
if(!$release||$release['status']!=='published'||$release['sha256']!==$expected['sha256']||hash_file('sha256',ds_apk_static_path($release))!==$expected['sha256'])throw new RuntimeException('Live catalog/public download mismatch');
if(dbone("SELECT id FROM app_releases WHERE channel='stable'"))throw new RuntimeException('Importer promoted without approval');
$again=apk_repositories_sync(true);if(!$again['android-tv']['ok']||$again['android-tv']['imported']!==0)throw new RuntimeException('Live import is not idempotent');
unlink($credential);
echo "GITHUB_APK_LIVE_PASS: authenticated release download, trusted RSA, exact APK checksum, published Pruebas catalog, no automatic stable promotion, idempotent recheck\n";
