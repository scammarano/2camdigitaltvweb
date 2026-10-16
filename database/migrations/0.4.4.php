<?php
/** Digital Signage 0.4.4 — append-only device diagnostics and remote sync acknowledgments. */
declare(strict_types=1);
return static function(PDO $pdo):void {
 $pdo->exec("CREATE TABLE IF NOT EXISTS device_diag_events (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 tenant_id BIGINT UNSIGNED NOT NULL,
 device_id BIGINT UNSIGNED NOT NULL,
 event_id CHAR(32) NOT NULL,
 level ENUM('debug','info','warn','error','fatal') NOT NULL,
 area VARCHAR(40) NOT NULL,
 message VARCHAR(360) NOT NULL,
 details VARCHAR(1500) NULL,
 event_at DATETIME NOT NULL,
 received_at DATETIME NOT NULL,
 UNIQUE KEY uq_device_event(device_id,event_id),
 INDEX idx_diag_company_time(tenant_id,received_at),
 INDEX idx_diag_device_time(device_id,received_at),
 FOREIGN KEY(tenant_id) REFERENCES tenants(id),
 FOREIGN KEY(device_id) REFERENCES devices(id) ON DELETE CASCADE
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 $pdo->exec("CREATE TABLE IF NOT EXISTS device_sync_state (
 device_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
 tenant_id BIGINT UNSIGNED NOT NULL,
 requested_version BIGINT UNSIGNED NOT NULL DEFAULT 0,
 applied_version BIGINT UNSIGNED NOT NULL DEFAULT 0,
 requested_at DATETIME NULL,
 applied_at DATETIME NULL,
 requested_by BIGINT UNSIGNED NULL,
 FOREIGN KEY(device_id) REFERENCES devices(id) ON DELETE CASCADE,
 FOREIGN KEY(tenant_id) REFERENCES tenants(id),
 INDEX idx_sync_company(tenant_id,requested_at)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 $pdo->exec('INSERT IGNORE INTO device_sync_state(device_id,tenant_id) SELECT id,tenant_id FROM devices');
 // Publish verified GitHub Actions APK as a real .apk file in the public catalogue.
 require_once ROOT.'/app/apk_releases.php';
 $source=ROOT.'/app/release-assets/DS-TV-0.4.4-API19-debug.apk';
 $expected='4d68476777f45781841f3affb924465f476c557f4e6c93c8b97b1d5ce1473f9f';
 if(!is_file($source)||filesize($source)!==2074627||!hash_equals($expected,hash_file('sha256',$source)))
     throw new RuntimeException('APK 0.4.4 ausente o integridad fallida');
 $exists=$pdo->prepare("SELECT * FROM app_releases WHERE platform='android-tv' AND channel='debug' AND version_name='0.4.4'");
 $exists->execute();$release=$exists->fetch(PDO::FETCH_ASSOC);
 if($release && !hash_equals($expected,(string)$release['sha256']))
     throw new RuntimeException('La versión 0.4.4 ya existe con un archivo distinto; no se sobrescribe');
 if(!$release){
     $insert=$pdo->prepare("INSERT INTO app_releases(platform,version_name,version_code,channel,status,release_notes,original_filename,storage_key,file_size,sha256,min_sdk,created_at,published_at)
      VALUES('android-tv','0.4.4',404,'debug','published',?,?,?,?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())");
     $insert->execute([
       "APK 0.4.4 de diagnóstico: registra fallos persistentes por pantalla, intenta enviar logs antes de sincronizar, permite elegir modo local y atiende solicitudes de sincronización del portal.\nAPI 19+ / Android 4.4+ (experimental en equipos antiguos).\nVersión DEBUG: el diagnóstico del cierre anterior requiere el primer log recibido; no está certificada como estable.",
       'DigitalSignage-TV-v0.4.4-API19-debug.apk',
       'bundled:DS-TV-0.4.4-API19-debug.apk',
       2074627,$expected,19
     ]);
     $exists->execute();$release=$exists->fetch(PDO::FETCH_ASSOC);
 }
 if(!$release)throw new RuntimeException('No se pudo registrar la versión 0.4.4');
 if($release['status']!=='published'){
     $stmt=$pdo->prepare("UPDATE app_releases SET status='published',published_at=UTC_TIMESTAMP() WHERE id=?");
     $stmt->execute([(int)$release['id']]);
     $release['status']='published';
 }
 ds_apk_publish_static($release);
 ds_apk_refresh_shortcut($pdo);
 $pdo->exec("INSERT IGNORE INTO schema_migrations(id) VALUES('0.4.4')");
};
