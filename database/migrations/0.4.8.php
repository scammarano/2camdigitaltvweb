<?php
/** 0.4.8 — publish validated APK and unified per-mode configuration. */
declare(strict_types=1);
return static function(PDO $pdo):void {
 require_once ROOT.'/app/apk_releases.php';
 $path=ROOT.'/app/release-assets/DS-TV-0.4.8-Modos-Operativos-debug.apk';
 $sha='5a3766fbf5c745a696cedd055f4d554224783536f687a6f0fe2f4a2f4685ee44';$bytes=2107115;
 if(!is_file($path)||is_link($path)||filesize($path)!==$bytes||!hash_equals($sha,hash_file('sha256',$path)))
   throw new RuntimeException('APK 0.4.8 ausente o con SHA-256 incorrecto');
 $find=$pdo->prepare("SELECT * FROM app_releases WHERE platform='android-tv' AND channel='debug' AND version_name='0.4.8'");
 $find->execute();$release=$find->fetch(PDO::FETCH_ASSOC);
 if($release && (!hash_equals($sha,(string)$release['sha256'])||(int)$release['version_code']!==408))
   throw new RuntimeException('APK 0.4.8 previamente registrada con un archivo diferente');
 if(!$release) {
   $insert=$pdo->prepare("INSERT INTO app_releases(platform,version_name,version_code,channel,status,release_notes,original_filename,storage_key,file_size,sha256,min_sdk,created_at,published_at)
      VALUES('android-tv','0.4.8',408,'debug','published',?,?,?,?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())");
   $insert->execute([
    "2Cam Digital Signage 0.4.8 — Arranque automático del reproductor con diagnóstico como recuperación. Opciones de Cartelera, Overlay y Pantalla inteligente separadas y sincronizadas vía portal, APK y panel LAN. En modo inteligente la web opera emisión/llamada/repetición de turnos y puntos de marcador. Android 4.4+ experimental, probado en compilación pero pendiente de validación física.

APLICACIÓN DEBUG: si el certificado no coincide con la versión instalada es necesario desinstalar y volver a emparejar. Instalación silenciosa no garantizada en Android/Fire OS.",
    '2Cam_Digital_Signage_v0.4.8_API19.apk','bundled:DS-TV-0.4.8-Modos-Operativos-debug.apk',$bytes,$sha,19
   ]);
   $find->execute();$release=$find->fetch(PDO::FETCH_ASSOC);
 }
 if(!$release)throw new RuntimeException('No se pudo registrar APK 0.4.8');
 if($release['status']!=='published') {
   $pdo->prepare("UPDATE app_releases SET status='published',published_at=UTC_TIMESTAMP() WHERE id=?")->execute([(int)$release['id']]);
   $release['status']='published';
 }
 ds_apk_publish_static($release);
 ds_apk_refresh_shortcut($pdo);
 $pdo->exec("INSERT IGNORE INTO schema_migrations(id) VALUES('0.4.8')");
};
