<?php
/** 0.4.9 — Publish 2Cam TV APK aligned with the media geometry guide. */
declare(strict_types=1);
return static function(PDO $pdo):void {
 require_once ROOT.'/app/apk_releases.php';
 $path=ROOT.'/app/release-assets/DS-TV-0.4.9-Medios-20-40-API19-debug.apk';
 $sha='0954958357e0bd392c6b18af8cbce9a36f0b6c29326ca1d827b715a8f2c04070';$bytes=2107178;
 if(!is_file($path)||is_link($path)||filesize($path)!==$bytes||!hash_equals($sha,hash_file('sha256',$path)))
   throw new RuntimeException('APK 0.4.9 ausente o alterada; no se publica una descarga inválida');
 $query=$pdo->prepare("SELECT * FROM app_releases WHERE platform='android-tv' AND channel='debug' AND version_name='0.4.9'");
 $query->execute();$release=$query->fetch(PDO::FETCH_ASSOC);
 if($release && (!hash_equals($sha,(string)$release['sha256'])||(int)$release['version_code']!==409||(int)$release['file_size']!==$bytes))
   throw new RuntimeException('Ya existe una APK 0.4.9 con identidad distinta; no se sobrescribe');
 if(!$release){
  $insert=$pdo->prepare("INSERT INTO app_releases(platform,version_name,version_code,channel,status,release_notes,original_filename,storage_key,file_size,sha256,min_sdk,created_at,published_at)
    VALUES('android-tv','0.4.9',409,'debug','published',?,?,?,?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())");
  $insert->execute([
   "2Cam Digital Signage 0.4.9. Las áreas de reproducción para artes de playlists se han alineado con la guía visual del portal: barra inferior 20% y barra lateral derecha 40%. Android API 19+ (Fire OS 5 compatible). La biblioteca de medios distingue orientación de pantalla y forma del archivo y muestra los diez formatos de diseño. Versión DEBUG sujeta a validación física; las firmas debug distintas pueden requerir reinstalación.",
   '2Cam_Digital_Signage_v0.4.9_API19.apk','bundled:DS-TV-0.4.9-Medios-20-40-API19-debug.apk',$bytes,$sha,19
  ]);
  $query->execute();$release=$query->fetch(PDO::FETCH_ASSOC);
 }
 if(!$release)throw new RuntimeException('No se pudo registrar APK 0.4.9');
 if((string)$release['status']!=='published'){
  $pdo->prepare("UPDATE app_releases SET status='published',published_at=UTC_TIMESTAMP() WHERE id=?")->execute([(int)$release['id']]);
  $release['status']='published';
 }
 ds_apk_publish_static($release);
 ds_apk_refresh_shortcut($pdo);
 $pdo->exec("INSERT IGNORE INTO schema_migrations(id) VALUES('0.4.9')");
};
