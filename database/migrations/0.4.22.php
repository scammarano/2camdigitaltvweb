<?php
declare(strict_types=1);
return static function(PDO $pdo):void {
 $previous=require ROOT.'/database/migrations/0.4.20.php';$previous($pdo);
 $pdo->exec("CREATE TABLE IF NOT EXISTS apk_repository_sources (
  platform VARCHAR(32) PRIMARY KEY, repository VARCHAR(201) NOT NULL,
  android_package VARCHAR(180) NOT NULL DEFAULT '', certificate_sha256 CHAR(64) NOT NULL DEFAULT '',
  token_mode ENUM('shared','custom') NOT NULL DEFAULT 'shared', enabled TINYINT UNSIGNED NOT NULL DEFAULT 0,
  revision INT UNSIGNED NOT NULL DEFAULT 1, last_checked_at DATETIME NULL,
  last_result VARCHAR(500) NOT NULL DEFAULT 'Sin consultar', latest_version VARCHAR(40) NOT NULL DEFAULT '',
  updated_at DATETIME NULL
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 $insert=$pdo->prepare('INSERT IGNORE INTO apk_repository_sources(platform,repository,android_package,certificate_sha256) VALUES(?,?,?,?)');
 // Existing delivery assets are in the web repository. The Android source remains in its own repo.
 $insert->execute(['android-tv','scammarano/2camdigitaltvweb','com.digitalsignage.tv','5390caf27181bb1163bb165867be5a5a83e748899957f0e0f68606ed6282f47a']);
 $insert->execute(['turnos-control','scammarano/2cam-turnos','','']);
 $insert->execute(['sports-control','scammarano/2cam-sports','','']);
 $pdo->exec("CREATE TABLE IF NOT EXISTS apk_release_origins (
  release_id BIGINT UNSIGNED PRIMARY KEY, platform VARCHAR(32) NOT NULL,
  repository VARCHAR(201) NOT NULL, asset_id BIGINT UNSIGNED NOT NULL,
  envelope_sha256 CHAR(64) NOT NULL, certificate_sha256 CHAR(64) NOT NULL,
  imported_at DATETIME NOT NULL, UNIQUE KEY uq_origin(platform,repository,asset_id)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 $pdo->exec("INSERT IGNORE INTO schema_migrations(id) VALUES('0.4.22')");
};
