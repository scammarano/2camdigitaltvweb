<?php
/** 0.4.2: globally managed APK releases, seeded from a verified GitHub Actions build. */
return static function(PDO $pdo):void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS app_releases (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
      platform VARCHAR(32) NOT NULL DEFAULT 'android-tv',
      version_name VARCHAR(40) NOT NULL,
      version_code INT UNSIGNED NOT NULL,
      channel ENUM('stable','beta','debug') NOT NULL DEFAULT 'debug',
      status ENUM('draft','published','retired') NOT NULL DEFAULT 'draft',
      release_notes TEXT NOT NULL,
      original_filename VARCHAR(180) NOT NULL,
      storage_key VARCHAR(160) NOT NULL,
      file_size BIGINT UNSIGNED NOT NULL,
      sha256 CHAR(64) NOT NULL,
      min_sdk SMALLINT UNSIGNED NOT NULL,
      downloads BIGINT UNSIGNED NOT NULL DEFAULT 0,
      created_by BIGINT UNSIGNED NULL,
      created_at DATETIME NOT NULL,
      published_at DATETIME NULL,
      UNIQUE KEY uq_platform_channel_version (platform,channel,version_name),
      INDEX idx_release_public (status,channel,created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // Preloaded APK is outside public/: it can only be downloaded through the controlled endpoint.
    $filename=ROOT.'/app/release-assets/DS-TV-0.4.3-API19-debug.apk';
    $sha='9bacc1d166e8a58344a7e3acf1a4918d85e53b8268ac5714bb49caa2c0b01feb';
    if(!is_file($filename) || !hash_equals($sha,hash_file('sha256',$filename)))
        throw new RuntimeException('El APK inicial no está presente o su hash es distinto');
    $stmt=$pdo->prepare("INSERT IGNORE INTO app_releases
      (platform,version_name,version_code,channel,status,release_notes,original_filename,storage_key,file_size,sha256,min_sdk,created_at,published_at)
      VALUES ('android-tv','0.4.3',403,'debug','published',?,?,?,?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())");
    $stmt->execute([
      "Primera APK de prueba compilada en GitHub Actions.\nCompatibilidad declarada: Android 4.4 (API 19) en adelante. Fire TV Stick 2.ª generación / Fire OS 5: instalar y validar en equipo real.\nCompilación debug; no apta aún como versión comercial. Incluye emparejamiento, configuración y motor de reproducción en evaluación.",
      'DigitalSignage-TV-v0.4.3-API19-debug.apk',
      'bundled:DS-TV-0.4.3-API19-debug.apk',
      filesize($filename),$sha,19
    ]);
    $pdo->exec("INSERT IGNORE INTO schema_migrations(id) VALUES('0.4.2')");
};
