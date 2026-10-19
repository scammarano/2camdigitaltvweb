<?php
/** Signed ZIP updater. Config, storage and user files are never update targets. */
require_once __DIR__.'/bootstrap.php';
const DS_MAX_ZIP = 50000000;
const DS_MAX_FILES = 700;
const DS_MAX_BACKUP_SQL = 150000000;
function safe_update_path(string $path):bool {
    if(!preg_match('#^(?:app|public|database)/[A-Za-z0-9_.\-/]+$#D',$path) && $path!=='version.txt')return false;
    if(str_contains($path,'..') || str_contains($path,'//') || str_contains($path,'\\'))return false;
    if(preg_match('#(?:^|/)(?:\.htaccess|\.env|config\.php)$#i',$path))return false;
    return true;
}
function safe_target(string $relative):bool{
    $parts=explode('/',$relative);$path=ROOT;
    foreach($parts as $part){$path.='/'.$part;if(is_link($path))return false;}
    return true;
}
/** Public verification keys are stored on the SERVER. Never store a private key here. */
function update_pubkey():string {
    $file=ROOT.'/config/update-pubkey.txt';
    $key=trim((string)@file_get_contents($file));
    if(strlen($key)<40)throw new RuntimeException('Clave pública Ed25519 no disponible');
    return $key;
}
function rsa_public_key():string {
    $file=ROOT.'/config/update-rsa-pubkey.pem';
    if(!is_file($file))throw new RuntimeException('No está instalada la clave pública RSA del actualizador');
    $contents=file_get_contents($file);
    if(!is_string($contents) || !str_contains($contents,'BEGIN PUBLIC KEY'))throw new RuntimeException('Archivo RSA de confianza inválido');
    return $contents;
}
/** Verify signed manifest bytes, not a reconstructed JSON object. */
function verify_update_signature(string $manifestJson,string $signatureB64,string $algorithm):void {
    $signature=base64_decode(trim($signatureB64),true);
    if($signature===false)throw new RuntimeException('Firma del manifiesto no codificada en base64 válido');
    if($algorithm==='RSA-SHA256'){
        if(!function_exists('openssl_verify')||!function_exists('openssl_pkey_get_public'))throw new RuntimeException('OpenSSL no está disponible para verificar firmas RSA');
        $key=openssl_pkey_get_public(rsa_public_key());
        if($key===false)throw new RuntimeException('Clave RSA no pudo abrirse');
        try {
            $details=openssl_pkey_get_details($key);
            if(!is_array($details)||($details['type']??-1)!==OPENSSL_KEYTYPE_RSA||($details['bits']??0)<3072)throw new RuntimeException('La clave RSA no cumple el mínimo de 3072 bits');
            $result=openssl_verify($manifestJson,$signature,$key,OPENSSL_ALGO_SHA256);
            if($result!==1)throw new RuntimeException('Firma RSA-SHA256 no válida o paquete no confiable');
        } finally { /* PHP administra el ciclo de vida de OpenSSLAsymmetricKey */ }
        return;
    }
    if($algorithm==='Ed25519'){
        if(!function_exists('sodium_crypto_sign_verify_detached'))throw new RuntimeException('Este paquete requiere Sodium; utilice el paquete RSA-SHA256 para este servidor');
        $pub=base64_decode(update_pubkey(),true);
        if($pub===false||strlen($pub)!==32||strlen($signature)!==64||!sodium_crypto_sign_verify_detached($signature,$manifestJson,$pub))throw new RuntimeException('Firma Ed25519 no válida');
        return;
    }
    throw new RuntimeException('Algoritmo de firma del manifiesto no admitido');
}
function package_info(string $filename):array {
    if(!class_exists('ZipArchive'))throw new RuntimeException('Falta la extensión PHP ZIP (ZipArchive) para abrir el paquete');
    if(!is_file($filename) || filesize($filename)>DS_MAX_ZIP)throw new RuntimeException('Archivo ZIP inexistente o mayor de 50 MB');
    $zip=new ZipArchive();if($zip->open($filename)!==true)throw new RuntimeException('Archivo ZIP inválido');
    try {
        $raw=$zip->getFromName('manifest.json');$sig=$zip->getFromName('manifest.sig');
        if($raw===false||$sig===false)throw new RuntimeException('Falta manifiesto o firma');
        $manifest=json_decode($raw,true,64,JSON_THROW_ON_ERROR);
        if(!is_array($manifest))throw new RuntimeException('Manifiesto JSON inválido');
        $algorithm=$manifest['signature_algorithm']??'Ed25519'; // Versiones antiguas
        if(!is_string($algorithm))throw new RuntimeException('Algoritmo de firma inválido');
        verify_update_signature($raw,$sig,$algorithm);
        if(($manifest['product']??'')!=='digital-signage'||!preg_match('/^\d+\.\d+\.\d+$/D',$manifest['version']??''))throw new RuntimeException('Producto o versión inválidos');
        if(!version_compare($manifest['version'],trim(file_get_contents(ROOT.'/version.txt')),'>'))throw new RuntimeException('Se requiere versión posterior');
        if(!empty($manifest['min_version']) && version_compare(trim(file_get_contents(ROOT.'/version.txt')),$manifest['min_version'],'<'))throw new RuntimeException('Versión base demasiado antigua');
        $files=$manifest['files']??[];if(!is_array($files)||!$files||count($files)>DS_MAX_FILES)throw new RuntimeException('Listado de archivos incorrecto');
        $expected=['manifest.json'=>true,'manifest.sig'=>true];$expandedBytes=0;
        foreach($files as $path=>$digest) {
            if(!is_string($path)||!safe_update_path($path)||!preg_match('/^[a-f0-9]{64}$/D',$digest))throw new RuntimeException('Ruta/hash prohibidos');
            $entry='payload/'.$path;$expected[$entry]=true;
            $entryData=$zip->getFromName($entry);
            if($entryData===false||!hash_equals($digest,hash('sha256',$entryData)))throw new RuntimeException('Contenido alterado en '.$path);
            if(strlen($entryData)>15000000)throw new RuntimeException('Archivo individual excesivo');
            $expandedBytes+=strlen($entryData);if($expandedBytes>100000000)throw new RuntimeException('Paquete descomprimido demasiado grande');
        }
        if(!isset($files['version.txt']))throw new RuntimeException('Falta version.txt');
        if(isset($manifest['migrations'])&&!is_array($manifest['migrations']))throw new RuntimeException('Migraciones inválidas');
        if(trim($zip->getFromName('payload/version.txt'))!==$manifest['version'])throw new RuntimeException('Versión de payload inconsistente');
        $seenNames=[];for($i=0;$i<$zip->numFiles;$i++){
            $stat=$zip->statIndex($i);$name=$stat['name'];if(isset($seenNames[$name]))throw new RuntimeException('Entrada ZIP duplicada');$seenNames[$name]=1;
            if(str_ends_with($name,'/'))continue;
            if(!isset($expected[$name]))throw new RuntimeException('Archivo inesperado en ZIP: '.$name);
            if(($stat['size']??0)>15000000)throw new RuntimeException('Entrada demasiado grande');
            $ops=0;$attr=0;$zip->getExternalAttributesIndex($i,$ops,$attr);
            $mode=($attr>>16)&0170000;if($mode===0120000)throw new RuntimeException('Los enlaces simbólicos están prohibidos');
        }
        return $manifest;
    } finally {$zip->close();}
}
function sql_backup(string $dest):void {
    $fh=fopen($dest,'wb');if(!$fh)throw new RuntimeException('No se puede crear respaldo de base de datos');
    $written=0;
    $write=function(string $line)use($fh,&$written):void{$written+=strlen($line);if($written>DS_MAX_BACKUP_SQL)throw new RuntimeException('Base de datos superior al límite de respaldo en línea; usar respaldo cPanel');if(fwrite($fh,$line)===false)throw new RuntimeException('Error escribiendo respaldo');};
    try {
        $write('-- Digital Signage SQL Backup '.gmdate('c')."\nSET FOREIGN_KEY_CHECKS=0;\n");
        foreach(dball('SHOW FULL TABLES') as $tableRow){
            if((array_values($tableRow)[1]??'')!=='BASE TABLE')continue;
            $name=(string)array_values($tableRow)[0];if(!preg_match('/^[A-Za-z0-9_]+$/D',$name))throw new RuntimeException('Nombre de tabla inesperado');
            $create=dbone('SHOW CREATE TABLE `'.$name.'`');$ddl=$create['Create Table']??array_values($create)[1]??'';
            $write("\nDROP TABLE IF EXISTS `$name`;\n".$ddl.";\n");
            $stmt=db()->query('SELECT * FROM `'.$name.'`');
            while($row=$stmt->fetch(PDO::FETCH_ASSOC)){
                $cols=array_map(fn($c)=>'`'.str_replace('`','``',$c).'`',array_keys($row));
                $values=array_map(fn($v)=>$v===null?'NULL':db()->quote((string)$v),array_values($row));
                $write('INSERT INTO `'.$name.'`('.implode(',',$cols).') VALUES('.implode(',',$values).");\n");
            }
        }
        $write("SET FOREIGN_KEY_CHECKS=1;\n");
    } finally {fclose($fh);}
}
function update_apply(string $filename):array {
    $info=package_info($filename);$dir=ROOT.'/storage/backups';if(!is_dir($dir))mkdir($dir,0700,true);
    $tag=gmdate('Ymd-His').'-'.bin2hex(random_bytes(3));$backup=$dir.'/before-'.$info['version'].'-'.$tag.'.zip';
    $sqlTmp=$dir.'/before-'.$tag.'.sql';$marker=ROOT.'/storage/maintenance.lock';
    if(file_exists($marker))throw new RuntimeException('Hay mantenimiento pendiente. Revisa el diagnóstico antes de intentar otro.');
    $zip=new ZipArchive();if($zip->open($filename)!==true)throw new RuntimeException('No se puede abrir ZIP');
    $bk=new ZipArchive();if($bk->open($backup,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true)throw new RuntimeException('No se puede crear respaldo ZIP');
    $replaced=[];$added=[];$stage=[];
    try {
        sql_backup($sqlTmp);$bk->addFile($sqlTmp,'database.sql');
        foreach($info['files'] as $relative=>$digest){
            $dest=ROOT.'/'.$relative;
            if(!safe_target($relative))throw new RuntimeException('La ruta atraviesa un enlace simbólico');
            if(is_file($dest)){$bk->addFile($dest,'files/'.$relative);$replaced[]=$relative;}else{$added[]=$relative;}
            $data=$zip->getFromName('payload/'.$relative);
            if($data===false)throw new RuntimeException('Falta archivo');
            $stage[$relative]=$data;
        }
        $bk->addFromString('update-metadata.json',json_encode(['version'=>$info['version'],'replaced'=>$replaced,'added'=>$added]));
        $bk->close();$bk=null;
        if(!is_file($backup)||filesize($backup)<100)throw new RuntimeException('Respaldo vacío');
        file_put_contents($marker,json_encode(['version'=>$info['version'],'backup'=>basename($backup),'started'=>gmdate('c')]),LOCK_EX);
        foreach($stage as $relative=>$bytes){
            $dest=ROOT.'/'.$relative;$folder=dirname($dest);if(!is_dir($folder))mkdir($folder,0755,true);
            if(!safe_target($relative))throw new RuntimeException('Ruta de destino con enlace simbólico');
            $temp=$dest.'.upload-'.bin2hex(random_bytes(5));
            if(file_put_contents($temp,$bytes,LOCK_EX)!==strlen($bytes))throw new RuntimeException('Error escribiendo: '.$relative);
            chmod($temp,0644);if(!rename($temp,$dest))throw new RuntimeException('Error sustituyendo: '.$relative);
        }
        // Signed, reviewed version-specific migrations run only after files are deployed.
        foreach(($info['migrations']??[]) as $migration){
            if(!is_string($migration)||!preg_match('#^database/migrations/[A-Za-z0-9_.-]+\.php$#D',$migration)||!isset($info['files'][$migration]))throw new RuntimeException('Ruta de migración inválida');
            $callback=require ROOT.'/'.$migration;if(!is_callable($callback))throw new RuntimeException('Migración no ejecutable');$callback(db());
        }
        if(!is_file(ROOT.'/version.txt')||trim(file_get_contents(ROOT.'/version.txt'))!==$info['version'])throw new RuntimeException('No se verificó la nueva versión');
        unlink($marker);log_event('update_success',['version'=>$info['version'],'backup'=>basename($backup)]);
        return ['version'=>$info['version'],'backup'=>basename($backup)];
    }catch(Throwable $e){
        // Roll back files from backups; DDL may require manual DB restore from database.sql.
        if($bk instanceof ZipArchive)$bk->close();
        $restore=new ZipArchive();if(is_file($backup)&&$restore->open($backup)===true){
            foreach($replaced as $relative){$bytes=$restore->getFromName('files/'.$relative);if($bytes!==false){$dest=ROOT.'/'.$relative;$tmp=$dest.'.restore-'.bin2hex(random_bytes(4));file_put_contents($tmp,$bytes);rename($tmp,$dest);}}
            foreach($added as $relative){$dest=ROOT.'/'.$relative;if(is_file($dest))unlink($dest);}
            $restore->close();
        }
        // Keep maintenance lock as an explicit warning if DB migration may have partially committed.
        log_event('update_failed',['version'=>$info['version'],'error'=>$e->getMessage(),'backup'=>basename($backup)]);
        throw new RuntimeException('Actualización detenida. Archivos restaurados cuando fue posible. Respaldo: '.basename($backup).'. Incidencia: '.$e->getMessage());
    }finally{if(is_file($sqlTmp))unlink($sqlTmp);$zip->close();}
}
