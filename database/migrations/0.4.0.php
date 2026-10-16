<?php
/** Additive v0.4.0 migration. Requires backup by the signed updater. */
return static function(PDO $pdo):void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS device_mode_blocks (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
      tenant_id BIGINT UNSIGNED NOT NULL, device_id BIGINT UNSIGNED NOT NULL,
      mode TINYINT UNSIGNED NOT NULL,
      days_mask TINYINT UNSIGNED NOT NULL DEFAULT 127,
      start_time TIME NOT NULL,end_time TIME NOT NULL,
      valid_from DATE NULL,valid_until DATE NULL,
      priority INT NOT NULL DEFAULT 0,enabled TINYINT(1) NOT NULL DEFAULT 1,
      INDEX idx_mode_device (tenant_id,device_id,enabled,priority),
      FOREIGN KEY (tenant_id) REFERENCES tenants(id),
      FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS device_runtime_options (
      device_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,tenant_id BIGINT UNSIGNED NOT NULL,
      overlay_interval_sec INT UNSIGNED NOT NULL DEFAULT 900,
      overlay_duration_sec INT UNSIGNED NOT NULL DEFAULT 12,
      modes_enabled TINYINT UNSIGNED NOT NULL DEFAULT 7,
      updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      FOREIGN KEY(device_id) REFERENCES devices(id) ON DELETE CASCADE,
      FOREIGN KEY(tenant_id) REFERENCES tenants(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS device_live_panels (
      device_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,tenant_id BIGINT UNSIGNED NOT NULL,
      widget_type ENUM('queue','scoreboard') NOT NULL DEFAULT 'queue',
      payload_json TEXT NOT NULL,updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      FOREIGN KEY(device_id) REFERENCES devices(id) ON DELETE CASCADE,
      FOREIGN KEY(tenant_id) REFERENCES tenants(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS device_health (
      device_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,tenant_id BIGINT UNSIGNED NOT NULL,
      app_version VARCHAR(32) NULL,active_mode TINYINT UNSIGNED NULL,
      cached_media INT UNSIGNED NOT NULL DEFAULT 0,free_storage_bytes BIGINT UNSIGNED NULL,
      last_error VARCHAR(250) NULL,last_report DATETIME NOT NULL,
      FOREIGN KEY(device_id) REFERENCES devices(id) ON DELETE CASCADE,
      FOREIGN KEY(tenant_id) REFERENCES tenants(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("INSERT IGNORE INTO device_runtime_options(device_id,tenant_id) SELECT id,tenant_id FROM devices");
    $pdo->exec("INSERT IGNORE INTO schema_migrations(id) VALUES('0.4.0')");
};
