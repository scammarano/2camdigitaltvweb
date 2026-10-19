<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/apk_repository.php';
copy(ROOT.'/.release-assets/update-public.txt',ROOT.'/config/update-rsa-pubkey.pem');
$sql=file_get_contents(ROOT.'/database/migrations/0.4.2.php');preg_match('/\$pdo->exec\("(CREATE TABLE IF NOT EXISTS app_releases .*?)"\);/s',$sql,$match);db()->exec($match[1]);
$m=require ROOT.'/database/migrations/0.4.22.php';$m(db());
$path=apk_source_token_path('android-tv');if(!is_dir(dirname($path)))mkdir(dirname($path),0700,true);
file_put_contents($path,getenv('GH_TOKEN'),LOCK_EX);chmod($path,0600);
execsql("UPDATE apk_repository_sources SET enabled=1,token_mode='custom' WHERE platform='android-tv'");
try{
 $result=apk_repositories_sync(true);if(!($result['android-tv']['ok']??false))throw new RuntimeException('Published APK sync failed');
 $expected=json_decode(file_get_contents(ROOT.'/.apk-release-assets/0420/manifest.json'),true,16,JSON_THROW_ON_ERROR);
 $row=dbone("SELECT * FROM app_releases WHERE platform='android-tv' AND version_code=? AND channel='debug'",[$expected['version_code']]);
 if(!$row||$row['status']!=='published'||$row['sha256']!==$expected['sha256']||hash_file('sha256',ds_apk_static_path($row))!==$expected['sha256'])throw new RuntimeException('Actual published candidate was not imported unchanged');
 if(dbone("SELECT id FROM app_releases WHERE channel='stable'"))throw new RuntimeException('Unexpected stable promotion');
 echo 'APK420_LIVE_IMPORT_PASS: real release, RSA/certificate/hash validation, Pruebas catalog, unchanged APK'.PHP_EOL;
}finally{unlink($path);}
