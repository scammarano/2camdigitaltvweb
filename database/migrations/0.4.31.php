<?php
/** Device PIN commands never appear in shared config or backup data. */
declare(strict_types=1);
return static function(PDO $pdo):void{
    $previous=require ROOT.'/database/migrations/0.4.30.php';$previous($pdo);
    $pdo->exec("CREATE TABLE IF NOT EXISTS device_pin_commands(
        device_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
        tenant_id BIGINT UNSIGNED NOT NULL,
        revision BIGINT UNSIGNED NOT NULL DEFAULT 0,
        token_binding CHAR(64) NOT NULL,
        ciphertext BLOB NULL,iv VARBINARY(12) NULL,auth_tag VARBINARY(16) NULL,
        status VARCHAR(12) NOT NULL DEFAULT 'pending',
        requested_by BIGINT UNSIGNED NOT NULL,
        requested_at DATETIME NOT NULL,expires_at DATETIME NOT NULL,applied_at DATETIME NULL,
        INDEX idx_device_pin_tenant(tenant_id,device_id),
        FOREIGN KEY(device_id) REFERENCES devices(id) ON DELETE CASCADE,
        FOREIGN KEY(tenant_id) REFERENCES tenants(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("INSERT IGNORE INTO schema_migrations(id) VALUES('0.4.31')");
};
