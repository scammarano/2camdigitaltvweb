<?php
declare(strict_types=1);
// Exercise the actual updater in an isolated installation, including SQL backup.
$source=dirname(__DIR__);$fixture=sys_get_temp_dir().'/signage-updater-'.bin2hex(random_bytes(6));
foreach(['app','database','config','storage'] as $dir)mkdir($fixture.'/'.$dir,0700,true);
foreach(glob($source.'/app/*.php') as $file)copy($file,$fixture.'/app/'.basename($file));
copy($source.'/app/config-schema.json',$fixture.'/app/config-schema.json');
copy($source.'/config/config.php',$fixture.'/config/config.php');
file_put_contents($fixture.'/version.txt',"0.4.16\n");
$private=openssl_pkey_new(['private_key_bits'=>3072,'private_key_type'=>OPENSSL_KEYTYPE_RSA]);
if($private===false)throw new RuntimeException('Test key generation failed');
$public=openssl_pkey_get_details($private)['key'];file_put_contents($fixture.'/config/update-rsa-pubkey.pem',$public);
$paths=json_decode(file_get_contents(__DIR__.'/update_files.json'),true,32,JSON_THROW_ON_ERROR);$hashes=[];
foreach($paths as $path)$hashes[$path]=hash_file('sha256',$source.'/'.$path);
$manifest=['product'=>'digital-signage','version'=>'0.4.21','min_version'=>'0.4.16','signature_algorithm'=>'RSA-SHA256','files'=>$hashes,'migrations'=>['database/migrations/0.4.20.php']];
$raw=json_encode($manifest,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
if(!openssl_sign($raw,$signature,$private,OPENSSL_ALGO_SHA256))throw new RuntimeException('Test signing failed');
$package=$fixture.'/update.zip';$zip=new ZipArchive();$zip->open($package,ZipArchive::CREATE|ZipArchive::OVERWRITE);
$zip->addFromString('manifest.json',$raw);$zip->addFromString('manifest.sig',base64_encode($signature));
foreach($paths as $path)$zip->addFile($source.'/'.$path,'payload/'.$path);$zip->close();
require $fixture.'/app/updater.php';
$clean=package_info($package);if($clean['files']!==$hashes)throw new RuntimeException('Package inspection mismatch');
copy($package,$fixture.'/tampered.zip');$zip=new ZipArchive();$zip->open($fixture.'/tampered.zip');$zip->addFromString('payload/version.txt','0.4.99');$zip->close();
$rejected=false;try{package_info($fixture.'/tampered.zip');}catch(RuntimeException $e){$rejected=str_contains($e->getMessage(),'alterado');}
if(!$rejected)throw new RuntimeException('Tampered package accepted');
$result=update_apply($package);
if($result['version']!=='0.4.21'||trim(file_get_contents($fixture.'/version.txt'))!=='0.4.21')throw new RuntimeException('Version not installed');
foreach($hashes as $path=>$hash)if(hash_file('sha256',$fixture.'/'.$path)!==$hash)throw new RuntimeException('Installed file mismatch: '.$path);
if(file_get_contents($fixture.'/config/config.php')!==file_get_contents($source.'/config/config.php'))throw new RuntimeException('Server config overwritten');
$backup=$fixture.'/storage/backups/'.$result['backup'];$zip=new ZipArchive();$zip->open($backup);
if($zip->locateName('database.sql')===false||$zip->getFromName('files/version.txt')!=="0.4.16\n")throw new RuntimeException('Rollback backup missing');$zip->close();
if(file_exists($fixture.'/storage/maintenance.lock'))throw new RuntimeException('Successful update left maintenance lock');
$rejected=false;try{package_info($package);}catch(RuntimeException $e){$rejected=str_contains($e->getMessage(),'posterior');}
if(!$rejected)throw new RuntimeException('Equal-version reinstall accepted');
echo "SIGNED_UPDATE_PASS: signature, payload tampering, install, hashes, preserved config, SQL/file backup, downgrade guard\n";
