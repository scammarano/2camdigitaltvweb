<?php
/** Digital Signage 0.4.1: idempotent desired/applied configuration and backups. */
return static function(PDO $pdo):void {
 $pdo->exec("CREATE TABLE IF NOT EXISTS device_config_desired (
  device_id BIGINT UNSIGNED NOT NULL PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL,
  revision BIGINT UNSIGNED NOT NULL DEFAULT 1, config_json TEXT NOT NULL,
  changed_by BIGINT UNSIGNED NULL, changed_at DATETIME NOT NULL,
  FOREIGN KEY(device_id) REFERENCES devices(id) ON DELETE CASCADE,
  FOREIGN KEY(tenant_id) REFERENCES tenants(id), INDEX idx_tenant_config(tenant_id)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 $pdo->exec("CREATE TABLE IF NOT EXISTS device_config_reported (
  device_id BIGINT UNSIGNED NOT NULL PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL,
  applied_revision BIGINT UNSIGNED NOT NULL DEFAULT 0,
  status ENUM('pending','applied','rejected') NOT NULL DEFAULT 'pending',
  config_json TEXT NULL, error_detail VARCHAR(200) NULL, updated_at DATETIME NOT NULL,
  FOREIGN KEY(device_id) REFERENCES devices(id) ON DELETE CASCADE,
  FOREIGN KEY(tenant_id) REFERENCES tenants(id)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 $pdo->exec("CREATE TABLE IF NOT EXISTS device_config_backups (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,device_id BIGINT UNSIGNED NOT NULL,
  source ENUM('desired','reported','manual') NOT NULL,
  label VARCHAR(150) NOT NULL, config_json TEXT NOT NULL,
  revision BIGINT UNSIGNED NOT NULL DEFAULT 0, created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  FOREIGN KEY(tenant_id) REFERENCES tenants(id),
  FOREIGN KEY(device_id) REFERENCES devices(id) ON DELETE CASCADE,
  INDEX idx_config_backups(tenant_id,device_id,id)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 $pdo->exec("CREATE TABLE IF NOT EXISTS device_config_profiles (
   id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
   tenant_id BIGINT UNSIGNED NOT NULL, name VARCHAR(160) NOT NULL,
   config_json TEXT NOT NULL, updated_at DATETIME NOT NULL,
   UNIQUE KEY uq_profile_company(tenant_id,name),
   FOREIGN KEY(tenant_id) REFERENCES tenants(id)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 $pdo->exec("INSERT IGNORE INTO schema_migrations(id) VALUES('0.4.1')");
};
