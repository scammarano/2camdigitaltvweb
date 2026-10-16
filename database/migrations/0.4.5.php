<?php
/** 0.4.5: publish verified GitHub APK; no schema changes required. */
declare(strict_types=1);
return static function(PDO $pdo):void {
 require_once ROOT.'/app/apk_releases.php';
 $path=ROOT.'/app/release-assets/DS-TV-0.4.5-API19-debug.apk';
 $expected='0cf713db2d939dcc4d1a8b3183e58cc921f4e4dc97233fb8f34c2971e972d387';
 $bytes=2084564;
 if(!is_file($path)||is_link($path)||filesize($path)!==$bytes||!hash_equals($expected,hash_file('sha256',$path)))
   throw new RuntimeException('APK 0.4.5 ausente o SHA-256 incorrecto');
 $stmt=$pdo->prepare("SELECT * FROM app_releases WHERE platform='android-tv' AND channel='debug' AND version_name='0.4.5'");
 $stmt->execute();$row=$stmt->fetch(PDO::FETCH_ASSOC);
 if($row && (!hash_equals($expected,(string)$row['sha256'])||(int)$row['version_code']!==405))
   throw new RuntimeException('La versión 0.4.5 ya está registrada con un APK distinto; no se sobrescribe');
 if(!$row){
   $stmt=$pdo->prepare("INSERT INTO app_releases(platform,version_name,version_code,channel,status,release_notes,original_filename,storage_key,file_size,sha256,min_sdk,created_at,published_at)
     VALUES('android-tv','0.4.5',405,'debug','published',?,?,?,?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())");
   $stmt->execute([
      "APK 0.4.5 (pruebas): selección de modo principal recibida desde el portal; chequeo automático de APK cada 30 minutos; descarga HTTPS y verificación SHA-256 + certificado antes de solicitar instalación.\nAndroid/Fire OS solicita confirmar la instalación y exige la misma firma que la versión instalada. Las builds DEBUG actuales requieren reinstalación manual por certificados diferentes.\nEl cierre inesperado del reproductor sigue pendiente de diagnóstico y prueba física.",
      'DigitalSignage-TV-v0.4.5-API19-debug.apk',
      'bundled:DS-TV-0.4.5-API19-debug.apk',$bytes,$expected,19
   ]);
   $stmt=$pdo->prepare("SELECT * FROM app_releases WHERE platform='android-tv' AND channel='debug' AND version_name='0.4.5'");
   $stmt->execute();$row=$stmt->fetch(PDO::FETCH_ASSOC);
 }
 if(!$row)throw new RuntimeException('No se pudo registrar APK 0.4.5');
 if($row['status']!=='published'){
   $stmt=$pdo->prepare("UPDATE app_releases SET status='published',published_at=UTC_TIMESTAMP() WHERE id=?");
   $stmt->execute([(int)$row['id']]);$row['status']='published';
 }
 ds_apk_publish_static($row);
 ds_apk_refresh_shortcut($pdo);
 $pdo->exec("INSERT IGNORE INTO schema_migrations(id) VALUES('0.4.5')");
};
