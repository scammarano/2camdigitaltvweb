<?php
/** v0.4.6: publish 2Cam Digital Signage manual diagnostics APK without replacing existing versions. */
declare(strict_types=1);
return static function(PDO $pdo):void {
 require_once ROOT.'/app/apk_releases.php';
 $path=ROOT.'/app/release-assets/DS-TV-0.4.6-API19-diagnostico-debug.apk';
 $expected='baf6e503de9928e2d6df2a2cca3d488d7022b40da825ce0c74ddc5904cbf7aa5';
 $bytes=2096473;
 if(!is_file($path)||is_link($path)||filesize($path)!==$bytes||!hash_equals($expected,hash_file('sha256',$path)))
   throw new RuntimeException('APK v0.4.6 ausente o SHA-256 incorrecto');
 $stmt=$pdo->prepare("SELECT * FROM app_releases WHERE platform='android-tv' AND channel='debug' AND version_name='0.4.6'");
 $stmt->execute();$row=$stmt->fetch(PDO::FETCH_ASSOC);
 if($row && (!hash_equals($expected,(string)$row['sha256'])||(int)$row['version_code']!==406))
   throw new RuntimeException('APK 0.4.6 ya registrada con otro contenido');
 if(!$row){
   $stmt=$pdo->prepare("INSERT INTO app_releases(platform,version_name,version_code,channel,status,release_notes,original_filename,storage_key,file_size,sha256,min_sdk,created_at,published_at)
     VALUES('android-tv','0.4.6',406,'debug','published',?,?,?,?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())");
   $stmt->execute([
     "2Cam Digital Signage v0.4.6 — DIAGNÓSTICO MANUAL: no reproduce ni sincroniza automáticamente al abrir. Ejecuta una prueba a la vez para localizar el cierre después del emparejamiento. Botones: gris pendiente, verde correcto, rojo error. Nuevo nombre e icono 2Cam. Android API 19+.
IMPORTANTE: versión DEBUG. Puede requerir desinstalar la anterior por certificado distinto (se pierden los logs y datos locales). Si una prueba falla, abre Ver logs y envía diagnóstico antes de desinstalar.",
     '2Cam-Digital-Signage-v0.4.6-DIAGNOSTICO.apk',
     'bundled:DS-TV-0.4.6-API19-diagnostico-debug.apk',$bytes,$expected,19
   ]);
   $stmt=$pdo->prepare("SELECT * FROM app_releases WHERE platform='android-tv' AND channel='debug' AND version_name='0.4.6'");
   $stmt->execute();$row=$stmt->fetch(PDO::FETCH_ASSOC);
 }
 if(!$row)throw new RuntimeException('No se pudo registrar la versión 0.4.6');
 if($row['status']!=='published'){
   $stmt=$pdo->prepare("UPDATE app_releases SET status='published',published_at=UTC_TIMESTAMP() WHERE id=?");
   $stmt->execute([(int)$row['id']]);$row['status']='published';
 }
 ds_apk_publish_static($row);
 ds_apk_refresh_shortcut($pdo);
 $pdo->exec("INSERT IGNORE INTO schema_migrations(id) VALUES('0.4.6')");
};
