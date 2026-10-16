<?php
/** Web v0.4.7: publish validated 2Cam Digital Signage APK v0.4.7; no other portal changes. */
declare(strict_types=1);
return static function(PDO $pdo):void {
    require_once ROOT.'/app/apk_releases.php';
    $path=ROOT.'/app/release-assets/DS-TV-0.4.7-API19-FireOS5-CacheFix-debug.apk';
    $sha='f2ea85e7b17bace1f7b78c06fafe1e7cbc3c46a5c0f35bddd0b8838ba2ca6882';
    $bytes=2096386;
    if(!is_file($path) || is_link($path) || filesize($path)!==$bytes || !hash_equals($sha,hash_file('sha256',$path)))
        throw new RuntimeException('APK 0.4.7 ausente o SHA-256 incorrecto');
    $find=$pdo->prepare("SELECT * FROM app_releases WHERE platform='android-tv' AND channel='debug' AND version_name='0.4.7'");
    $find->execute();
    $release=$find->fetch(PDO::FETCH_ASSOC);
    if($release && (!hash_equals($sha,(string)$release['sha256']) || (int)$release['version_code']!==407 || (int)$release['file_size']!==$bytes))
        throw new RuntimeException('La versión 0.4.7 ya está registrada con otro APK; no se sobrescribe');
    if(!$release) {
        $insert=$pdo->prepare("INSERT INTO app_releases(platform,version_name,version_code,channel,status,release_notes,original_filename,storage_key,file_size,sha256,min_sdk,created_at,published_at)
          VALUES('android-tv','0.4.7',407,'debug','published',?,?,?,?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())");
        $insert->execute([
          "2Cam Digital Signage 0.4.7 — Corrige el error de compatibilidad NoSuchMethodError de getContentLengthLong() al descargar medios en Fire OS 5 / Android API 22. Conserva el menú de diagnóstico manual, el emparejamiento y el registro de logs. Compatible desde API 19 (Android 4.4, experimental).

Compilación DEBUG: puede requerir desinstalar la versión anterior si las firmas no coinciden. Haz copia de los logs antes de desinstalar; se eliminarán el token de emparejamiento y la caché del dispositivo. Aún no está validada la reproducción en equipos físicos.",
          '2Cam_Digital_Signage_v0.4.7_API19.apk',
          'bundled:DS-TV-0.4.7-API19-FireOS5-CacheFix-debug.apk',
          $bytes,$sha,19
        ]);
        $find->execute();
        $release=$find->fetch(PDO::FETCH_ASSOC);
    }
    if(!$release)throw new RuntimeException('No se pudo registrar APK 0.4.7');
    if($release['status']!=='published') {
        $publish=$pdo->prepare("UPDATE app_releases SET status='published',published_at=UTC_TIMESTAMP() WHERE id=?");
        $publish->execute([(int)$release['id']]);
        $release['status']='published';
    }
    // Only published, integrity-checked APKs appear as public downloads.
    ds_apk_publish_static($release);
    ds_apk_refresh_shortcut($pdo);
    $pdo->exec("INSERT IGNORE INTO schema_migrations(id) VALUES('0.4.7')");
};
